<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\AI\TutorService;
use App\Services\AI\GeminiService;
use App\Services\AI\JavaExecutionService;
use App\Models\ChatMessage;
use App\Models\LearningSession;
use App\Models\LearningTopic;
use App\Models\UserProgress;
use App\Models\LessonPlan;
use App\Models\LessonModule;
use App\Models\LessonExercise;
use App\Models\ModuleProgress;
use App\Models\ExerciseAttempt;
use App\Models\QuizAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AITutorController extends Controller
{
    protected $tutorService;
    protected $geminiService;
    protected $javaExecutionService;

    /**
     * Create a new controller instance.
     */
    public function __construct(TutorService $tutorService, GeminiService $geminiService, JavaExecutionService $javaExecutionService)
    {
        $this->tutorService = $tutorService;
        $this->geminiService = $geminiService;
        $this->javaExecutionService = $javaExecutionService;
    }

    /**
     * Get a response from the AI tutor.
     */
    public function chat(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required|string',
            'conversation_history' => 'nullable|array',
            'topic_id' => 'nullable|exists:learning_topics,id',
            'module_id' => 'nullable|exists:lesson_modules,id',
            'session_id' => 'nullable|exists:learning_sessions,id',
            'preferences' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Get the topic if provided
            $topic = null;
            if ($request->has('topic_id') && $request->topic_id) {
                $topic = LearningTopic::find($request->topic_id);
                if (!$topic) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Topic not found with ID: ' . $request->topic_id
                    ], 404);
                }
            }

            // Get the module if provided
            $module = null;
            if ($request->has('module_id') && $request->module_id) {
                $module = LessonModule::with('lessonPlan')->find($request->module_id);
                
                if (!$module) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Module not found with ID: ' . $request->module_id
                    ], 404);
                }
                
                // If the module is found but no topic was specified, get the topic from the module's lesson plan
                if ($module && !$topic && $module->lessonPlan) {
                    $topic = $module->lessonPlan->topic;
                }
            }

            // Get or create a session if not provided
            $session = null;
            if ($request->has('session_id') && $request->session_id) {
                $session = LearningSession::find($request->session_id);
                if (!$session) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Session not found with ID: ' . $request->session_id
                    ], 404);
                }
            } else if ($topic) {
                // Create a new session for this topic if none provided
                $userId = Auth::id() ?? 1;
                
                try {
                    $session = LearningSession::create([
                        'user_id' => $userId,
                        'topic_id' => $topic->id,
                        'title' => 'Session on ' . $topic->title,
                        'started_at' => now(),
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to create new learning session', [
                        'error' => $e->getMessage(),
                        'user_id' => $userId,
                        'topic_id' => $topic->id
                    ]);
                    // Continue without session if creation fails
                }
            }

            // Initialize context for the AI tutor
            $context = [];
            
            // If a module is provided, track progress and add module content to context
            if ($module) {
                $userId = Auth::id() ?? 1;
                
                // Track module progress
                try {
                    $moduleProgress = ModuleProgress::firstOrCreate(
                        [
                            'user_id' => $userId,
                            'module_id' => $module->id
                        ],
                        [
                            'status' => 'in_progress',
                            'started_at' => now(),
                            'last_activity_at' => now()
                        ]
                    );
                    
                    $moduleProgress->markAsStarted();
                    
                    // Add module content to context for the AI tutor
                    $context = [
                        'module_title' => $module->title,
                        'module_content' => $module->content,
                        'examples' => $module->examples,
                        'key_points' => $module->key_points,
                        'teaching_strategy' => $module->teaching_strategy,
                        'common_misconceptions' => $module->common_misconceptions
                    ];
                    
                    // Add guidance notes if they exist
                    if ($module->guidance_notes) {
                        $context['guidance_notes'] = $module->guidance_notes;
                    }
                    
                    // Add any struggle points from previous interactions
                    if ($moduleProgress->struggle_points) {
                        $context['struggle_points'] = $moduleProgress->struggle_points;
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to track module progress', [
                        'error' => $e->getMessage(),
                        'user_id' => $userId,
                        'module_id' => $module->id
                    ]);
                    // Continue without module progress if tracking fails
                }
            }

            // Validate question before proceeding
            if (empty($request->question) || !is_string($request->question)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid question format. Question must be a non-empty string.'
                ], 400);
            }

            // Ensure preferences is an array
            $preferences = $request->preferences ?? [];
            if (!is_array($preferences)) {
                $preferences = [];
            }

            // Ensure conversation_history is an array
            $conversationHistory = $request->conversation_history ?? [];
            if (!is_array($conversationHistory)) {
                $conversationHistory = [];
            }

            // Get response from AI tutor
            $isFallback = false;
            $responseMessage = null;
            
            try {
                // Determine which AI service to use based on preferences
                $model = isset($preferences['model']) ? strtolower($preferences['model']) : 'together';
                
                if ($model === 'gemini') {
                    $response = $this->geminiService->getResponse(
                        $request->question,
                        $conversationHistory,
                        $preferences,
                        $topic ? $topic->title : null
                    );
                } else {
                    // Default to Together AI
                    $response = $this->tutorService->getResponseWithContext(
                        $request->question,
                        $conversationHistory,
                        $preferences,
                        $topic ? $topic->title : null,
                        $context
                    );
                }
                
                // Check if this is a fallback response (contains specific fallback phrases)
                if (strpos($response, 'temporarily unavailable') !== false ||
                    strpos($response, 'experiencing connectivity issues') !== false ||
                    strpos($response, 'having trouble connecting') !== false) {
                    $isFallback = true;
                    $responseMessage = 'AI service temporarily unavailable';
                    
                    // Log that we received a fallback response
                    Log::warning('Received fallback response from TutorService', [
                        'response' => $response,
                        'question' => $request->question
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('AI Tutor service error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                // Create a simplified fallback response directly from the controller
                $response = "The AI service is temporarily unavailable. Please try again in a few minutes.";
                $isFallback = true;
                $responseMessage = 'Error getting response from AI tutor: ' . $e->getMessage();
            }

            // Save the chat message with both question and response
            try {
                $userId = Auth::id() ?? 1;
                
                ChatMessage::create([
                    'user_id' => $userId,
                    'message' => $request->question,
                    'response' => $response,
                    'topic' => $topic ? $topic->title : null,
                    'topic_id' => $topic ? $topic->id : null,
                    'context' => !empty($context) ? json_encode($context) : null,
                    'conversation_history' => $request->conversation_history ?? [],
                    'preferences' => $request->preferences ?? [],
                    'is_fallback' => $isFallback,
                ]);
            } catch (\Exception $e) {
                // Log the error but continue since this is not critical
                Log::error('Failed to save chat message', [
                    'error' => $e->getMessage(),
                    'user_id' => $userId
                ]);
                // We don't need to return an error here as the main functionality worked
            }

            // Update session last_activity if available
            if ($session) {
                try {
                    $session->update([
                        'last_activity_at' => now(),
                    ]);
                } catch (\Exception $e) {
                    // Log the error but continue
                    Log::error('Failed to update session last_activity', [
                        'error' => $e->getMessage(),
                        'session_id' => $session->id
                    ]);
                }
            }

            // Return the response to the client with appropriate status code
            return response()->json([
                'status' => $isFallback ? 'partial' : 'success',
                'message' => $responseMessage,
                'data' => [
                    'response' => $response,
                    'session_id' => $session ? $session->id : null,
                    'is_fallback' => $isFallback
                ]
            ], $isFallback ? 206 : 200); // Use 206 Partial Content for fallback responses
            
        } catch (\Exception $e) {
            Log::error('Error in AITutorController::chat', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred: ' . $e->getMessage(),
                'data' => [
                    'response' => 'The AI service encountered an unexpected error. Please try again later.',
                    'is_fallback' => true
                ]
            ], 500);
        }
    }

    /**
     * Execute Java code and get feedback from the AI tutor.
     */
    public function executeCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
            'input' => 'nullable|string',
            'session_id' => 'nullable|exists:learning_sessions,id',
            'topic_id' => 'nullable|exists:learning_topics,id',
            'module_id' => 'nullable|exists:lesson_modules,id',
            'exercise_id' => 'nullable|exists:lesson_exercises,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = Auth::id() ?? 1;
            
            // Execute the code
            $executionResult = $this->javaExecutionService->executeJavaCode(
                $request->code,
                $request->input ?? ''
            );

            // Variables for additional context and result tracking
            $testResults = null;
            $isCorrect = false;
            $score = 0;
            $exerciseContext = [];

            // If this is for a specific exercise, evaluate against test cases
            if ($request->has('exercise_id') && $request->exercise_id) {
                $exercise = LessonExercise::find($request->exercise_id);
                
                if ($exercise && $exercise->test_cases) {
                    // Run test cases
                    $testResults = [];
                    $passedCount = 0;
                    
                    foreach ($exercise->test_cases as $index => $testCase) {
                        $testInput = $testCase['input'] ?? '';
                        $expectedOutput = $testCase['output'] ?? '';
                        
                        // Execute with test case input
                        $testExecution = $this->javaExecutionService->executeJavaCode(
                            $request->code,
                            $testInput
                        );
                        
                        $passed = false;
                        if ($testExecution['success']) {
                            // Simple string comparison - can be enhanced with more sophisticated comparison
                            $actualOutput = trim($testExecution['stdout'] ?? '');
                            $expectedOutput = trim($expectedOutput);
                            $passed = $actualOutput == $expectedOutput;
                            
                            if ($passed) {
                                $passedCount++;
                            }
                        }
                        
                        $testResults[] = [
                            'test_number' => $index + 1,
                            'input' => $testInput,
                            'expected_output' => $expectedOutput,
                            'actual_output' => $testExecution['stdout'] ?? '',
                            'passed' => $passed
                        ];
                    }
                    
                    // Calculate score and correctness
                    $totalTests = count($exercise->test_cases);
                    $score = $totalTests > 0 ? round(($passedCount / $totalTests) * 100) : 0;
                    $isCorrect = $score >= 100;
                    
                    // Add exercise context for AI feedback
                    $exerciseContext = [
                        'exercise_title' => $exercise->title,
                        'exercise_instructions' => $exercise->instructions,
                        'test_results' => $testResults,
                        'is_correct' => $isCorrect,
                        'score' => $score
                    ];
                    
                    // Record this attempt
                    $attemptNumber = ExerciseAttempt::getNextAttemptNumber($userId, $exercise->id);
                    
                    ExerciseAttempt::create([
                        'user_id' => $userId,
                        'exercise_id' => $exercise->id,
                        'attempt_number' => $attemptNumber,
                        'submitted_code' => $request->code,
                        'is_correct' => $isCorrect,
                        'score' => $score,
                        'test_results' => $testResults,
                        'time_spent_seconds' => $request->time_spent_seconds ?? 0
                    ]);
                }
            }

            // Get AI feedback on the code
            $aiFeedback = '';
            if ($executionResult['success']) {
                // Add relevant context for the AI feedback
                $feedbackContext = [
                    'stdout' => $executionResult['stdout'] ?? '',
                    'stderr' => $executionResult['stderr'] ?? '',
                    'topic' => $request->topic_id ? LearningTopic::find($request->topic_id)->title : null,
                    'conversation_history' => $request->conversation_history ?? []
                ];
                
                // Merge with exercise context if available
                if (!empty($exerciseContext)) {
                    $feedbackContext = array_merge($feedbackContext, $exerciseContext);
                }
                
                // Determine which AI service to use based on preferences
                $model = 'together';
                if ($request->has('preferences') && is_array($request->preferences) && isset($request->preferences['model'])) {
                    $model = strtolower($request->preferences['model']);
                }
                
                if ($model === 'gemini') {
                    $aiFeedback = $this->geminiService->evaluateCode(
                        $request->code,
                        $feedbackContext['stdout'] ?? '',
                        $feedbackContext['stderr'] ?? '',
                        $feedbackContext['topic'] ?? null
                    );
                } else {
                    $aiFeedback = $this->tutorService->evaluateCodeWithContext(
                        $request->code,
                        $feedbackContext
                    );
                }
            }

            // Save the code to session history if provided
            if ($request->has('session_id') && $request->session_id) {
                $session = LearningSession::find($request->session_id);
                
                if ($session) {
                    // Update session last_activity
                    $session->update([
                        'last_activity_at' => now(),
                    ]);
                }
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'execution' => $executionResult,
                    'test_results' => $testResults,
                    'is_correct' => $isCorrect,
                    'score' => $score,
                    'feedback' => $aiFeedback,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error executing Java code: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error executing Java code',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Execute Java project with multiple files and get feedback from the AI tutor.
     */
    public function executeProject(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'files' => 'required|array',
            'files.*.path' => 'required|string',
            'files.*.content' => 'required|string',
            'main_class' => 'required|string',
            'input' => 'nullable|string',
            'session_id' => 'nullable|exists:learning_sessions,id',
            'topic_id' => 'nullable|exists:learning_topics,id',
            'module_id' => 'nullable|exists:lesson_modules,id',
            'exercise_id' => 'nullable|exists:lesson_exercises,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = Auth::id() ?? 1;
            
            // Execute the project
            $executionResult = $this->javaExecutionService->executeJavaProject(
                $request->files,
                $request->main_class,
                $request->input ?? ''
            );

            // Variables for additional context and result tracking
            $testResults = null;
            $isCorrect = false;
            $score = 0;
            $exerciseContext = [];

            // If this is for a specific exercise, evaluate against test cases
            if ($request->has('exercise_id') && $request->exercise_id) {
                $exercise = LessonExercise::find($request->exercise_id);
                
                if ($exercise && $exercise->test_cases) {
                    // Run test cases
                    $testResults = [];
                    $passedCount = 0;
                    
                    foreach ($exercise->test_cases as $index => $testCase) {
                        $testInput = $testCase['input'] ?? '';
                        $expectedOutput = $testCase['output'] ?? '';
                        
                        // Execute with test case input
                        $testExecution = $this->javaExecutionService->executeJavaProject(
                            $request->files,
                            $request->main_class,
                            $testInput
                        );
                        
                        $passed = false;
                        if ($testExecution['success']) {
                            // Simple string comparison - can be enhanced with more sophisticated comparison
                            $actualOutput = trim($testExecution['stdout'] ?? '');
                            $expectedOutput = trim($expectedOutput);
                            $passed = $actualOutput == $expectedOutput;
                            
                            if ($passed) {
                                $passedCount++;
                            }
                        }
                        
                        $testResults[] = [
                            'test_number' => $index + 1,
                            'input' => $testInput,
                            'expected_output' => $expectedOutput,
                            'actual_output' => $testExecution['stdout'] ?? '',
                            'passed' => $passed
                        ];
                    }
                    
                    // Calculate score and correctness
                    $totalTests = count($exercise->test_cases);
                    $score = $totalTests > 0 ? round(($passedCount / $totalTests) * 100) : 0;
                    $isCorrect = $score >= 100;
                    
                    // Add exercise context for AI feedback
                    $exerciseContext = [
                        'exercise_title' => $exercise->title,
                        'exercise_instructions' => $exercise->instructions,
                        'test_results' => $testResults,
                        'is_correct' => $isCorrect,
                        'score' => $score
                    ];
                    
                    // Record this attempt - store all files content
                    $attemptNumber = ExerciseAttempt::getNextAttemptNumber($userId, $exercise->id);
                    
                    // Collect all code into a single string for storage
                    $allCode = '';
                    foreach ($request->files as $file) {
                        $allCode .= "// File: " . $file['path'] . "\n";
                        $allCode .= $file['content'] . "\n\n";
                    }
                    
                    ExerciseAttempt::create([
                        'user_id' => $userId,
                        'exercise_id' => $exercise->id,
                        'attempt_number' => $attemptNumber,
                        'submitted_code' => $allCode,
                        'is_correct' => $isCorrect,
                        'score' => $score,
                        'test_results' => $testResults,
                        'time_spent_seconds' => $request->time_spent_seconds ?? 0
                    ]);
                }
            }

            // Get AI feedback on the code
            $aiFeedback = '';
            if ($executionResult['success']) {
                // Extract main file content for feedback
                $mainFile = null;
                foreach ($request->files as $file) {
                    if (strpos($file['path'], $request->main_class) !== false) {
                        $mainFile = $file;
                        break;
                    }
                }
                
                $mainCode = $mainFile ? $mainFile['content'] : '';
                
                // Add relevant context for the AI feedback
                $feedbackContext = [
                    'stdout' => $executionResult['stdout'] ?? '',
                    'stderr' => $executionResult['stderr'] ?? '',
                    'project_files' => $request->files, // Pass all files for context
                    'main_class' => $request->main_class,
                    'topic' => $request->topic_id ? LearningTopic::find($request->topic_id)->title : null,
                    'conversation_history' => $request->conversation_history ?? []
                ];
                
                // Merge with exercise context if available
                if (!empty($exerciseContext)) {
                    $feedbackContext = array_merge($feedbackContext, $exerciseContext);
                }
                
                // Determine which AI service to use based on preferences
                $model = 'together';
                if ($request->has('preferences') && is_array($request->preferences) && isset($request->preferences['model'])) {
                    $model = strtolower($request->preferences['model']);
                }
                
                if ($model === 'gemini') {
                    $aiFeedback = $this->geminiService->evaluateCode(
                        $mainCode,
                        $feedbackContext['stdout'] ?? '',
                        $feedbackContext['stderr'] ?? '',
                        $feedbackContext['topic'] ?? null
                    );
                } else {
                    $aiFeedback = $this->tutorService->evaluateCodeWithContext(
                        $mainCode, // Pass the main file content
                        $feedbackContext
                    );
                }
            }

            // Save the project to session history if provided
            if ($request->has('session_id') && $request->session_id) {
                $session = LearningSession::find($request->session_id);
                
                if ($session) {
                    // Update session last_activity
                    $session->update([
                        'last_activity_at' => now(),
                    ]);
                }
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'execution' => $executionResult,
                    'test_results' => $testResults,
                    'is_correct' => $isCorrect,
                    'score' => $score,
                    'feedback' => $aiFeedback,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error executing Java project: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error executing Java project',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user progress for a topic.
     */
    public function updateProgress(Request $request)
    {
        // Full dump of request data for deep debugging
        Log::debug('Update Progress Request DETAILED', [
            'all_raw' => $request->all(),
            'json_decode_test' => json_decode($request->getContent(), true),
            'content_type' => $request->header('Content-Type'),
            'completed_subtopics_type' => is_array($request->completed_subtopics) ? 'array' : gettype($request->completed_subtopics),
            'progress_data_type' => gettype($request->progress_data),
            'progress_percentage_type' => gettype($request->progress_percentage),
            'progress_percentage_value' => $request->progress_percentage,
            'REQUEST_CONTENT' => $request->getContent()
        ]);
        
        // Pre-process progress_percentage to ensure it's an integer
        if ($request->has('progress_percentage')) {
            $request->merge([
                'progress_percentage' => intval($request->progress_percentage)
            ]);
        }
        
        $validator = Validator::make($request->all(), [
            'topic_id' => 'required|exists:learning_topics,id',
            'progress_percentage' => 'required|integer|min:0|max:100',
            'status' => 'nullable|string|in:not_started,in_progress,completed',
            'time_spent_minutes' => 'nullable|integer|min:0',
            'exercises_completed' => 'nullable|integer|min:0',
            'exercises_total' => 'nullable|integer|min:0',
            'completed_subtopics' => 'required|string',  // Now we expect a JSON string
            'progress_data' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            Log::warning('Update Progress Validation Failed DETAILED', [
                'errors' => $validator->errors()->toArray(),
                'received_data' => $request->all(),
                'raw_content' => $request->getContent(),
                'progress_percentage_value' => $request->progress_percentage,
                'progress_percentage_type' => gettype($request->progress_percentage),
            ]);
            
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
                'debug_info' => [
                    'received_data' => $request->all(),
                    'completed_subtopics_type' => is_array($request->completed_subtopics) ? 'array' : gettype($request->completed_subtopics),
                    'progress_percentage_type' => gettype($request->progress_percentage),
                ]
            ], 422);
        }

        try {
            // Use user ID 1 if not authenticated (for testing purposes)
            $userId = Auth::id() ?? 1;
            
            // Get existing progress record if it exists
            $progress = UserProgress::where('user_id', $userId)
                ->where('topic_id', $request->topic_id)
                ->first();
                
            // Calculate time spent by adding new time to existing time
            $timeSpentMinutes = ($progress ? $progress->time_spent_minutes : 0) + ($request->time_spent_minutes ?? 0);
            
            // Update exercises progress if provided
            $exercisesCompleted = $request->exercises_completed ?? ($progress ? $progress->exercises_completed : 0);
            $exercisesTotal = $request->exercises_total ?? ($progress ? $progress->exercises_total : 0);
            
            // Handle completed subtopics
            $completedSubtopics = [];
            if ($progress && $progress->completed_subtopics) {
                $completedSubtopics = json_decode($progress->completed_subtopics, true) ?? [];
            }
            
            // Process the completed_subtopics as a JSON string
            $inputSubtopics = [];
            if ($request->has('completed_subtopics') && is_string($request->completed_subtopics)) {
                try {
                    $decodedSubtopics = json_decode($request->completed_subtopics, true);
                    if (is_array($decodedSubtopics)) {
                        $inputSubtopics = $decodedSubtopics;
                    }
                } catch (\Exception $e) {
                    Log::error('Error decoding completed_subtopics: ' . $e->getMessage());
                }
            }
            
            // Merge with existing subtopics
            $completedSubtopics = array_unique(array_merge($completedSubtopics, $inputSubtopics));
            
            // Determine status
            $status = $request->status;
            if (!$status) {
                if ($request->progress_percentage >= 100) {
                    $status = 'completed';
                } elseif ($request->progress_percentage > 0) {
                    $status = 'in_progress';
                } else {
                    $status = 'not_started';
                }
            }
            
            // Update streak days
            $currentStreakDays = $progress ? $progress->current_streak_days : 0;
            $lastInteraction = $progress ? $progress->last_interaction_at : null;
            
            if ($lastInteraction) {
                $lastInteractionDate = new \DateTime($lastInteraction);
                $today = new \DateTime();
                $diff = $lastInteractionDate->diff($today);
                
                if ($diff->days == 1) {
                    // Consecutive day, increase streak
                    $currentStreakDays++;
                } elseif ($diff->days > 1) {
                    // Streak broken, reset to 1
                    $currentStreakDays = 1;
                }
                // If same day, don't change streak
            } else {
                // First time, set streak to 1
                $currentStreakDays = 1;
            }
            
            // Set completion timestamps
            $startedAt = null;
            $completedAt = null;
            
            if ($progress) {
                $startedAt = $progress->started_at;
                $completedAt = $progress->completed_at;
            }
            
            if ($status == 'in_progress' && !$startedAt) {
                $startedAt = now();
            } elseif ($status == 'completed' && !$completedAt) {
                $completedAt = now();
            }
            
            // Prepare the update data
            $updateData = [
                'progress_percentage' => $request->progress_percentage,
                'status' => $status,
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'time_spent_minutes' => $timeSpentMinutes,
                'exercises_completed' => $exercisesCompleted,
                'exercises_total' => $exercisesTotal,
                'completed_subtopics' => !empty($completedSubtopics) ? json_encode($completedSubtopics) : null,
                'current_streak_days' => $currentStreakDays,
                'last_interaction_at' => now(),
            ];
            
            // Add progress_data if provided
            if ($request->has('progress_data')) {
                $updateData['progress_data'] = $request->progress_data;
            }
            
            $progress = UserProgress::updateOrCreate(
                [
                    'user_id' => $userId,
                    'topic_id' => $request->topic_id,
                ],
                $updateData
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Progress updated successfully',
                'data' => $progress
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating progress: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error updating progress',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Analyze a user's quiz attempts and provide personalized feedback
     */
    public function analyzeQuizResults(Request $request)
    {
        try {
            $user = Auth::user();
            
            // Validate the incoming request
            $validated = $request->validate([
                'topic_id' => 'nullable|integer',
                'quiz_id' => 'nullable|integer',
                'module_id' => 'nullable|integer',
            ]);
            
            // Get the user's quiz attempts
            $quizAttemptsQuery = QuizAttempt::with(['quiz.questions', 'quiz.module'])
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc');
            
            // Apply filters if provided
            if (isset($validated['quiz_id'])) {
                $quizAttemptsQuery->where('quiz_id', $validated['quiz_id']);
            }
            
            if (isset($validated['module_id'])) {
                $quizAttemptsQuery->whereHas('quiz', function($query) use ($validated) {
                    $query->where('module_id', $validated['module_id']);
                });
            }
            
            if (isset($validated['topic_id'])) {
                $quizAttemptsQuery->whereHas('quiz.module.lessonPlan', function($query) use ($validated) {
                    $query->where('topic_id', $validated['topic_id']);
                });
            }
            
            // Get the attempts
            $quizAttempts = $quizAttemptsQuery->get();
            
            if ($quizAttempts->isEmpty()) {
                return response()->json([
                    'message' => 'No quiz attempts found for analysis.',
                    'status' => 'info'
                ]);
            }
            
            // Get strengths and weaknesses data
            $correctResponsesMap = [];
            $incorrectResponsesMap = [];
            $topicPerformance = [];
            
            foreach ($quizAttempts as $attempt) {
                $quiz = $attempt->quiz;
                if (!$quiz) continue;
                
                $questions = $quiz->questions;
                $responses = $attempt->question_responses;
                $correctQuestions = $attempt->correct_questions ?? [];
                
                foreach ($questions as $question) {
                    $questionId = $question->id;
                    $topicName = $quiz->module?->lessonPlan?->topic?->title ?? 'Unknown Topic';
                    $moduleName = $quiz->module?->title ?? 'Unknown Module';
                    $questionType = $question->type;
                    $questionText = $question->question_text;
                    
                    // Initialize topic performance data
                    if (!isset($topicPerformance[$topicName])) {
                        $topicPerformance[$topicName] = [
                            'correct' => 0,
                            'total' => 0,
                            'modules' => []
                        ];
                    }
                    
                    // Initialize module performance data
                    if (!isset($topicPerformance[$topicName]['modules'][$moduleName])) {
                        $topicPerformance[$topicName]['modules'][$moduleName] = [
                            'correct' => 0,
                            'total' => 0
                        ];
                    }
                    
                    // Track correct/incorrect responses
                    if (in_array($questionId, $correctQuestions)) {
                        // Correct answer
                        if (!isset($correctResponsesMap[$questionType])) {
                            $correctResponsesMap[$questionType] = [];
                        }
                        $correctResponsesMap[$questionType][] = [
                            'question' => $questionText,
                            'module' => $moduleName,
                            'topic' => $topicName
                        ];
                        
                        $topicPerformance[$topicName]['correct']++;
                        $topicPerformance[$topicName]['total']++;
                        $topicPerformance[$topicName]['modules'][$moduleName]['correct']++;
                        $topicPerformance[$topicName]['modules'][$moduleName]['total']++;
                    } else {
                        // Incorrect answer
                        if (!isset($incorrectResponsesMap[$questionType])) {
                            $incorrectResponsesMap[$questionType] = [];
                        }
                        $incorrectResponsesMap[$questionType][] = [
                            'question' => $questionText,
                            'module' => $moduleName,
                            'topic' => $topicName
                        ];
                        
                        $topicPerformance[$topicName]['total']++;
                        $topicPerformance[$topicName]['modules'][$moduleName]['total']++;
                    }
                }
            }
            
            // Calculate percentages for topics
            foreach ($topicPerformance as $topic => $data) {
                if ($data['total'] > 0) {
                    $topicPerformance[$topic]['percentage'] = round(($data['correct'] / $data['total']) * 100);
                } else {
                    $topicPerformance[$topic]['percentage'] = 0;
                }
                
                // Calculate percentages for modules
                foreach ($data['modules'] as $module => $moduleData) {
                    if ($moduleData['total'] > 0) {
                        $topicPerformance[$topic]['modules'][$module]['percentage'] = 
                            round(($moduleData['correct'] / $moduleData['total']) * 100);
                    } else {
                        $topicPerformance[$topic]['modules'][$module]['percentage'] = 0;
                    }
                }
            }
            
            // Identify strengths and weaknesses
            $strengths = [];
            $weaknesses = [];
            
            foreach ($topicPerformance as $topic => $data) {
                if ($data['percentage'] >= 80) {
                    $strengths[] = [
                        'topic' => $topic,
                        'percentage' => $data['percentage'],
                        'type' => 'topic'
                    ];
                } elseif ($data['percentage'] <= 60) {
                    $weaknesses[] = [
                        'topic' => $topic,
                        'percentage' => $data['percentage'],
                        'type' => 'topic'
                    ];
                }
                
                foreach ($data['modules'] as $module => $moduleData) {
                    if ($moduleData['percentage'] >= 80) {
                        $strengths[] = [
                            'topic' => $topic,
                            'module' => $module,
                            'percentage' => $moduleData['percentage'],
                            'type' => 'module'
                        ];
                    } elseif ($moduleData['percentage'] <= 60) {
                        $weaknesses[] = [
                            'topic' => $topic,
                            'module' => $module,
                            'percentage' => $moduleData['percentage'],
                            'type' => 'module'
                        ];
                    }
                }
            }
            
            // Generate tutor feedback using AI service
            $promptData = [
                'topics' => array_keys($topicPerformance),
                'strengths' => $strengths,
                'weaknesses' => $weaknesses,
                'questionTypes' => [
                    'correct' => array_keys($correctResponsesMap),
                    'incorrect' => array_keys($incorrectResponsesMap)
                ]
            ];
            
            $tutorPrompt = "Based on the user's quiz performance, provide personalized feedback and recommendations:\n\n";
            $tutorPrompt .= "Strengths: ";
            if (count($strengths) > 0) {
                foreach ($strengths as $strength) {
                    if ($strength['type'] === 'topic') {
                        $tutorPrompt .= "Topic: {$strength['topic']} ({$strength['percentage']}%), ";
                    } else {
                        $tutorPrompt .= "Module: {$strength['module']} in {$strength['topic']} ({$strength['percentage']}%), ";
                    }
                }
            } else {
                $tutorPrompt .= "None identified yet. ";
            }
            
            $tutorPrompt .= "\n\nWeaknesses: ";
            if (count($weaknesses) > 0) {
                foreach ($weaknesses as $weakness) {
                    if ($weakness['type'] === 'topic') {
                        $tutorPrompt .= "Topic: {$weakness['topic']} ({$weakness['percentage']}%), ";
                    } else {
                        $tutorPrompt .= "Module: {$weakness['module']} in {$weakness['topic']} ({$weakness['percentage']}%), ";
                    }
                }
            } else {
                $tutorPrompt .= "None identified yet. ";
            }
            
            $tutorPrompt .= "\n\nProvide a concise paragraph of feedback about their performance, followed by 3 specific improvement recommendations, and 2 strengths to build upon.";
            
            // Use the AI tutor service to generate feedback
            $aiTutorService = app()->make(\App\Services\AI\TutorService::class);
            $aiFeedback = $aiTutorService->getResponse($tutorPrompt, [], 'quiz_analysis');
            
            return response()->json([
                'analysis' => [
                    'attempt_count' => count($quizAttempts),
                    'topic_performance' => $topicPerformance,
                    'strengths' => $strengths,
                    'weaknesses' => $weaknesses
                ],
                'feedback' => $aiFeedback,
                'status' => 'success'
            ]);
        } catch (\Exception $e) {
            Log::error('Error analyzing quiz results: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to analyze quiz results: ' . $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }
}