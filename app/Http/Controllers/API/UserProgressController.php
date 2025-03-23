<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserProgress;
use Illuminate\Support\Facades\Auth;
use App\Models\LearningTopic;

class UserProgressController extends Controller
{
    /**
     * Display a listing of the resource.
     * Get all progress entries for the current authenticated user
     */
    public function index()
    {
        // Get authenticated user ID or use ID 1 for testing
        $userId = Auth::id() ?? 1;
        
        // Get all progress entries for this user with related topic information
        $progress = UserProgress::with('topic')
            ->where('user_id', $userId)
            ->get()
            ->map(function ($item) {
                // Format the response to include only needed fields
                return [
                    'id' => $item->id,
                    'topic_id' => $item->topic_id,
                    'topic_title' => $item->topic ? $item->topic->title : null,
                    'progress_percentage' => $item->progress_percentage,
                    'status' => $item->status,
                    'time_spent_minutes' => $item->time_spent_minutes,
                    'exercises_completed' => $item->exercises_completed,
                    'exercises_total' => $item->exercises_total,
                    'completed_subtopics' => json_decode($item->completed_subtopics),
                    'current_streak_days' => $item->current_streak_days,
                    'last_interaction_at' => $item->last_interaction_at,
                    'progress_data' => json_decode($item->progress_data),
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $progress
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     * Get progress for a specific topic for the authenticated user
     */
    public function show(string $topicId)
    {
        // Get authenticated user ID or use ID 1 for testing
        $userId = Auth::id() ?? 1;
        
        // Check if the topic exists
        $topic = LearningTopic::find($topicId);
        if (!$topic) {
            return response()->json([
                'status' => 'error',
                'message' => 'Topic not found'
            ], 404);
        }
        
        // Get progress for this user and topic
        $progress = UserProgress::where('user_id', $userId)
            ->where('topic_id', $topicId)
            ->first();
            
        if (!$progress) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'topic_id' => (int)$topicId,
                    'progress_percentage' => 0,
                    'status' => 'not_started',
                    'time_spent_minutes' => 0,
                    'exercises_completed' => 0,
                    'exercises_total' => 0,
                    'completed_subtopics' => [],
                    'current_streak_days' => 0,
                    'progress_data' => [
                        'interaction' => 0,
                        'code_execution' => 0,
                        'time_spent' => 0,
                        'knowledge_check' => 0
                    ]
                ]
            ]);
        }
        
        // Format the response
        $data = [
            'id' => $progress->id,
            'topic_id' => $progress->topic_id,
            'progress_percentage' => $progress->progress_percentage,
            'status' => $progress->status,
            'time_spent_minutes' => $progress->time_spent_minutes,
            'exercises_completed' => $progress->exercises_completed,
            'exercises_total' => $progress->exercises_total,
            'completed_subtopics' => json_decode($progress->completed_subtopics),
            'current_streak_days' => $progress->current_streak_days,
            'last_interaction_at' => $progress->last_interaction_at,
            'progress_data' => json_decode($progress->progress_data)
        ];

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
