<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserProgress;
use Illuminate\Support\Facades\Auth;
use App\Models\LearningTopic;
use Illuminate\Support\Facades\Log;

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
    public function update(Request $request, string $topicId)
    {
        try {
            // Validate request
            $request->validate([
                'progress_percentage' => 'nullable|integer|min:0|max:100',
                'status' => 'nullable|string|in:not_started,in_progress,completed',
                'time_spent_minutes' => 'nullable|integer|min:0',
                'exercises_completed' => 'nullable|integer|min:0',
                'exercises_total' => 'nullable|integer|min:0',
                'completed_subtopics' => 'nullable|array',
                'current_streak_days' => 'nullable|integer|min:0',
                'progress_data' => 'nullable|array',
            ]);
            
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
            
            // Get or create progress record
            $progress = UserProgress::firstOrNew([
                'user_id' => $userId,
                'topic_id' => $topicId
            ]);
            
            // Set initial values if this is a new record
            if (!$progress->exists) {
                $progress->progress_percentage = 0;
                $progress->status = 'not_started';
                $progress->time_spent_minutes = 0;
                $progress->exercises_completed = 0;
                $progress->exercises_total = 0;
                $progress->completed_subtopics = json_encode([]);
                $progress->current_streak_days = 0;
                $progress->last_interaction_at = now();
                $progress->progress_data = json_encode([
                    'interaction' => 0,
                    'code_execution' => 0,
                    'time_spent' => 0,
                    'knowledge_check' => 0
                ]);
                $progress->started_at = now();
            }
            
            // Update the fields from the request
            if ($request->has('progress_percentage')) {
                $progress->progress_percentage = $request->progress_percentage;
            }
            
            if ($request->has('status')) {
                $progress->status = $request->status;
                
                // If status is completed, set progress to 100% and completed_at
                if ($request->status === 'completed' && $progress->completed_at === null) {
                    $progress->progress_percentage = 100;
                    $progress->completed_at = now();
                }
            } else {
                // Auto-set status based on progress
                if ($progress->progress_percentage >= 100) {
                    $progress->status = 'completed';
                    if ($progress->completed_at === null) {
                        $progress->completed_at = now();
                    }
                } elseif ($progress->progress_percentage > 0) {
                    $progress->status = 'in_progress';
                }
            }
            
            if ($request->has('time_spent_minutes')) {
                $progress->time_spent_minutes += $request->time_spent_minutes;
            }
            
            if ($request->has('exercises_completed')) {
                $progress->exercises_completed = $request->exercises_completed;
            }
            
            if ($request->has('exercises_total')) {
                $progress->exercises_total = $request->exercises_total;
            }
            
            if ($request->has('completed_subtopics')) {
                $progress->completed_subtopics = json_encode($request->completed_subtopics);
            }
            
            if ($request->has('current_streak_days')) {
                $progress->current_streak_days = $request->current_streak_days;
            }
            
            if ($request->has('progress_data')) {
                $currentData = json_decode($progress->progress_data, true) ?: [];
                $newData = $request->progress_data;
                
                // Merge the arrays, adding values for keys that exist in both
                foreach ($newData as $key => $value) {
                    if (isset($currentData[$key]) && is_numeric($currentData[$key]) && is_numeric($value)) {
                        $currentData[$key] += $value;
                    } else {
                        $currentData[$key] = $value;
                    }
                }
                
                $progress->progress_data = json_encode($currentData);
            }
            
            // Always update last interaction time
            $progress->last_interaction_at = now();
            
            // Save the progress
            $progress->save();
            
            // Determine if any topics are newly unlocked
            $newlyUnlocked = [];
            
            // Only check for newly unlocked topics if progress is >= 80%
            if ($progress->progress_percentage >= 80) {
                // Find topics that have this topic as a prerequisite
                $dependentTopics = LearningTopic::where('is_active', true)->get()
                    ->filter(function($t) use ($topicId) {
                        if (empty($t->prerequisites)) {
                            return false;
                        }
                        $prereqs = array_filter(array_map('trim', explode(',', $t->prerequisites)));
                        return in_array($topicId, $prereqs);
                    });
                
                foreach ($dependentTopics as $depTopic) {
                    // Check if this topic was previously locked but is now unlocked
                    $wasLocked = true; // Assume it was locked
                    
                    // A topic is unlocked if all prerequisites are completed
                    $prereqIds = array_filter(array_map('trim', explode(',', $depTopic->prerequisites)));
                    
                    if (!empty($prereqIds)) {
                        $prereqProgress = UserProgress::where('user_id', $userId)
                            ->whereIn('topic_id', $prereqIds)
                            ->get();
                            
                        $allCompleted = true;
                        foreach ($prereqIds as $prereqId) {
                            if ($prereqId == $topicId) {
                                // We know this one is completed (>= 80%)
                                continue;
                            }
                            
                            $prereq = $prereqProgress->firstWhere('topic_id', $prereqId);
                            if (!$prereq || $prereq->progress_percentage < 80) {
                                $allCompleted = false;
                                break;
                            }
                        }
                        
                        if ($allCompleted) {
                            $newlyUnlocked[] = [
                                'id' => $depTopic->id,
                                'title' => $depTopic->title
                            ];
                        }
                    }
                }
            }
            
            // Prepare response data
            $responseData = [
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
                'progress_data' => json_decode($progress->progress_data),
                'newly_unlocked_topics' => $newlyUnlocked
            ];
            
            return response()->json([
                'status' => 'success',
                'message' => 'Progress updated successfully',
                'data' => $responseData
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating user progress: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update progress',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    /**
     * Get all topics with their locked/unlocked status for the current user
     * A topic is unlocked if:
     * 1. It has no prerequisites in its prerequisites field, OR
     * 2. All its prerequisites have been completed (progress_percentage >= 80%)
     */
    public function getTopicsWithLockStatus()
    {
        try {
            // Get authenticated user ID or use ID 1 for testing
            $userId = Auth::id() ?? 1;
            
            // Get all active topics
            $topics = LearningTopic::where('is_active', true)
                ->orderBy('order')
                ->get();
                
            // Get all user progress for this user
            $userProgress = UserProgress::where('user_id', $userId)->get()
                ->keyBy('topic_id');
                
            $topicsWithLockStatus = $topics->map(function($topic) use ($userProgress) {
                // Parse prerequisites from the prerequisites field
                // The field is expected to be a string like: "1,2,3" where numbers are topic IDs
                $prerequisites = [];
                if (!empty($topic->prerequisites)) {
                    $prerequisites = explode(',', $topic->prerequisites);
                    $prerequisites = array_map('trim', $prerequisites);
                    $prerequisites = array_filter($prerequisites);
                }
                
                // Check if topic is unlocked
                $isUnlocked = true;
                $prerequisiteStatuses = [];
                
                if (!empty($prerequisites)) {
                    foreach ($prerequisites as $prereqId) {
                        // Get the progress for this prerequisite
                        $progress = $userProgress->get($prereqId);
                        $isPrereqCompleted = false;
                        
                        if ($progress && $progress->progress_percentage >= 80) {
                            $isPrereqCompleted = true;
                        }
                        
                        $prerequisiteStatuses[$prereqId] = [
                            'completed' => $isPrereqCompleted,
                            'progress' => $progress ? $progress->progress_percentage : 0
                        ];
                        
                        // If any prerequisite is not completed, the topic is locked
                        if (!$isPrereqCompleted) {
                            $isUnlocked = false;
                        }
                    }
                }
                
                // Get progress for this topic
                $progress = $userProgress->get($topic->id);
                
                return [
                    'id' => $topic->id,
                    'title' => $topic->title,
                    'description' => $topic->description,
                    'difficulty_level' => $topic->difficulty_level,
                    'order' => $topic->order,
                    'parent_id' => $topic->parent_id,
                    'learning_objectives' => $topic->learning_objectives,
                    'prerequisites' => $prerequisites,
                    'estimated_hours' => $topic->estimated_hours,
                    'icon' => $topic->icon,
                    'is_active' => $topic->is_active,
                    'progress' => $progress ? $progress->progress_percentage : 0,
                    'status' => $progress ? $progress->status : 'not_started',
                    'is_unlocked' => $isUnlocked,
                    'prerequisite_statuses' => $prerequisiteStatuses
                ];
            });
            
            return response()->json([
                'status' => 'success',
                'data' => $topicsWithLockStatus
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting topics with lock status: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve topics with lock status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
