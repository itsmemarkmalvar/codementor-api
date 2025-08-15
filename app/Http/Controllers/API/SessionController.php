<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SplitScreenSession;
use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SessionController extends Controller
{
    /**
     * Start a new split-screen session
     */
    public function startSession(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'topic_id' => 'nullable|exists:learning_topics,id',
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
            
            // End any existing active session for this user
            SplitScreenSession::where('user_id', $userId)
                ->whereNull('ended_at')
                ->update(['ended_at' => now()]);

            // Create new session
            $session = SplitScreenSession::create([
                'user_id' => $userId,
                'topic_id' => $request->topic_id,
                'session_type' => $request->session_type,
                'ai_models_used' => $request->ai_models,
                'started_at' => now(),
                'engagement_score' => 0,
            ]);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'session_id' => $session->id,
                    'session_type' => $session->session_type,
                    'ai_models' => $session->ai_models_used,
                    'started_at' => $session->started_at,
                ]
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
     * Record user choice after quiz/practice
     */
    public function recordChoice(Request $request, $sessionId)
    {
        $validator = Validator::make($request->all(), [
            'choice' => 'required|in:gemini,together,both,neither',
            'reason' => 'nullable|string|max:500',
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

            $session->recordUserChoice($request->choice, $request->reason);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'session_id' => $session->id,
                    'user_choice' => $session->user_choice,
                    'choice_reason' => $session->choice_reason,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error recording user choice: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to record choice',
                'error' => $e->getMessage()
            ], 500);
        }
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
            $session = SplitScreenSession::with(['topic'])
                ->where('user_id', $userId)
                ->whereNull('ended_at')
                ->first();

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

            return response()->json([
                'status' => 'success',
                'data' => [
                    'session_id' => $session->id,
                    'engagement_score' => $session->engagement_score,
                    'should_trigger_engagement' => $session->shouldTriggerEngagement(),
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
