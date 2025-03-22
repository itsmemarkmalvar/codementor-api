<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\AI\TutorService;
use App\Services\AI\JavaExecutionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TutorController extends Controller
{
    private $tutorService;
    private $javaExecutionService;

    public function __construct(TutorService $tutorService, JavaExecutionService $javaExecutionService)
    {
        $this->tutorService = $tutorService;
        $this->javaExecutionService = $javaExecutionService;
    }

    /**
     * Get AI tutor response for a question
     */
    public function getResponse(Request $request)
    {
        // Log the incoming request data for debugging
        Log::info('TutorController::getResponse - Request data:', [
            'all' => $request->all(),
            'headers' => $request->headers->all()
        ]);

        $validator = Validator::make($request->all(), [
            'question' => 'required|string',
            'preferences' => 'required|array',
            'topic' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            Log::error('TutorController::getResponse - Validation failed:', [
                'errors' => $validator->errors()->toArray()
            ]);
            return response()->json(['error' => $validator->errors()], 400);
        }

        try {
            // Ensure conversation history is an array
            $conversationHistory = $request->input('conversationHistory', []);
            if (!is_array($conversationHistory)) {
                $conversationHistory = [];
            }
            
            // Log the processed data being sent to the service
            Log::info('TutorController::getResponse - Calling TutorService with:', [
                'question' => $request->input('question'),
                'conversationHistory' => $conversationHistory,
                'preferences' => $request->input('preferences'),
                'topic' => $request->input('topic')
            ]);

            $response = $this->tutorService->getResponse(
                $conversationHistory,
                $request->input('question'),
                $request->input('preferences'),
                $request->input('topic')
            );

            Log::info('TutorController::getResponse - Successfully got response from AI');
            return response()->json(['response' => $response]);
        } catch (\Exception $e) {
            Log::error('Error getting AI tutor response: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to get response from AI tutor: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Execute Java code and return the result
     */
    public function executeJavaCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
            'input' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        try {
            $result = $this->javaExecutionService->executeJavaCode(
                $request->input('code'),
                $request->input('input')
            );

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error executing Java code: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to execute Java code: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Evaluate Java code and provide feedback
     */
    public function evaluateCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
            'topic' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        try {
            $feedback = $this->tutorService->evaluateCode(
                $request->input('code'),
                $request->input('topic')
            );

            return response()->json(['feedback' => $feedback]);
        } catch (\Exception $e) {
            Log::error('Error evaluating code: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to evaluate code'], 500);
        }
    }
} 