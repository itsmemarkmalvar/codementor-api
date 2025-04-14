<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ChatMessageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $userId = Auth::id();
        $query = ChatMessage::where('user_id', $userId);
        
        // Filter by topic if provided
        if ($request->has('topic_id')) {
            $query->where('topic_id', $request->topic_id);
        }
        
        $messages = $query->orderBy('created_at', 'desc')->paginate(15);
        
        return response()->json([
            'status' => 'success',
            'data' => $messages
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
            'topic' => 'nullable|string',
            'topic_id' => 'nullable|integer|exists:learning_topics,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $message = ChatMessage::create([
            'user_id' => Auth::id(),
            'message' => $request->message,
            'topic' => $request->topic,
            'topic_id' => $request->topic_id,
            'context' => $request->context,
            'conversation_history' => $request->conversation_history,
            'preferences' => $request->preferences,
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $message
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $message = ChatMessage::findOrFail($id);
        
        // Ensure user can only access their own messages
        if ($message->user_id !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access'
            ], 403);
        }
        
        return response()->json([
            'status' => 'success',
            'data' => $message
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $message = ChatMessage::findOrFail($id);
        
        // Ensure user can only update their own messages
        if ($message->user_id !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $message->update([
            'message' => $request->message,
            'context' => $request->context,
            'preferences' => $request->preferences,
        ]);
        
        return response()->json([
            'status' => 'success',
            'data' => $message
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $message = ChatMessage::findOrFail($id);
        
        // Ensure user can only delete their own messages
        if ($message->user_id !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access'
            ], 403);
        }
        
        $message->delete();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Chat message deleted successfully'
        ]);
    }
}
