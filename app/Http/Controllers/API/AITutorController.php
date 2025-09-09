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
use App\Models\SplitScreenSession;
use App\Models\PreservedSession;
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

            // Ensure conversation_history is an array and normalize/trim it to avoid model API issues
            $conversationHistory = $request->conversation_history ?? [];
            if (!is_array($conversationHistory)) {
                $conversationHistory = [];
            }
            // Normalize entries to expected shape { role: 'user'|'assistant', content: string }
            $normalizedHistory = [];
            foreach ($conversationHistory as $item) {
                if ($item === null) { continue; }
                if (is_object($item)) { $item = (array) $item; }
                if (!is_array($item)) { continue; }
                $role = isset($item['role']) && strtolower($item['role']) === 'user' ? 'user' : 'assistant';
                $content = isset($item['content']) ? (string) $item['content'] : '';
                if (trim($content) === '') { continue; }
                // Cap extremely long turns to keep payload reasonable
                if (strlen($content) > 1200) {
                    $content = substr($content, 0, 1200);
                }
                $normalizedHistory[] = [ 'role' => $role, 'content' => $content ];
            }
            // Keep only the most recent 10 turns to limit token usage
            if (count($normalizedHistory) > 10) {
                $normalizedHistory = array_slice($normalizedHistory, -10);
            }
            $conversationHistory = $normalizedHistory;

            // Get response from AI tutor
            $isFallback = false;
            $responseMessage = null;
            
            try {
                // Determine which AI service to use based on preferences or top-level param
                $model = strtolower(
                    $request->input('model', $preferences['model'] ?? ($preferences['aiModel'] ?? 'together'))
                );

                // Precheck for missing API keys to avoid confusing generic fallbacks
                $geminiKey = config('services.gemini.api_key', env('GEMINI_API_KEY', ''));
                $togetherKey = config('services.together.api_key', env('TOGETHER_API_KEY', ''));

                if ($model === 'gemini' && empty($geminiKey)) {
                    $isFallback = true;
                    $response = 'Gemini AI is not configured (missing GEMINI_API_KEY). Please set it in the backend .env and reload.';
                } elseif ($model === 'together' && empty($togetherKey)) {
                    $isFallback = true;
                    $response = 'Together AI is not configured (missing TOGETHER_API_KEY). Please set it in the backend .env and reload.';
                } else {
                    $t0 = microtime(true);
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
                    $latencyMs = (int) round((microtime(true) - $t0) * 1000);
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
                
                $savedMessage = ChatMessage::create([
                    'user_id' => $userId,
                    'message' => $request->question,
                    'response' => $response,
                    'topic' => $topic ? $topic->title : null,
                    'topic_id' => $topic ? $topic->id : null,
                    'context' => !empty($context) ? json_encode($context) : null,
                    'conversation_history' => $request->conversation_history ?? [],
                    'preferences' => $request->preferences ?? [],
                    'is_fallback' => $isFallback,
                    'model' => $model,
                    'response_time_ms' => isset($latencyMs) ? $latencyMs : null,
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
                    'is_fallback' => $isFallback,
                    'model' => $model,
                    'chat_message_id' => isset($savedMessage) ? $savedMessage->id : null,
                    'response_time_ms' => isset($latencyMs) ? $latencyMs : null,
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
        // Debug logging
        \Log::info('executeCode called with request data:', $request->all());
        
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
            'input' => 'nullable|string',
            'session_id' => 'nullable|string',
            'topic_id' => 'nullable|exists:learning_topics,id',
            'module_id' => 'nullable|exists:lesson_modules,id',
            'exercise_id' => 'nullable|exists:lesson_exercises,id',
        ]);

        if ($validator->fails()) {
            \Log::error('executeCode validation failed:', $validator->errors()->toArray());
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

            // Get AI feedback on the code (optional - don't fail if AI is unavailable)
            $aiFeedback = '';
            if ($executionResult['success']) {
                try {
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
                } catch (\Exception $aiError) {
                    // Log the AI error but don't fail the entire request
                    Log::warning('AI feedback generation failed: ' . $aiError->getMessage());
                    $aiFeedback = 'Code executed successfully! AI feedback is temporarily unavailable.';
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

            // Get AI feedback on the code (optional - don't fail if AI is unavailable)
            $aiFeedback = '';
            if ($executionResult['success']) {
                try {
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
                } catch (\Exception $aiError) {
                    // Log the AI error but don't fail the entire request
                    Log::warning('AI feedback generation failed: ' . $aiError->getMessage());
                    $aiFeedback = 'Code executed successfully! AI feedback is temporarily unavailable.';
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
            // progress_percentage will be computed server-side
            'status' => 'nullable|string|in:not_started,in_progress,completed',
            'time_spent_minutes' => 'nullable|integer|min:0',
            'exercises_completed' => 'nullable|integer|min:0',
            'exercises_total' => 'nullable|integer|min:0',
            'completed_subtopics' => 'nullable|string',
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
            
            // Merge progress_data (JSON string) into cumulative map
            $currentData = [];
            if ($progress && $progress->progress_data) {
                $decoded = json_decode($progress->progress_data, true);
                if (is_array($decoded)) { $currentData = $decoded; }
            }
            $incomingData = [];
            if ($request->has('progress_data') && is_string($request->progress_data)) {
                $decodedIncoming = json_decode($request->progress_data, true);
                if (is_array($decodedIncoming)) { $incomingData = $decodedIncoming; }
            }
            foreach ($incomingData as $k => $v) {
                if (!is_numeric($v)) { continue; }
                $currentData[$k] = (isset($currentData[$k]) && is_numeric($currentData[$k]))
                    ? ($currentData[$k] + $v)
                    : $v;
            }

            // Compute weighted progress exactly as specified
            $interactionPoints = (int) floor(($currentData['interaction'] ?? 0));
            $codePoints = (int) floor(($currentData['code_execution'] ?? 0));
            // Time points come from total minutes, not the payload
            $timePoints = \App\Services\Progress\ProgressService::computeTimePoints((int) $timeSpentMinutes);
            $quizPoints = (int) floor(($currentData['knowledge_check'] ?? 0));

            $weighted = \App\Services\Progress\ProgressService::computeWeightedProgress(
                $interactionPoints,
                $codePoints,
                $timePoints,
                $quizPoints
            );

            // Determine status from computed progress if not provided
            $computedOverall = (int) $weighted['overall_progress'];
            
            // Prepare the update data
            $updateData = [
                'progress_percentage' => $computedOverall,
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
            
            // Persist merged progress_data plus a server-computed snapshot
            $updateData['progress_data'] = json_encode($currentData);
            
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
                'data' => array_merge($progress->toArray(), [
                    'weighted_breakdown' => $weighted,
                    'time_points' => $timePoints,
                ])
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
     * Heartbeat to accrue time and recompute weighted progress exactly per model.
     * Expects: topic_id, minutes_increment (default 1)
     */
    public function heartbeat(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'topic_id' => 'required|exists:learning_topics,id',
            'minutes_increment' => 'nullable|integer|min:0|max:60',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = Auth::id() ?? 1;
            $topicId = (int) $request->topic_id;
            $inc = (int) ($request->minutes_increment ?? 1);

            $progress = UserProgress::firstOrCreate(
                ['user_id' => $userId, 'topic_id' => $topicId],
                ['progress_percentage' => 0, 'status' => 'in_progress', 'time_spent_minutes' => 0]
            );

            // Increment minutes
            $progress->time_spent_minutes = (int) ($progress->time_spent_minutes ?? 0) + $inc;

            // Compute weighted progress from stored progress_data + new time points
            $currentData = json_decode($progress->progress_data ?? '{}', true) ?: [];
            $interactionPoints = (int) floor(($currentData['interaction'] ?? 0));
            $codePoints = (int) floor(($currentData['code_execution'] ?? 0));
            $quizPoints = (int) floor(($currentData['knowledge_check'] ?? 0));
            $timePoints = \App\Services\Progress\ProgressService::computeTimePoints((int) $progress->time_spent_minutes);

            $weighted = \App\Services\Progress\ProgressService::computeWeightedProgress(
                $interactionPoints, $codePoints, $timePoints, $quizPoints
            );

            $progress->progress_percentage = (int) $weighted['overall_progress'];
            $progress->last_interaction_at = now();
            if ($progress->progress_percentage >= 100) {
                $progress->status = 'completed';
                if (!$progress->completed_at) { $progress->completed_at = now(); }
            } else {
                $progress->status = 'in_progress';
            }
            $progress->save();

            return response()->json([
                'status' => 'success',
                'data' => array_merge($progress->toArray(), [
                    'weighted_breakdown' => $weighted,
                    'time_points' => $timePoints,
                ])
            ]);
        } catch (\Exception $e) {
            Log::error('Heartbeat error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error updating time progress',
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

    /**
     * Get responses from both AI models simultaneously for split-screen comparison.
     */
    public function splitScreenChat(Request $request)
    {
        // Debug logging
        \Log::info('splitScreenChat called with request data:', $request->all());
        
        $validator = Validator::make($request->all(), [
            'question' => 'required|string',
            'conversation_history' => 'nullable|array',
            'conversation_history.*.role' => 'nullable|string|in:user,assistant',
            'conversation_history.*.content' => 'nullable|string',
            'topic_id' => 'nullable|exists:learning_topics,id',
            'module_id' => 'nullable|exists:lesson_modules,id',
            'session_id' => 'nullable|string',
            'preferences' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            \Log::error('splitScreenChat validation failed:', $validator->errors()->toArray());
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Debug authentication
            $userId = Auth::id();
            \Log::info('splitScreenChat authentication check', [
                'user_id' => $userId,
                'authenticated' => Auth::check()
            ]);
            
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

            // Get or create a preserved session
            $session = null;
            $userId = Auth::id() ?? 1;
            
            if ($request->has('session_id') && $request->session_id) {
                // Try to find existing preserved session
                $session = PreservedSession::where('session_identifier', $request->session_id)
                    ->where('user_id', $userId)
                    ->first();
                
                if ($session) {
                    // Reactivate existing session
                    $session->markAsActive();
                    \Log::info('Reactivated existing preserved session', [
                        'session_id' => $session->session_identifier,
                        'user_id' => $userId
                    ]);
                } else {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Session not found with ID: ' . $request->session_id
                    ], 404);
                }
            } else if ($topic) {
                // Check for existing active session for this user and topic
                $existingSession = PreservedSession::getMostRecentSession($userId, $topic->id);
                
                if ($existingSession) {
                    // Use existing session
                    $session = $existingSession;
                    $session->markAsActive();
                    \Log::info('Using existing preserved session', [
                        'session_id' => $session->session_identifier,
                        'user_id' => $userId,
                        'topic_id' => $topic->id
                    ]);
                } else {
                    // Create new preserved session
                    try {
                        \Log::info('Creating new preserved session', [
                            'user_id' => $userId,
                            'topic_id' => $topic->id
                        ]);
                        
                        $session = PreservedSession::createSession([
                            'user_id' => $userId,
                            'topic_id' => $topic->id,
                            'lesson_id' => $module ? $module->id : null,
                            'session_type' => 'comparison',
                            'ai_models_used' => ['gemini', 'together']
                        ]);
                        
                        \Log::info('Preserved session created successfully', [
                            'session_id' => $session->session_identifier
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Failed to create new preserved session', [
                            'error' => $e->getMessage(),
                            'user_id' => $userId,
                            'topic_id' => $topic->id
                        ]);
                        // Continue without session if creation fails
                    }
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

            // Ensure conversation_history is an array and normalize/trim it to avoid model API issues
            $conversationHistory = $request->conversation_history ?? [];
            if (!is_array($conversationHistory)) {
                $conversationHistory = [];
            }
            // Normalize entries to expected shape { role: 'user'|'assistant', content: string }
            $normalizedHistory = [];
            foreach ($conversationHistory as $item) {
                if ($item === null) { continue; }
                if (is_object($item)) { $item = (array) $item; }
                if (!is_array($item)) { continue; }
                $role = isset($item['role']) && strtolower($item['role']) === 'user' ? 'user' : 'assistant';
                $content = isset($item['content']) ? (string) $item['content'] : '';
                if (trim($content) === '') { continue; }
                // Cap extremely long turns to keep payload reasonable
                if (strlen($content) > 1200) {
                    $content = substr($content, 0, 1200);
                }
                $normalizedHistory[] = [ 'role' => $role, 'content' => $content ];
            }
            // Keep only the most recent 10 turns to limit token usage
            if (count($normalizedHistory) > 10) {
                $normalizedHistory = array_slice($normalizedHistory, -10);
            }
            $conversationHistory = $normalizedHistory;
            
            // Create a simplified conversation history for Together AI to prevent validation errors
            $togetherConversationHistory = $normalizedHistory;
            if (count($togetherConversationHistory) > 2) {
                $togetherConversationHistory = array_slice($togetherConversationHistory, -2);
            }

            // Call both AI models sequentially (this is the most reliable approach)
            // The frontend fix ensures only one API call is made, so both AIs will respond
            $responses = [];
            $errors = [];
            
            // Precheck for missing API keys
            $geminiKey = config('services.gemini.api_key', env('GEMINI_API_KEY', ''));
            $togetherKey = config('services.together.api_key', env('TOGETHER_API_KEY', ''));
            
            // Debug logging for API keys
            Log::info('Split-screen chat API key check', [
                'geminiKey_exists' => !empty($geminiKey),
                'geminiKey_length' => strlen($geminiKey),
                'togetherKey_exists' => !empty($togetherKey),
                'togetherKey_length' => strlen($togetherKey),
                'togetherKey_prefix' => substr($togetherKey, 0, 10) . '...',
                'env_together_key' => env('TOGETHER_API_KEY', 'NOT_SET'),
                'config_together_key' => config('services.together.api_key', 'NOT_SET')
            ]);

            // Call Gemini AI first
            $geminiResponse = null;
            $geminiLatency = null;
            if (!empty($geminiKey)) {
                try {
                    $t0 = microtime(true);
                    $geminiResponse = $this->geminiService->getResponse(
                        $request->question,
                        $conversationHistory,
                        $preferences,
                        $topic ? $topic->title : null
                    );
                    $geminiLatency = (int) round((microtime(true) - $t0) * 1000);
                } catch (\Exception $e) {
                    $errors['gemini'] = $e->getMessage();
                    Log::error('Gemini AI error in split-screen chat', [
                        'error' => $e->getMessage(),
                        'question' => $request->question
                    ]);
                }
            } else {
                $errors['gemini'] = 'Gemini AI is not configured (missing GEMINI_API_KEY). Please set it in the backend .env file and reload the application.';
            }

            // Call Together AI second
            $togetherResponse = null;
            $togetherLatency = null;
            if (!empty($togetherKey)) {
                try {
                    $t0 = microtime(true);
                    $togetherResponse = $this->tutorService->getResponseWithContext(
                        $request->question,
                        $togetherConversationHistory,
                        $preferences,
                        $topic ? $topic->title : null,
                        $context
                    );
                    $togetherLatency = (int) round((microtime(true) - $t0) * 1000);
                } catch (\Exception $e) {
                    $errors['together'] = $e->getMessage();
                    Log::error('Together AI error in split-screen chat', [
                        'error' => $e->getMessage(),
                        'question' => $request->question
                    ]);
                }
            } else {
                $errors['together'] = 'Together AI is not configured (missing TOGETHER_API_KEY). Please set it in the backend .env file and reload the application.';
            }

            // Save both responses
            $userId = Auth::id() ?? 1;
            $savedMessages = [];
            
            if ($geminiResponse) {
                try {
                    $savedMessages['gemini'] = ChatMessage::create([
                        'user_id' => $userId,
                        'message' => $request->question,
                        'response' => $geminiResponse,
                        'topic' => $topic ? $topic->title : null,
                        'topic_id' => $topic ? $topic->id : null,
                        'context' => !empty($context) ? json_encode($context) : null,
                        'conversation_history' => $request->conversation_history ?? [],
                        'preferences' => $request->preferences ?? [],
                        'is_fallback' => false,
                        'model' => 'gemini',
                        'response_time_ms' => $geminiLatency,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to save Gemini message', ['error' => $e->getMessage()]);
                }
            }
            
            if ($togetherResponse) {
                try {
                    $savedMessages['together'] = ChatMessage::create([
                        'user_id' => $userId,
                        'message' => $request->question,
                        'response' => $togetherResponse,
                        'topic' => $topic ? $topic->title : null,
                        'topic_id' => $topic ? $topic->id : null,
                        'context' => !empty($context) ? json_encode($context) : null,
                        'conversation_history' => $request->conversation_history ?? [],
                        'preferences' => $request->preferences ?? [],
                        'is_fallback' => false,
                        'model' => 'together',
                        'response_time_ms' => $togetherLatency,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to save Together message', ['error' => $e->getMessage()]);
                }
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'responses' => [
                        'gemini' => $geminiResponse ? [
                            'response' => $geminiResponse,
                            'message_id' => $savedMessages['gemini']->id ?? null,
                            'response_time_ms' => $geminiLatency,
                            'error' => null
                        ] : [
                            'response' => null,
                            'message_id' => null,
                            'response_time_ms' => null,
                            'error' => $errors['gemini'] ?? 'Unknown error'
                        ],
                        'together' => $togetherResponse ? [
                            'response' => $togetherResponse,
                            'message_id' => $savedMessages['together']->id ?? null,
                            'response_time_ms' => $togetherLatency,
                            'error' => null
                        ] : [
                            'response' => null,
                            'message_id' => null,
                            'response_time_ms' => null,
                            'error' => $errors['together'] ?? 'Unknown error'
                        ]
                    ],
                    'session_id' => $session ? $session->id : null,
                    'topic' => $topic ? $topic->title : null,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Split-screen chat error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while processing your request.'
            ], 500);
        }
    }

    /**
     * Get active preserved session for a user.
     */
    public function getActivePreservedSession($userId)
    {
        try {
            $session = PreservedSession::getMostRecentSession($userId);
            
            if (!$session) {
                return response()->json([
                    'status' => 'success',
                    'data' => null
                ]);
            }

            // If session has no conversation history, try to load from chat_messages
            if (empty($session->conversation_history)) {
                $chatMessages = ChatMessage::where('user_id', $userId)
                    ->orderBy('created_at', 'asc')
                    ->get();
                
                if ($chatMessages->count() > 0) {
                    $conversationHistory = [];
                    foreach ($chatMessages as $message) {
                        // Add user message
                        $conversationHistory[] = [
                            'id' => 'user_' . $message->id,
                            'text' => $message->message,
                            'sender' => 'user',
                            'timestamp' => $message->created_at->toISOString(),
                            '_model' => null
                        ];
                        
                        // Add AI response
                        $conversationHistory[] = [
                            'id' => 'ai_' . $message->id,
                            'text' => $message->response,
                            'sender' => 'bot', // Default to bot for AI responses
                            'timestamp' => $message->created_at->toISOString(),
                            '_model' => $message->model ?? 'together' // Default to together if no model specified
                        ];
                    }
                    
                    // Update the session with conversation history
                    $session->update([
                        'conversation_history' => $conversationHistory,
                        'last_activity' => now()
                    ]);
                    
                    Log::info('Loaded conversation history from chat_messages', [
                        'session_id' => $session->session_identifier,
                        'user_id' => $userId,
                        'message_count' => count($conversationHistory)
                    ]);
                }
            }

            // Update last activity when session is accessed
            $session->updateActivity();

            return response()->json([
                'status' => 'success',
                'data' => $session
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting active preserved session', [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get active session'
            ], 500);
        }
    }

    /**
     * Reactivate a preserved session.
     */
    public function reactivatePreservedSession($sessionId)
    {
        try {
            $session = PreservedSession::where('session_identifier', $sessionId)->first();
            
            if (!$session) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Session not found'
                ], 404);
            }

            $session->markAsActive();

            // Log session reactivation for analytics
            Log::info('Preserved session reactivated', [
                'session_id' => $sessionId,
                'user_id' => $session->user_id,
                'topic_id' => $session->topic_id,
                'conversation_history_count' => count($session->conversation_history ?? [])
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $session
            ]);
        } catch (\Exception $e) {
            Log::error('Error reactivating preserved session', [
                'error' => $e->getMessage(),
                'session_id' => $sessionId
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to reactivate session'
            ], 500);
        }
    }

    /**
     * Deactivate a preserved session.
     */
    public function deactivatePreservedSession($sessionId)
    {
        try {
            $session = PreservedSession::where('session_identifier', $sessionId)->first();
            
            if (!$session) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Session not found'
                ], 404);
            }

            $session->markAsInactive();

            return response()->json([
                'status' => 'success',
                'data' => $session
            ]);
        } catch (\Exception $e) {
            Log::error('Error deactivating preserved session', [
                'error' => $e->getMessage(),
                'session_id' => $sessionId
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to deactivate session'
            ], 500);
        }
    }

    /**
     * Delete a preserved session.
     */
    public function deletePreservedSession($sessionId)
    {
        try {
            $session = PreservedSession::where('session_identifier', $sessionId)->first();
            
            if (!$session) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Session not found'
                ], 404);
            }

            $session->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Session deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting preserved session', [
                'error' => $e->getMessage(),
                'session_id' => $sessionId
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete session'
            ], 500);
        }
    }

    /**
     * Get user session history.
     */
    public function getUserSessionHistory($userId)
    {
        try {
            $sessions = PreservedSession::forUser($userId)
                ->orderBy('last_activity', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $sessions
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting user session history', [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get session history'
            ], 500);
        }
    }

    /**
     * Update session metadata (engagement, topic, lesson, etc.) for a preserved session.
     */
    public function updateSessionMetadata(Request $request, $sessionId)
    {
        try {
            // Log the incoming request for debugging
            Log::info('updateSessionMetadata called', [
                'session_id' => $sessionId,
                'request_data' => $request->all()
            ]);

            $validator = Validator::make($request->all(), [
                'engagement_data' => 'nullable|array',
                'engagement_data.score' => 'nullable|numeric',
                'engagement_data.is_threshold_reached' => 'nullable|boolean',
                'engagement_data.events' => 'nullable|array',
                'engagement_data.triggered_activity' => 'nullable|string|in:quiz,practice,null',
                'engagement_data.assessment_sequence' => 'nullable|string|in:quiz,practice,null',
                'topic_data' => 'nullable|array',
                'topic_data.id' => 'nullable|integer',
                'topic_data.title' => 'nullable|string',
                'lesson_data' => 'nullable|array',
                'lesson_data.id' => 'nullable|integer',
                'lesson_data.title' => 'nullable|string',
                'user_preferences' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                Log::error('Session metadata validation failed', [
                    'errors' => $validator->errors()->toArray(),
                    'session_id' => $sessionId
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $session = PreservedSession::where('session_identifier', $sessionId)->first();
            
            if (!$session) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Session not found'
                ], 404);
            }

            // Get current metadata
            $currentMetadata = $session->session_metadata ?? [];
            
            // Update metadata with new data
            $updatedMetadata = array_merge($currentMetadata, [
                'engagement_data' => $request->input('engagement_data'),
                'topic_data' => $request->input('topic_data'),
                'lesson_data' => $request->input('lesson_data'),
                'user_preferences' => $request->input('user_preferences'),
                'last_updated' => now()->toISOString()
            ]);

            // Update session metadata
            $session->update([
                'session_metadata' => $updatedMetadata,
                'last_activity' => now()
            ]);

            Log::info('Session metadata updated', [
                'session_id' => $sessionId,
                'engagement_score' => $request->input('engagement_data.score'),
                'topic_id' => $request->input('topic_data.id'),
                'lesson_id' => $request->input('lesson_data.id')
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $session
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating session metadata', [
                'error' => $e->getMessage(),
                'session_id' => $sessionId
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update session metadata'
            ], 500);
        }
    }

    /**
     * Update conversation history for a preserved session.
     */
    public function updateConversationHistory(Request $request, $sessionId)
    {
        try {
            // Log the incoming request for debugging
            Log::info('updateConversationHistory called', [
                'session_id' => $sessionId,
                'request_data' => $request->all(),
                'conversation_history_count' => count($request->input('conversation_history', []))
            ]);

            $validator = Validator::make($request->all(), [
                'conversation_history' => 'required|array',
                'conversation_history.*.id' => 'nullable|string',
                'conversation_history.*.text' => 'nullable|string',
                'conversation_history.*.sender' => 'nullable|string|in:user,bot,ai',
                'conversation_history.*.timestamp' => 'nullable',
                'conversation_history.*._model' => 'nullable|string|in:gemini,together'
            ]);

            if ($validator->fails()) {
                Log::error('Conversation history validation failed', [
                    'errors' => $validator->errors()->toArray(),
                    'session_id' => $sessionId
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $session = PreservedSession::where('session_identifier', $sessionId)->first();
            
            if (!$session) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Session not found'
                ], 404);
            }

            // Append + dedupe + cap
            $incoming = $request->conversation_history ?? [];
            $existing = $session->conversation_history ?? [];
            if (!is_array($existing)) { $existing = []; }
            $merged = array_merge($existing, $incoming);

            $assoc = [];
            foreach ($merged as $msg) {
                $id = isset($msg['id']) ? (string)$msg['id'] : uniqid('msg_', true);
                $assoc[$id] = [
                    'id' => $id,
                    'text' => $msg['text'] ?? '',
                    'sender' => $msg['sender'] ?? null,
                    'timestamp' => $msg['timestamp'] ?? null,
                    '_model' => $msg['_model'] ?? null,
                ];
            }
            $deduped = array_values($assoc);
            $CAP = 1000;
            if (count($deduped) > $CAP) {
                $deduped = array_slice($deduped, -$CAP);
            }

            $session->update([
                'conversation_history' => $deduped,
                'last_activity' => now()
            ]);

            Log::info('Conversation history updated', [
                'session_id' => $sessionId,
                'message_count' => count($deduped)
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $session
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating conversation history', [
                'error' => $e->getMessage(),
                'session_id' => $sessionId
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update conversation history'
            ], 500);
        }
    }

    /**
     * Get active preserved session for a specific user and lesson.
     */
    public function getActivePreservedSessionByLesson($userId, $lessonId)
    {
        try {
            Log::info('getActivePreservedSessionByLesson called', [
                'user_id' => $userId,
                'lesson_id' => $lessonId
            ]);

            // Validate user ID
            if (!is_numeric($userId)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid user ID'
                ], 400);
            }

            // Validate lesson ID
            if (!is_numeric($lessonId)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid lesson ID'
                ], 400);
            }

            // Get active session for this user and lesson
            $session = PreservedSession::where('user_id', $userId)
                ->where('lesson_id', $lessonId)
                ->where('is_active', true)
                ->orderBy('last_activity', 'desc')
                ->first();

            if ($session) {
                Log::info('Found active session for user and lesson', [
                    'session_id' => $session->session_identifier,
                    'user_id' => $userId,
                    'lesson_id' => $lessonId
                ]);

                return response()->json([
                    'status' => 'success',
                    'data' => $session
                ]);
            } else {
                Log::info('No active session found for user and lesson', [
                    'user_id' => $userId,
                    'lesson_id' => $lessonId
                ]);

                return response()->json([
                    'status' => 'success',
                    'data' => null,
                    'message' => 'No active session found for this lesson'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error getting active preserved session by lesson', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'lesson_id' => $lessonId
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get active session'
            ], 500);
        }
    }
}