<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\AIPreferenceLog;
use App\Models\PracticeAttempt;
use Carbon\Carbon;

class AIPreferenceLogController extends Controller
{
    /**
     * Store a new AI preference log entry
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'practice_attempt_id' => 'required|integer|exists:practice_attempts,id',
                'chosen_ai' => 'required|string|in:gemini,together,both,neither',
                'choice_reason' => 'nullable|string|max:500',
                'interaction_type' => 'required|string|in:quiz,practice,code_execution',
                'topic_id' => 'nullable|integer|exists:learning_topics,id',
                'difficulty_level' => 'nullable|string|in:beginner,easy,medium,hard,expert',
                'performance_score' => 'nullable|numeric|min:0|max:100',
                'success_rate' => 'nullable|numeric|min:0|max:100',
                'time_spent_seconds' => 'nullable|integer|min:0',
                'attempt_count' => 'nullable|integer|min:1',
                'context_data' => 'nullable|array',
            ]);

            $userId = Auth::id();
            if (!$userId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Authentication required'
                ], 401);
            }

            // Verify the practice attempt belongs to the authenticated user
            $practiceAttempt = PracticeAttempt::where('id', $validated['practice_attempt_id'])
                ->where('user_id', $userId)
                ->first();

            if (!$practiceAttempt) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Practice attempt not found or access denied'
                ], 404);
            }

            // Get attribution data from the practice attempt
            $attributionData = [
                'attribution_chat_message_id' => $practiceAttempt->attribution_chat_message_id,
                'attribution_model' => $practiceAttempt->attribution_model,
                'attribution_confidence' => $practiceAttempt->attribution_confidence,
                'attribution_delay_sec' => $practiceAttempt->attribution_delay_sec,
            ];

            // Create the preference log entry
            $preferenceLog = AIPreferenceLog::create([
                'user_id' => $userId,
                'session_id' => null, // No session for practice completion
                'topic_id' => $validated['topic_id'] ?? null,
                'interaction_type' => $validated['interaction_type'],
                'chosen_ai' => $validated['chosen_ai'],
                'choice_reason' => $validated['choice_reason'] ?? null,
                'performance_score' => $validated['performance_score'] ?? null,
                'success_rate' => $validated['success_rate'] ?? null,
                'time_spent_seconds' => $validated['time_spent_seconds'] ?? null,
                'attempt_count' => $validated['attempt_count'] ?? 1,
                'difficulty_level' => $validated['difficulty_level'] ?? null,
                'context_data' => $validated['context_data'] ?? [],
                'attribution_chat_message_id' => $attributionData['attribution_chat_message_id'],
                'attribution_model' => $attributionData['attribution_model'],
                'attribution_confidence' => $attributionData['attribution_confidence'],
                'attribution_delay_sec' => $attributionData['attribution_delay_sec'],
            ]);

            Log::info('AI preference log created from practice completion', [
                'user_id' => $userId,
                'practice_attempt_id' => $validated['practice_attempt_id'],
                'chosen_ai' => $validated['chosen_ai'],
                'interaction_type' => $validated['interaction_type'],
                'preference_log_id' => $preferenceLog->id,
            ]);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'preference_log_id' => $preferenceLog->id,
                    'chosen_ai' => $preferenceLog->chosen_ai,
                    'interaction_type' => $preferenceLog->interaction_type,
                    'created_at' => $preferenceLog->created_at,
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error creating AI preference log: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create preference log'
            ], 500);
        }
    }

    /**
     * Get AI preference statistics for the authenticated user
     */
    public function getUserPreferences(Request $request)
    {
        try {
            $userId = Auth::id();
            if (!$userId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Authentication required'
                ], 401);
            }

            $window = $request->get('window', '30d');
            $interactionType = $request->get('interaction_type');
            $topicId = $request->get('topic_id');

            // Calculate date range
            $now = Carbon::now();
            $start = match (true) {
                str_ends_with($window, 'd') => $now->copy()->subDays((int) rtrim($window, 'd')),
                str_ends_with($window, 'w') => $now->copy()->subWeeks((int) rtrim($window, 'w')),
                default => $now->copy()->subDays(30),
            };

            // Build query
            $query = AIPreferenceLog::where('user_id', $userId)
                ->where('created_at', '>=', $start);

            if ($interactionType) {
                $query->where('interaction_type', $interactionType);
            }

            if ($topicId) {
                $query->where('topic_id', $topicId);
            }

            $preferences = $query->get();

            // Calculate statistics
            $totalChoices = $preferences->count();
            $aiChoices = $preferences->groupBy('chosen_ai')->map->count();
            $interactionTypes = $preferences->groupBy('interaction_type')->map->count();

            // Calculate success rates by AI choice
            $successRates = [];
            foreach (['gemini', 'together', 'both', 'neither'] as $ai) {
                $aiPreferences = $preferences->where('chosen_ai', $ai);
                $total = $aiPreferences->count();
                $successful = $aiPreferences->where('success_rate', '>=', 70)->count();
                $successRates[$ai] = $total > 0 ? round(($successful / $total) * 100, 2) : 0;
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'window' => $window,
                    'total_choices' => $totalChoices,
                    'ai_choices' => $aiChoices,
                    'interaction_types' => $interactionTypes,
                    'success_rates' => $successRates,
                    'recent_preferences' => $preferences->take(10)->map(function($pref) {
                        return [
                            'id' => $pref->id,
                            'chosen_ai' => $pref->chosen_ai,
                            'interaction_type' => $pref->interaction_type,
                            'choice_reason' => $pref->choice_reason,
                            'performance_score' => $pref->performance_score,
                            'success_rate' => $pref->success_rate,
                            'created_at' => $pref->created_at,
                        ];
                    }),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching user AI preferences: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch preferences'
            ], 500);
        }
    }
}
