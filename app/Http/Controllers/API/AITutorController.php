<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\AI\TutorService;
use App\Services\AI\JavaExecutionService;
use App\Models\ChatMessage;
use App\Models\LearningSession;
use App\Models\LearningTopic;
use App\Models\UserProgress;
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

            // Get or create a session if not provided
            $session = null;
            if ($request->has('session_id') && $request->session_id) {
                $session = LearningSession::find($request->session_id);
            } else if ($topic) {
                // Create a new session for this topic if none provided
                // Use user ID 1 if not authenticated (for testing purposes)
                $userId = Auth::id() ?? 1;
                
                $session = LearningSession::create([
                    'user_id' => $userId,
                    'topic_id' => $topic->id,
                    'title' => 'Session on ' . $topic->title,
                    'started_at' => now(),
                ]);
            }

            // Get response from AI tutor
            $response = $this->tutorService->getResponse(
                $request->question,
                $request->conversation_history ?? [],
                $request->preferences ?? [],
                $topic ? $topic->title : null
            );

            // Save the user question to chat history
            if ($session) {
                // Use user ID 1 if not authenticated (for testing purposes)
                $userId = Auth::id() ?? 1;
                
                ChatMessage::create([
                    'session_id' => $session->id,
                    'user_id' => $userId,
                    'sender' => 'user',
                    'message' => $request->question,
                ]);

                // Save the AI response to chat history
                ChatMessage::create([
                    'session_id' => $session->id,
                    'user_id' => $userId,
                    'sender' => 'bot',
                    'message' => $response,
                ]);

                // Update session last_activity
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
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Use user ID 1 if not authenticated (for testing purposes)
            $userId = Auth::id() ?? 1;
            
            // Execute the code
            $executionResult = $this->javaExecutionService->executeJavaCode(
                $request->code,
                $request->input ?? ''
            );

            // Get AI feedback on the code if execution was successful
            $aiFeedback = '';
            if ($executionResult['success']) {
                $aiFeedback = $this->tutorService->evaluateCode(
                    $request->code,
                    $executionResult['stdout'] ?? '',
                    $executionResult['stderr'] ?? '',
                    $request->topic_id ? LearningTopic::find($request->topic_id)->title : null
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
        $validator = Validator::make($request->all(), [
            'topic_id' => 'required|exists:learning_topics,id',
            'progress_percentage' => 'required|integer|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Use user ID 1 if not authenticated (for testing purposes)
            $userId = Auth::id() ?? 1;
            
            $progress = UserProgress::updateOrCreate(
                [
                    'user_id' => $userId,
                    'topic_id' => $request->topic_id,
                ],
                [
                    'progress_percentage' => $request->progress_percentage,
                    'last_updated_at' => now(),
                ]
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
