<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\AI\TutorService;
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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AITutorController extends Controller
{
    protected $tutorService;
    protected $javaExecutionService;

    /**
     * Create a new controller instance.
     */
    public function __construct(TutorService $tutorService, JavaExecutionService $javaExecutionService)
    {
        $this->tutorService = $tutorService;
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
            }

            // Get the module if provided
            $module = null;
            if ($request->has('module_id') && $request->module_id) {
                $module = LessonModule::with('lessonPlan')->find($request->module_id);
                
                // If the module is found but no topic was specified, get the topic from the module's lesson plan
                if ($module && !$topic && $module->lessonPlan) {
                    $topic = $module->lessonPlan->topic;
                }
            }

            // Get or create a session if not provided
            $session = null;
            if ($request->has('session_id') && $request->session_id) {
                $session = LearningSession::find($request->session_id);
            } else if ($topic) {
                // Create a new session for this topic if none provided
                $userId = Auth::id() ?? 1;
                
                $session = LearningSession::create([
                    'user_id' => $userId,
                    'topic_id' => $topic->id,
                    'title' => 'Session on ' . $topic->title,
                    'started_at' => now(),
                ]);
            }

            // Initialize context for the AI tutor
            $context = [];
            
            // If a module is provided, track progress and add module content to context
            if ($module) {
                $userId = Auth::id() ?? 1;
                
                // Track module progress
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
            }

            // Get response from AI tutor
            $response = $this->tutorService->getResponseWithContext(
                $request->question,
                $request->conversation_history ?? [],
                $request->preferences ?? [],
                $topic ? $topic->title : null,
                $context
            );

            // Save the chat message with both question and response
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
            ]);

            // Update session last_activity if available
            if ($session) {
                $session->update([
                    'last_activity_at' => now(),
                ]);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'response' => $response,
                    'session_id' => $session ? $session->id : null,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error in AI Tutor chat: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error getting response from AI tutor',
                'error' => $e->getMessage()
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
                
                $aiFeedback = $this->tutorService->evaluateCodeWithContext(
                    $request->code,
                    $feedbackContext
                );
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
}
