<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\LearningTopic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LearningTopicController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Get all topics, ordered by their order field
        $topics = LearningTopic::where('is_active', true)
            ->orderBy('order')
            ->get();
            
        return response()->json([
            'status' => 'success',
            'data' => $topics
        ]);
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
        $topic = LearningTopic::findOrFail($id);
        
        return response()->json([
            'status' => 'success',
            'data' => $topic
        ]);
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
        // Get only root topics (with no parent)
        $rootTopics = LearningTopic::where('parent_id', null)
            ->where('is_active', true)
            ->orderBy('order')
            ->with(['subtopics' => function($query) {
                $query->where('is_active', true)->orderBy('order');
            }])
            ->get();
            
        return response()->json([
            'status' => 'success',
            'data' => $rootTopics
        ]);
    }
}
