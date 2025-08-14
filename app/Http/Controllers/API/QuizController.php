<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LessonQuiz;
use App\Models\QuizQuestion;
use App\Models\QuizAttempt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class QuizController extends Controller
{
    /**
     * Get a specific quiz
     */
    public function getQuiz($id)
    {
        try {
            $quiz = LessonQuiz::with(['questions' => function($query) {
                $query->select('id', 'quiz_id', 'question_text', 'type', 'options', 'code_snippet')
                      ->orderBy('order_index');
            }])->findOrFail($id);
            
            return response()->json([
                'quiz' => $quiz
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving quiz: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to retrieve quiz: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get all quizzes for a specific module
     */
    public function getModuleQuizzes($moduleId)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $quizzes = LessonQuiz::where('module_id', $moduleId)
                ->orderBy('difficulty')
                ->orderBy('order_index')
                ->get();

            // Determine highest passed difficulty for this module
            $highestPassed = 0;
            foreach ($quizzes as $q) {
                if ($q->isPassedByUser($user->id)) {
                    $highestPassed = max($highestPassed, (int) ($q->difficulty ?? 0));
                }
            }

            // Compute lock state: only allow difficulty <= highestPassed+1
            $decorated = $quizzes->map(function ($q) use ($user, $highestPassed) {
                $passed = $q->isPassedByUser($user->id);
                $difficulty = (int) ($q->difficulty ?? 0);
                $locked = $difficulty > ($highestPassed + 1);
                return array_merge($q->toArray(), [
                    'passed' => $passed,
                    'locked' => $locked,
                ]);
            });

            return response()->json([
                'quizzes' => $decorated
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving module quizzes: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to retrieve module quizzes: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Start a quiz attempt
     */
    public function startQuizAttempt(Request $request, $id)
    {
        try {
            $quiz = LessonQuiz::findOrFail($id);
            $user = Auth::user();
            
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            // Enforce gating per module: difficulty <= highestPassed+1
            $moduleQuizzes = LessonQuiz::where('module_id', $quiz->module_id)->get();
            $highestPassed = 0;
            foreach ($moduleQuizzes as $q) {
                if ($q->isPassedByUser($user->id)) {
                    $highestPassed = max($highestPassed, (int) ($q->difficulty ?? 0));
                }
            }
            $requestedDifficulty = (int) ($quiz->difficulty ?? 0);
            if ($requestedDifficulty > $highestPassed + 1) {
                return response()->json([
                    'message' => 'Quiz is locked. Please pass the lower difficulty first.'
                ], 403);
            }

            // Check if there's an incomplete attempt
            $existingAttempt = QuizAttempt::where('user_id', $user->id)
                ->where('quiz_id', $id)
                ->whereNull('completed_at')
                ->first();
                
            if ($existingAttempt) {
                return response()->json([
                    'attempt' => $existingAttempt,
                    'message' => 'Continuing existing attempt'
                ]);
            }
            
            // Count previous attempts to set attempt number
            $attemptNumber = QuizAttempt::where('user_id', $user->id)
                ->where('quiz_id', $id)
                ->count() + 1;
            
            // Create new attempt (with optional attribution if provided now)
            $attemptData = [
                'user_id' => $user->id,
                'quiz_id' => $id,
                'question_responses' => json_encode([]),
                'score' => 0,
                'max_possible_score' => $quiz->getMaxScore(),
                'attempt_number' => $attemptNumber
            ];
            try {
                $chatId = $request->input('chat_message_id');
                if ($chatId) {
                    $msg = \App\Models\ChatMessage::where('id', $chatId)->where('user_id', $user->id)->first();
                    if ($msg) {
                        $attemptData['attribution_chat_message_id'] = $msg->id;
                        $attemptData['attribution_model'] = $msg->model;
                        $attemptData['attribution_confidence'] = 'explicit';
                        $attemptData['attribution_delay_sec'] = now()->diffInSeconds(\Carbon\Carbon::parse($msg->created_at));
                    }
                }
            } catch (\Throwable $e) { \Log::warning('Attribution set failed (quiz start): ' . $e->getMessage()); }

            $attempt = QuizAttempt::create($attemptData);
            
            return response()->json([
                'attempt' => $attempt,
                'message' => 'Quiz attempt started'
            ]);
        } catch (\Exception $e) {
            Log::error('Error starting quiz attempt: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to start quiz attempt: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Submit answers for a quiz attempt
     */
    public function submitQuizAttempt(Request $request, $id)
    {
        // Validate first so ValidationException returns 422 gracefully
        try {
            $validated = $request->validate([
                'responses' => 'required|array',
                'time_spent_seconds' => 'nullable|integer|min:0'
            ]);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $ve->errors()
            ], 422);
        }

        try {
            $attempt = QuizAttempt::findOrFail($id);
            $user = Auth::user();

            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }
            
            // Ensure the attempt belongs to the authenticated user
            if ($attempt->user_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized access to quiz attempt'], 403);
            }
            
            $quiz = LessonQuiz::with('questions')->findOrFail($attempt->quiz_id);
            $questions = $quiz->questions;
            $responses = $validated['responses'];
            $score = 0;
            $maxPossibleScore = $quiz->getMaxScore();
            $correctQuestions = [];
            
            // Calculate score
            foreach ($questions as $question) {
                if (isset($responses[$question->id])) {
                    $userResponse = $responses[$question->id];
                    if ($question->isCorrect($userResponse)) {
                        $score += $question->points;
                        $correctQuestions[] = $question->id;
                    }
                }
            }
            
            // Update attempt
            $percentage = $maxPossibleScore > 0 ? ($score / $maxPossibleScore) * 100 : 0;
            $passed = $percentage >= $quiz->passing_score_percent;
            $timeSpent = 0;
            
            if ($request->has('time_spent_seconds')) {
                $timeSpent = $request->time_spent_seconds;
            }
            
            $attempt->question_responses = $responses;
            $attempt->correct_questions = $correctQuestions;
            $attempt->score = $score;
            $attempt->max_possible_score = $maxPossibleScore;
            $attempt->percentage = $percentage;
            $attempt->time_spent_seconds = $validated['time_spent_seconds'] ?? $timeSpent;
            $attempt->passed = $passed;
            $attempt->completed_at = now();
            // If no explicit attribution recorded earlier, attempt temporal attribution on submit
            try {
                if (!$attempt->attribution_chat_message_id) {
                    $msg = \App\Models\ChatMessage::where('user_id', $user->id)
                        ->orderByDesc('created_at')->first();
                    if ($msg && now()->diffInMinutes(\Carbon\Carbon::parse($msg->created_at)) <= 60) {
                        $attempt->attribution_chat_message_id = $msg->id;
                        $attempt->attribution_model = $msg->model;
                        $attempt->attribution_confidence = 'temporal';
                        $attempt->attribution_delay_sec = now()->diffInSeconds(\Carbon\Carbon::parse($msg->created_at));
                    }
                }
            } catch (\Throwable $e) { \Log::warning('Attribution set failed (quiz submit): ' . $e->getMessage()); }

            $attempt->save();
            
            return response()->json([
                'attempt' => $attempt,
                'score' => $score,
                'percentage' => $percentage,
                'passed' => $passed,
                'message' => 'Quiz attempt submitted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error submitting quiz attempt: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to submit quiz attempt: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get a specific quiz attempt
     */
    public function getQuizAttempt($id)
    {
        try {
            $attempt = QuizAttempt::with('quiz.questions')->findOrFail($id);
            $user = Auth::user();
            
            // Ensure the attempt belongs to the authenticated user
            if ($attempt->user_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized access to quiz attempt'], 403);
            }
            
            return response()->json([
                'attempt' => $attempt
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving quiz attempt: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to retrieve quiz attempt: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get all quizzes attempted by the user
     */
    public function getUserQuizzes()
    {
        try {
            $user = Auth::user();
            $attempts = QuizAttempt::with('quiz')
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();
            
            return response()->json([
                'attempts' => $attempts
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving user quizzes: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to retrieve user quizzes: ' . $e->getMessage()], 500);
        }
    }
}