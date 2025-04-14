<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\LearningTopic;
use App\Models\UserProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class LearningTopicController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            // Get authenticated user ID if available
            $userId = Auth::id();
            
            // Get all topics, ordered by their order field
            $topics = LearningTopic::where('is_active', true)
                ->orderBy('order')
                ->get();
                
            // Add lock status if user is authenticated
            if ($userId) {
                $topics = $topics->map(function($topic) use ($userId) {
                    $topic->is_locked = $topic->isLockedForUser($userId);
                    return $topic;
                });
            }
            
            return response()->json([
                'status' => 'success',
                'data' => $topics
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching topics: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve topics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'difficulty_level' => 'nullable|string|in:beginner,intermediate,advanced',
            'order' => 'nullable|integer',
            'parent_id' => 'nullable|exists:learning_topics,id',
            'learning_objectives' => 'nullable|string',
            'prerequisites' => 'nullable|string',
            'estimated_hours' => 'nullable|string',
            'icon' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $topic = LearningTopic::create($request->all());
        
        return response()->json([
            'status' => 'success',
            'message' => 'Topic created successfully',
            'data' => $topic
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $topic = LearningTopic::findOrFail($id);
            
            // Get authenticated user ID if available
            $userId = Auth::id();
            
            // Add lock status if user is authenticated
            if ($userId) {
                $topic->is_locked = $topic->isLockedForUser($userId);
                
                // Add prerequisites details if any
                if (!empty($topic->prerequisites)) {
                    $prerequisiteTopics = $topic->getPrerequisiteTopics();
                    $prereqProgress = UserProgress::where('user_id', $userId)
                        ->whereIn('topic_id', $prerequisiteTopics->pluck('id'))
                        ->get()
                        ->keyBy('topic_id');
                        
                    $prerequisites = $prerequisiteTopics->map(function($prereqTopic) use ($prereqProgress) {
                        $progress = $prereqProgress->get($prereqTopic->id);
                        return [
                            'id' => $prereqTopic->id,
                            'title' => $prereqTopic->title,
                            'progress' => $progress ? $progress->progress_percentage : 0,
                            'completed' => $progress && $progress->progress_percentage >= 80
                        ];
                    });
                    
                    $topic->prerequisite_details = $prerequisites;
                }
            }
            
            return response()->json([
                'status' => 'success',
                'data' => $topic
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching topic: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve topic',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'difficulty_level' => 'nullable|string|in:beginner,intermediate,advanced',
            'order' => 'nullable|integer',
            'parent_id' => 'nullable|exists:learning_topics,id',
            'learning_objectives' => 'nullable|string',
            'prerequisites' => 'nullable|string',
            'estimated_hours' => 'nullable|string',
            'icon' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $topic = LearningTopic::findOrFail($id);
        $topic->update($request->all());
        
        return response()->json([
            'status' => 'success',
            'message' => 'Topic updated successfully',
            'data' => $topic
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $topic = LearningTopic::findOrFail($id);
        $topic->delete();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Topic deleted successfully'
        ]);
    }
    
    /**
     * Get topics hierarchy with parent/child relationships.
     */
    public function hierarchy()
    {
        try {
            // Get authenticated user ID if available
            $userId = Auth::id();
            
            // Get only root topics (with no parent)
            $query = LearningTopic::where('parent_id', null)
                ->where('is_active', true)
                ->orderBy('order');
                
            // Add eager loading for subtopics
            $query->with(['subtopics' => function($query) {
                $query->where('is_active', true)->orderBy('order');
            }]);
            
            $rootTopics = $query->get();
            
            // Add lock status if user is authenticated
            if ($userId) {
                // First get all user progress to reduce database queries
                $allTopicIds = collect();
                $rootTopics->each(function($topic) use (&$allTopicIds) {
                    $allTopicIds->push($topic->id);
                    $topic->subtopics->each(function($subtopic) use (&$allTopicIds) {
                        $allTopicIds->push($subtopic->id);
                    });
                });
                
                $userProgress = UserProgress::where('user_id', $userId)
                    ->whereIn('topic_id', $allTopicIds)
                    ->get()
                    ->keyBy('topic_id');
                
                // Apply lock status to root topics
                $rootTopics = $rootTopics->map(function($topic) use ($userId, $userProgress) {
                    $progress = $userProgress->get($topic->id);
                    $topic->progress = $progress ? $progress->progress_percentage : 0;
                    $topic->is_locked = $topic->isLockedForUser($userId);
                    
                    // Apply lock status to subtopics
                    if ($topic->subtopics) {
                        $topic->subtopics = $topic->subtopics->map(function($subtopic) use ($userId, $userProgress) {
                            $progress = $userProgress->get($subtopic->id);
                            $subtopic->progress = $progress ? $progress->progress_percentage : 0;
                            $subtopic->is_locked = $subtopic->isLockedForUser($userId);
                            return $subtopic;
                        });
                    }
                    
                    return $topic;
                });
            }
            
            return response()->json([
                'status' => 'success',
                'data' => $rootTopics
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching topic hierarchy: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve topic hierarchy',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
