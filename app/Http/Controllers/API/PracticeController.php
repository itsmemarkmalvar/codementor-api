<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PracticeProblem;
use App\Models\PracticeAttempt;
use App\Models\PracticeCategory;
use App\Models\PracticeResource;
use App\Services\AI\JavaExecutionService;
use App\Services\AI\TutorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class PracticeController extends Controller
{
    protected $javaExecutionService;
    protected $tutorService;

    /**
     * Create a new controller instance.
     */
    public function __construct(JavaExecutionService $javaExecutionService, TutorService $tutorService)
    {
        $this->javaExecutionService = $javaExecutionService;
        $this->tutorService = $tutorService;
    }

    /**
     * Get all practice categories.
     */
    public function getCategories()
    {
        try {
            $categories = PracticeCategory::where('is_active', true)
                ->orderBy('display_order')
                ->get()
                ->map(function ($category) {
                    $problemCounts = $category->getProblemCountsByDifficulty();
                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        'description' => $category->description,
                        'icon' => $category->icon,
                        'color' => $category->color,
                        'problem_counts' => $problemCounts,
                        'total_problems' => array_sum($problemCounts),
                        'required_level' => $category->required_level
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $categories
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching practice categories: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching practice categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get practice problems by category.
     */
    public function getProblemsByCategory(Request $request, $categoryId)
    {
        try {
            $filters = $request->only(['difficulty_level', 'topic_tags']);
            $sort = $request->get('sort', 'difficulty_asc');
            $perPage = $request->get('per_page', 10);
            
            $query = PracticeProblem::where('category_id', $categoryId);
            
            // Apply filters
            if (isset($filters['difficulty_level'])) {
                $query->where('difficulty_level', $filters['difficulty_level']);
            }
            
            if (isset($filters['topic_tags'])) {
                $query->whereJsonContains('topic_tags', $filters['topic_tags']);
            }
            
            // Apply sorting
            switch ($sort) {
                case 'difficulty_asc':
                    $query->orderByRaw("FIELD(difficulty_level, 'beginner', 'easy', 'medium', 'hard', 'expert')");
                    break;
                case 'difficulty_desc':
                    $query->orderByRaw("FIELD(difficulty_level, 'expert', 'hard', 'medium', 'easy', 'beginner')");
                    break;
                case 'popularity':
                    $query->orderBy('attempts_count', 'desc');
                    break;
                case 'success_rate':
                    $query->orderBy('success_rate', 'desc');
                    break;
                default:
                    $query->orderBy('created_at', 'desc');
            }
            
            $problems = $query->paginate($perPage);
            
            // Add user attempt status for each problem if user is authenticated
            if (Auth::check()) {
                $userId = Auth::id();
                $problems->getCollection()->transform(function ($problem) use ($userId) {
                    $bestAttempt = PracticeAttempt::where('user_id', $userId)
                        ->where('problem_id', $problem->id)
                        ->where('is_correct', true)
                        ->orderBy('points_earned', 'desc')
                        ->first();
                    
                    $problem->user_status = $bestAttempt ? 'completed' : 'not_started';
                    $problem->user_points = $bestAttempt ? $bestAttempt->points_earned : 0;
                    
                    return $problem;
                });
            }
            
            return response()->json([
                'status' => 'success',
                'data' => $problems
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching practice problems: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching practice problems',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific practice problem.
     */
    public function getProblem($id)
    {
        try {
            $problem = PracticeProblem::findOrFail($id);
            
            // Add user attempt info if authenticated
            $userId = Auth::id();
            if ($userId) {
                $attempts = PracticeAttempt::where('user_id', $userId)
                    ->where('problem_id', $id)
                    ->orderBy('created_at', 'desc')
                    ->get();
                
                $bestAttempt = $attempts->where('is_correct', true)
                    ->sortByDesc('points_earned')
                    ->first();
                
                $problem->user_attempts = $attempts->count();
                $problem->user_best_score = $bestAttempt ? $bestAttempt->points_earned : 0;
                $problem->user_completed = (bool) $bestAttempt;
                
                // Don't send all hints at once
                if (!$problem->user_completed) {
                    // Only provide first hint for incomplete problems
                    $problem->hints = !empty($problem->hints) ? [$problem->hints[0]] : [];
                }
            }
            
            // Get recommended next problems
            $problem->recommended_problems = $problem->getRecommendedProblems();
            $problem->next_level_problems = $problem->getNextLevelProblems();
            
            // Get associated resources
            $problem->resources = $problem->resources()->orderBy('relevance_score', 'desc')->get();
            
            return response()->json([
                'status' => 'success',
                'data' => $problem
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching practice problem: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching practice problem',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit a solution for a practice problem.
     */
    public function submitSolution(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
            'time_spent_seconds' => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $problem = PracticeProblem::findOrFail($id);
            
            // Get user ID (use 1 for demo if not authenticated)
            $userId = Auth::id() ?? 1;
            
            // Count previous attempts
            $attemptNumber = PracticeAttempt::where('user_id', $userId)
                ->where('problem_id', $id)
                ->count() + 1;
            
            // Execute the submitted code
            $executionResult = $this->javaExecutionService->execute(
                $request->code,
                '',
                $problem->test_cases
            );
            
            // Check if all test cases passed
            $allTestsPassed = true;
            $testResults = [];
            $compilerErrors = [];
            $runtimeErrors = [];
            
            if (isset($executionResult['error'])) {
                $allTestsPassed = false;
                
                // Determine if it's a compiler or runtime error
                if (strpos($executionResult['error'], 'error:') !== false) {
                    $compilerErrors[] = $executionResult['error'];
                } else {
                    $runtimeErrors[] = $executionResult['error'];
                }
            } elseif (isset($executionResult['test_results'])) {
                foreach ($executionResult['test_results'] as $testIndex => $result) {
                    $testResults[] = [
                        'test_case' => $testIndex,
                        'passed' => $result['passed'],
                        'expected' => $result['expected'],
                        'actual' => $result['actual'] ?? null,
                        'error' => $result['error'] ?? null
                    ];
                    
                    if (!$result['passed']) {
                        $allTestsPassed = false;
                    }
                }
            }
            
            // Create an attempt record
            $attempt = PracticeAttempt::create([
                'user_id' => $userId,
                'problem_id' => $id,
                'submitted_code' => $request->code,
                'execution_result' => $executionResult,
                'is_correct' => $allTestsPassed,
                'time_spent_seconds' => $request->time_spent_seconds,
                'attempt_number' => $attemptNumber,
                'compiler_errors' => $compilerErrors,
                'runtime_errors' => $runtimeErrors,
                'test_case_results' => $testResults,
                'execution_time_ms' => $executionResult['execution_time'] ?? 0,
                'status' => 'evaluated'
            ]);
            
            // Calculate points if solution is correct
            if ($allTestsPassed) {
                $points = $attempt->calculateFinalScore();
                $attempt->update(['points_earned' => $points]);
                
                // Update problem statistics
                $problem->completion_count = ($problem->completion_count ?? 0) + 1;
                $problem->attempts_count = ($problem->attempts_count ?? 0) + 1;
                $problem->success_rate = $problem->completion_count / $problem->attempts_count * 100;
                $problem->save();
            } else {
                // Identify struggle points
                $attempt->identifyStrugglePoints();
                
                // Update problem statistics
                $problem->attempts_count = ($problem->attempts_count ?? 0) + 1;
                $problem->success_rate = $problem->completion_count / $problem->attempts_count * 100;
                $problem->save();
            }
            
            // Get AI feedback on the solution
            $feedback = $allTestsPassed ? 
                $this->getSuccessFeedback($problem, $attempt) : 
                $this->getErrorFeedback($problem, $attempt);
            
            $attempt->update(['feedback' => $feedback]);
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'attempt_id' => $attempt->id,
                    'is_correct' => $allTestsPassed,
                    'points_earned' => $attempt->points_earned ?? 0,
                    'test_results' => $testResults,
                    'compiler_errors' => $compilerErrors,
                    'runtime_errors' => $runtimeErrors,
                    'execution_time_ms' => $executionResult['execution_time'] ?? 0,
                    'feedback' => $feedback,
                    'next_problems' => $allTestsPassed ? $problem->getRecommendedProblems() : []
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error submitting practice solution: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error submitting practice solution',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a hint for a practice problem.
     */
    public function getHint(Request $request, $id)
    {
        try {
            $problem = PracticeProblem::findOrFail($id);
            
            // Get user ID (use 1 for demo if not authenticated)
            $userId = Auth::id() ?? 1;
            
            // Get next hint
            $hint = $problem->getProgressiveHint($userId, true);
            
            if (!$hint) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No more hints available'
                ], 404);
            }
            
            // Record hint usage
            $attempt = PracticeAttempt::where('user_id', $userId)
                ->where('problem_id', $id)
                ->orderBy('created_at', 'desc')
                ->first();
            
            if ($attempt) {
                $attempt->addHintUsage($hint['index']);
            } else {
                // Create a new attempt record if none exists
                $attempt = PracticeAttempt::create([
                    'user_id' => $userId,
                    'problem_id' => $id,
                    'submitted_code' => '',
                    'status' => 'started',
                    'hints_used' => [$hint['index']],
                    'last_hint_index' => $hint['index']
                ]);
            }
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'hint' => $hint['content'],
                    'hint_number' => $hint['index'] + 1,
                    'total_hints' => $hint['total_hints'],
                    'points_penalty' => $attempt->calculatePointsReduction(),
                    'has_more_hints' => $hint['has_more_hints']
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting practice hint: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error getting practice hint',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get AI feedback on a successful solution.
     */
    private function getSuccessFeedback($problem, $attempt)
    {
        try {
            $context = [
                'problem_title' => $problem->title,
                'problem_difficulty' => $problem->difficulty_level,
                'user_code' => $attempt->submitted_code,
                'execution_time' => $attempt->execution_time_ms,
                'solution_code' => $problem->solution_code
            ];
            
            $feedback = $this->tutorService->getCodeFeedback(
                $attempt->submitted_code,
                'success',
                $context
            );
            
            return $feedback;
        } catch (\Exception $e) {
            Log::error('Error generating success feedback: ' . $e->getMessage());
            return "Great job on solving this problem! Your solution passed all test cases.";
        }
    }

    /**
     * Get AI feedback on an incorrect solution.
     */
    private function getErrorFeedback($problem, $attempt)
    {
        try {
            $context = [
                'problem_title' => $problem->title,
                'problem_difficulty' => $problem->difficulty_level,
                'user_code' => $attempt->submitted_code,
                'compiler_errors' => $attempt->compiler_errors,
                'runtime_errors' => $attempt->runtime_errors,
                'test_case_results' => $attempt->test_case_results,
                'struggle_points' => $attempt->struggle_points
            ];
            
            $feedback = $this->tutorService->getCodeFeedback(
                $attempt->submitted_code,
                'error',
                $context
            );
            
            return $feedback;
        } catch (\Exception $e) {
            Log::error('Error generating error feedback: ' . $e->getMessage());
            return "Your solution didn't pass all test cases. Review the test results and try again.";
        }
    }

    /**
     * Associate resources with a practice problem.
     *
     * @param Request $request
     * @param int $id Practice problem ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function associateResources(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'resources' => 'required|array',
                'resources.*.id' => 'required|exists:practice_resources,id',
                'resources.*.relevance_score' => 'numeric|min:0|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $problem = PracticeProblem::findOrFail($id);
            $resourceData = collect($request->resources)->keyBy('id')->map(function ($item) {
                return ['relevance_score' => $item['relevance_score'] ?? 50];
            })->toArray();

            // Sync the resources with the pivot table data
            $problem->resources()->sync($resourceData);

            // Return the problem with its associated resources
            return response()->json([
                'status' => 'success',
                'message' => 'Resources associated successfully',
                'data' => [
                    'problem' => $problem,
                    'resources' => $problem->resources
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error associating resources with practice problem: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error associating resources with practice problem',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get resources associated with a practice problem.
     *
     * @param int $id Practice problem ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProblemResources($id)
    {
        try {
            $problem = PracticeProblem::findOrFail($id);
            $resources = $problem->resources()->orderBy('relevance_score', 'desc')->get();

            return response()->json([
                'status' => 'success',
                'data' => $resources
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting resources for practice problem: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error getting resources for practice problem',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get suggested learning resources based on struggle points.
     */
    public function getSuggestedResources(Request $request, $id)
    {
        try {
            // Get user ID (use 1 for demo if not authenticated)
            $userId = Auth::id() ?? 1;
            
            $attempt = PracticeAttempt::where('user_id', $userId)
                ->where('problem_id', $id)
                ->orderBy('created_at', 'desc')
                ->first();
            
            if (!$attempt || empty($attempt->struggle_points)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No struggle points identified'
                ], 404);
            }
            
            // Get related learning resources based on struggle points
            $resources = [];
            
            // Map common struggle points to learning resources
            $resourceMap = [
                'variable_declaration' => [
                    'title' => 'Java Variable Declaration',
                    'description' => 'Learn how to properly declare and initialize variables in Java',
                    'url' => '/dashboard/courses/java-fundamentals/variables',
                    'type' => 'course'
                ],
                'type_conversion' => [
                    'title' => 'Type Conversion in Java',
                    'description' => 'Understanding implicit and explicit type conversions',
                    'url' => '/dashboard/courses/java-fundamentals/type-conversion',
                    'type' => 'course'
                ],
                'null_handling' => [
                    'title' => 'Avoiding NullPointerException',
                    'description' => 'Best practices for handling null values in Java',
                    'url' => '/dashboard/courses/java-fundamentals/null-safety',
                    'type' => 'article'
                ],
                'array_index' => [
                    'title' => 'Working with Arrays in Java',
                    'description' => 'Learn proper array indexing and bounds checking',
                    'url' => '/dashboard/courses/java-fundamentals/arrays',
                    'type' => 'course'
                ],
                'method_return' => [
                    'title' => 'Methods and Return Values',
                    'description' => 'Understanding method signatures and return types',
                    'url' => '/dashboard/courses/java-fundamentals/methods',
                    'type' => 'course'
                ],
                'test_case_understanding' => [
                    'title' => 'Test-Driven Development',
                    'description' => 'Understanding how to interpret test cases',
                    'url' => '/dashboard/courses/java-advanced/testing',
                    'type' => 'article'
                ]
            ];
            
            foreach ($attempt->struggle_points as $point) {
                if (isset($resourceMap[$point])) {
                    $resources[] = $resourceMap[$point];
                }
            }
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'struggle_points' => $attempt->struggle_points,
                    'resources' => $resources
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting suggested resources: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error getting suggested resources',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 