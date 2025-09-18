<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SplitScreenSession;
use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SessionController extends Controller
{
    /**
     * Start a new split-screen session
     */
    public function startSession(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'topic_id' => 'nullable|exists:learning_topics,id',
            // IMPORTANT: session.lesson_id maps to lesson_plans.id across the app
            'lesson_id' => 'nullable|exists:lesson_plans,id',
            'session_type' => 'required|in:comparison,single',
            'ai_models' => 'required|array',
            'ai_models.*' => 'in:gemini,together',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = Auth::id();

            // CONCURRENCY SAFE: ensure only one active session per user+lesson via transaction + row lock
            $result = DB::transaction(function () use ($request, $userId) {
                // Lock any existing rows for this user+lesson to prevent races
                if ($request->lesson_id) {
                    SplitScreenSession::where('user_id', $userId)
                        ->where('lesson_id', $request->lesson_id)
                        ->lockForUpdate()
                        ->get();

                    // POLICY: Only one active session overall per user. End any other active sessions for different lessons.
                    $others = SplitScreenSession::where('user_id', $userId)
                        ->whereNull('ended_at')
                        ->when($request->lesson_id, fn($q) => $q->where('lesson_id', '!=', $request->lesson_id))
                        ->get();
                    foreach ($others as $s) {
                        $s->ended_at = now();
                        $s->save();
                    }

                    // Re-check for active after acquiring lock
                    $existingActive = SplitScreenSession::where('user_id', $userId)
                        ->where('lesson_id', $request->lesson_id)
                        ->whereNull('ended_at')
                        ->first();
                    if ($existingActive) {
                        return [
                            'session_id' => $existingActive->id,
                            'preserved_session_id' => $existingActive->session_metadata['preserved_session_id'] ?? null,
                            'session_type' => $existingActive->session_type,
                            'ai_models' => $existingActive->ai_models_used,
                            'started_at' => $existingActive->started_at,
                        ];
                    }

                    // Try to reactivate the most recent ended session for this lesson
                    $recentEnded = SplitScreenSession::where('user_id', $userId)
                        ->where('lesson_id', $request->lesson_id)
                        ->whereNotNull('ended_at')
                        ->orderByDesc('ended_at')
                        ->first();
                    if ($recentEnded) {
                        $recentEnded->ended_at = null;
                        $recentEnded->save();
                        return [
                            'session_id' => $recentEnded->id,
                            'preserved_session_id' => $recentEnded->session_metadata['preserved_session_id'] ?? null,
                            'session_type' => $recentEnded->session_type,
                            'ai_models' => $recentEnded->ai_models_used,
                            'started_at' => $recentEnded->started_at,
                        ];
                    }
                }

                // Create new preserved session (lesson-based)
                $sessionData = [
                    'user_id' => $userId,
                    'topic_id' => $request->topic_id,
                    'lesson_id' => $request->lesson_id,
                    'session_type' => $request->session_type,
                    'ai_models_used' => $request->ai_models
                ];
                $preservedSession = \App\Models\PreservedSession::createSession($sessionData);

                // Create new split-screen session (engagement-based) for TICA-E tracking
                $splitScreenSession = SplitScreenSession::create([
                    'user_id' => $userId,
                    'topic_id' => $request->topic_id,
                    'lesson_id' => $request->lesson_id,
                    'session_type' => $request->session_type,
                    'ai_models_used' => $request->ai_models,
                    'started_at' => now(),
                    'total_messages' => 0,
                    'engagement_score' => 0,
                    'quiz_triggered' => false,
                    'practice_triggered' => false,
                    'session_metadata' => [
                        'preserved_session_id' => $preservedSession->session_identifier,
                        'session_type' => $request->session_type
                    ]
                ]);

                Log::info('Split-screen session created for TICA-E tracking', [
                    'user_id' => $userId,
                    'split_screen_session_id' => $splitScreenSession->id,
                    'preserved_session_id' => $preservedSession->session_identifier,
                    'topic_id' => $request->topic_id,
                    'lesson_id' => $request->lesson_id,
                    'session_type' => $request->session_type
                ]);

                return [
                    'session_id' => $splitScreenSession->id,
                    'preserved_session_id' => $preservedSession->session_identifier,
                    'session_type' => $request->session_type,
                    'ai_models' => $request->ai_models,
                    'started_at' => $splitScreenSession->started_at,
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Error starting split-screen session: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to start session',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * End a split-screen session
     */
    public function endSession(Request $request, $sessionId)
    {
        try {
            $userId = Auth::id();
            $session = SplitScreenSession::where('id', $sessionId)
                ->where('user_id', $userId)
                ->first();

            if (!$session) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Session not found'
                ], 404);
            }

            $session->endSession();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'session_id' => $session->id,
                    'duration_minutes' => $session->getDurationMinutes(),
                    'total_messages' => $session->total_messages,
                    'engagement_score' => $session->engagement_score,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error ending split-screen session: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to end session',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Record user choice after quiz/practice/code execution
     */
    public function recordChoice(Request $request, $sessionId)
    {
        $validator = Validator::make($request->all(), [
            'choice' => 'required|in:gemini,together,both,neither',
            'reason' => 'nullable|string|max:500',
            'activity_type' => 'nullable|in:quiz,practice,code_execution',
            'performance_metrics' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = Auth::id();
            $session = SplitScreenSession::where('id', $sessionId)
                ->where('user_id', $userId)
                ->first();

            if (!$session) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Session not found'
                ], 404);
            }

            // Record in session (existing functionality)
            $session->recordUserChoice($request->choice, $request->reason);

            // Determine activity type if not provided
            $activityType = $request->activity_type;
            if (!$activityType) {
                if ($session->quiz_triggered) {
                    $activityType = 'quiz';
                } elseif ($session->practice_triggered) {
                    $activityType = 'practice';
                } else {
                    $activityType = 'code_execution'; // Default for solo room
                }
            }

            // Get performance metrics from recent attempts
            $performanceMetrics = $this->getPerformanceMetrics($userId, $activityType, $session->topic_id, $sessionId);

            // Normalize attribution-related fields for DB compatibility
            $attributionConfidence = $this->mapConfidenceToDecimal($performanceMetrics['attribution_confidence'] ?? null);
            $attributionDelay = $performanceMetrics['attribution_delay_sec'] ?? null;
            if (is_numeric($attributionDelay)) {
                // Guard against negative delays; store null if invalid
                $attributionDelay = max(0, (int)$attributionDelay);
            } else {
                $attributionDelay = null;
            }

            // Store in AIPreferenceLog table
            $preferenceLog = \App\Models\AIPreferenceLog::create([
                'user_id' => $userId,
                'session_id' => $sessionId,
                'topic_id' => $session->topic_id,
                'interaction_type' => $activityType,
                'chosen_ai' => $request->choice,
                'choice_reason' => $request->reason,
                'performance_score' => $performanceMetrics['performance_score'] ?? 100,
                'success_rate' => $performanceMetrics['success_rate'] ?? 100,
                'time_spent_seconds' => $performanceMetrics['time_spent_seconds'] ?? 0,
                'attempt_count' => $performanceMetrics['attempt_count'] ?? 1,
                'difficulty_level' => $session->topic?->difficulty_level ?? 'medium',
                'context_data' => $performanceMetrics['context_data'] ?? [],
                'attribution_chat_message_id' => $performanceMetrics['attribution_chat_message_id'] ?? null,
                'attribution_model' => $performanceMetrics['attribution_model'] ?? null,
                'attribution_confidence' => $attributionConfidence,
                'attribution_delay_sec' => $attributionDelay,
            ]);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'session_id' => $session->id,
                    'user_choice' => $session->user_choice,
                    'choice_reason' => $session->choice_reason,
                    'preference_log_id' => $preferenceLog->id,
                    'activity_type' => $activityType,
                    'performance_metrics' => $performanceMetrics,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error recording user choice: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to record choice',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Map string confidence labels (e.g., 'explicit', 'temporal') to decimal.
     * If already numeric, return a float rounded to 4 decimals. Unknown → null.
     */
    private function mapConfidenceToDecimal($value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return round((float)$value, 4);
        }

        $normalized = strtolower(trim((string)$value));

        return match ($normalized) {
            'very_high' => 0.95,
            'high' => 0.85,
            'explicit' => 0.85,
            'strong' => 0.8,
            'medium' => 0.6,
            'temporal' => 0.6,
            'weak' => 0.4,
            'low' => 0.35,
            'very_low' => 0.2,
            default => null,
        };
    }

    /**
     * Get performance metrics for the current activity
     */
    private function getPerformanceMetrics($userId, $activityType, $topicId = null, $sessionId = null)
    {
        $metrics = [
            'performance_score' => null,
            'success_rate' => null,
            'time_spent_seconds' => null,
            'attempt_count' => null,
            'context_data' => null,
            'attribution_chat_message_id' => null,
            'attribution_model' => null,
            'attribution_confidence' => null,
            'attribution_delay_sec' => null,
        ];

        try {
            switch ($activityType) {
                case 'quiz':
                    // Get latest quiz attempt
                    $quizAttempt = \App\Models\QuizAttempt::where('user_id', $userId)
                        ->when($topicId, fn($q) => $q->whereHas('quiz.module.lessonPlan', fn($sq) => $sq->where('topic_id', $topicId)))
                        ->latest()
                        ->first();

                    if ($quizAttempt) {
                        $metrics['performance_score'] = $quizAttempt->percentage;
                        $metrics['success_rate'] = $quizAttempt->passed ? 100 : 0;
                        $metrics['time_spent_seconds'] = $quizAttempt->time_spent_seconds;
                        $metrics['attempt_count'] = $quizAttempt->attempt_number;
                        $metrics['attribution_model'] = $quizAttempt->attribution_model;
                        $metrics['attribution_confidence'] = $quizAttempt->attribution_confidence;
                        $metrics['attribution_delay_sec'] = $quizAttempt->attribution_delay_sec;
                        $metrics['context_data'] = [
                            'quiz_id' => $quizAttempt->quiz_id,
                            'score' => $quizAttempt->score,
                            'max_score' => $quizAttempt->max_possible_score,
                            'passed' => $quizAttempt->passed,
                        ];
                    }
                    break;

                case 'practice':
                    // Get latest practice attempt
                    $practiceAttempt = \App\Models\PracticeAttempt::where('user_id', $userId)
                        ->when($topicId, fn($q) => $q->whereHas('problem.category', fn($sq) => $sq->where('topic_id', $topicId)))
                        ->latest()
                        ->first();

                    if ($practiceAttempt) {
                        $metrics['performance_score'] = $practiceAttempt->points_earned ?? 0;
                        $metrics['success_rate'] = $practiceAttempt->is_correct ? 100 : 0;
                        $metrics['time_spent_seconds'] = $practiceAttempt->time_spent_seconds;
                        $metrics['attempt_count'] = $practiceAttempt->attempt_number;
                        $metrics['attribution_model'] = $practiceAttempt->attribution_model;
                        $metrics['attribution_confidence'] = $practiceAttempt->attribution_confidence;
                        $metrics['attribution_delay_sec'] = $practiceAttempt->attribution_delay_sec;
                        $metrics['context_data'] = [
                            'problem_id' => $practiceAttempt->problem_id,
                            'is_correct' => $practiceAttempt->is_correct,
                            'points_earned' => $practiceAttempt->points_earned,
                            'complexity_score' => $practiceAttempt->complexity_score,
                        ];
                    }
                    break;

                case 'code_execution':
                    // For code execution, we might not have specific metrics
                    // but we can get recent chat messages for attribution
                    $recentMessage = \App\Models\ChatMessage::where('user_id', $userId)
                        ->where('session_id', $sessionId ?? null)
                        ->latest()
                        ->first();

                    if ($recentMessage) {
                        // Only set attribution if the columns exist
                        if (isset($recentMessage->attribution_model)) {
                            $metrics['attribution_model'] = $recentMessage->attribution_model;
                        }
                        if (isset($recentMessage->attribution_confidence)) {
                            $metrics['attribution_confidence'] = $recentMessage->attribution_confidence;
                        }
                        $metrics['attribution_delay_sec'] = $recentMessage->attribution_delay_sec;
                        $metrics['attribution_chat_message_id'] = $recentMessage->id;
                        $metrics['context_data'] = [
                            'message_id' => $recentMessage->id,
                            'message_type' => $recentMessage->message_type,
                        ];
                    }
                    break;
            }
        } catch (\Exception $e) {
            Log::warning('Error getting performance metrics: ' . $e->getMessage());
        }

        return $metrics;
    }

    /**
     * Request clarification for lesson
     */
    public function requestClarification(Request $request, $sessionId)
    {
        $validator = Validator::make($request->all(), [
            'request' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = Auth::id();
            $session = SplitScreenSession::where('id', $sessionId)
                ->where('user_id', $userId)
                ->first();

            if (!$session) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Session not found'
                ], 404);
            }

            $session->requestClarification($request->request);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'session_id' => $session->id,
                    'clarification_needed' => $session->clarification_needed,
                    'clarification_request' => $session->clarification_request,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error requesting clarification: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to request clarification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get session details and TICA metrics
     */
    public function getSession($sessionId)
    {
        try {
            $userId = Auth::id();
            $session = SplitScreenSession::with(['topic', 'chatMessages'])
                ->where('id', $sessionId)
                ->where('user_id', $userId)
                ->first();

            if (!$session) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Session not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'session' => $session->toArray(),
                    'tica_metrics' => $session->getTicaMetrics(),
                    'is_active' => $session->isActive(),
                    'should_trigger_engagement' => $session->shouldTriggerEngagement(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting session: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get session',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get active session for current user
     */
    public function getActiveSession()
    {
        try {
            $userId = Auth::id();
            $lessonId = request()->query('lesson_id');
            $query = SplitScreenSession::with(['topic'])
                ->where('user_id', $userId)
                ->whereNull('ended_at');

            if ($lessonId) {
                $query->where('lesson_id', $lessonId);
            }

            $session = $query->first();

            if (!$session) {
                return response()->json([
                    'status' => 'success',
                    'data' => null
                ]);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'session' => $session->toArray(),
                    'is_active' => true,
                    'should_trigger_engagement' => $session->shouldTriggerEngagement(),
                    'threshold_status' => $session->getThresholdStatus(),
                    'practice_completed' => $session->practice_completed,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting active session: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get active session',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get threshold status for a session
     */
    public function getThresholdStatus($sessionId)
    {
        try {
            $userId = Auth::id();
            $session = SplitScreenSession::where('id', $sessionId)
                ->where('user_id', $userId)
                ->first();

            if (!$session) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Session not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'session_id' => $session->id,
                    'threshold_status' => $session->getThresholdStatus(),
                    'engagement_score' => $session->engagement_score,
                    'quiz_triggered' => $session->quiz_triggered,
                    'practice_triggered' => $session->practice_triggered,
                    'practice_completed' => $session->practice_completed,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting threshold status: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get threshold status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Increment engagement score
     */
    public function incrementEngagement(Request $request, $sessionId)
    {
        $validator = Validator::make($request->all(), [
            'points' => 'nullable|integer|min:1|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = Auth::id();
            $session = SplitScreenSession::where('id', $sessionId)
                ->where('user_id', $userId)
                ->first();

            if (!$session) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Session not found'
                ], 404);
            }

            $points = $request->points ?? 1;
            $session->incrementEngagement($points);

            // Check for threshold triggers after incrementing
            $thresholdStatus = $session->getThresholdStatus();
            $triggeredActivities = [];

            // Check if quiz should be triggered
            if ($session->shouldTriggerQuiz()) {
                $session->markQuizTriggered();
                $triggeredActivities[] = 'quiz';
                Log::info('Quiz threshold triggered for session', [
                    'session_id' => $session->id,
                    'user_id' => $userId,
                    'engagement_score' => $session->engagement_score
                ]);
            }

            // Check if practice should be triggered
            if ($session->shouldTriggerPractice()) {
                $session->markPracticeTriggered();
                $triggeredActivities[] = 'practice';
                Log::info('Practice threshold triggered for session', [
                    'session_id' => $session->id,
                    'user_id' => $userId,
                    'engagement_score' => $session->engagement_score
                ]);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'session_id' => $session->id,
                    'engagement_score' => $session->engagement_score,
                    'should_trigger_engagement' => $session->shouldTriggerEngagement(),
                    'threshold_status' => $session->getThresholdStatus(),
                    'triggered_activities' => $triggeredActivities,
                    'quiz_triggered' => $session->quiz_triggered,
                    'practice_triggered' => $session->practice_triggered,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error incrementing engagement: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to increment engagement',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
