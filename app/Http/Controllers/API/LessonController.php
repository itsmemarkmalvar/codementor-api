<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\LessonPlan;
use App\Models\LessonModule;
use App\Models\LessonExercise;
use App\Models\ModuleProgress;
use App\Models\ExerciseAttempt;
use App\Models\LearningTopic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class LessonController extends Controller
{
    /**
     * Get all lesson plans for a specific topic.
     */
    public function getLessonPlans(Request $request, $topicId)
    {
        try {
            $topic = LearningTopic::findOrFail($topicId);
            $lessonPlans = LessonPlan::where('topic_id', $topicId)
                            ->where('is_published', true)
                            ->orderBy('id')
                            ->get();
            
            return response()->json([
                'status' => 'success',
                'data' => $lessonPlans,
                'topic' => $topic
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting lesson plans: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching lesson plans',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all lesson plans across all topics.
     */
    public function getAllLessonPlans(Request $request)
    {
        try {
            // Log the query we're about to run
            Log::info('Getting all lesson plans with is_published filter');
            
            // Get all lesson plans, without the published filter for debugging
            $allPlans = LessonPlan::all();
            Log::info('Total lesson plans (without is_published filter): ' . $allPlans->count());
            
            // Now get the filtered plans
            $lessonPlans = LessonPlan::where('is_published', true)
                            ->orderBy('id')
                            ->get();
            
            Log::info('Filtered lesson plans (with is_published filter): ' . $lessonPlans->count());
            
            // Attach topic names to each lesson plan
            $lessonPlans->each(function($plan) {
                $topic = LearningTopic::find($plan->topic_id);
                $plan->topic_name = $topic ? $topic->title : 'Unknown';
            });
            
            return response()->json([
                'status' => 'success',
                'data' => $lessonPlans
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting all lesson plans: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching all lesson plans',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific lesson plan with its modules.
     */
    public function getLessonPlan(Request $request, $lessonPlanId)
    {
        try {
            $lessonPlan = LessonPlan::with(['modules' => function($query) {
                $query->where('is_published', true)->orderBy('order_index');
            }])->findOrFail($lessonPlanId);
            
            // Also fetch the topic it belongs to
            $topic = LearningTopic::findOrFail($lessonPlan->topic_id);
            
            return response()->json([
                'status' => 'success',
                'data' => $lessonPlan,
                'topic' => $topic
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting lesson plan: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching lesson plan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all modules for a specific lesson plan.
     */
    public function getLessonPlanModules(Request $request, $lessonPlanId)
    {
        try {
            $lessonPlan = LessonPlan::findOrFail($lessonPlanId);
            $modules = LessonModule::where('lesson_plan_id', $lessonPlanId)
                        ->where('is_published', true)
                        ->orderBy('order_index')
                        ->get();
            
            return response()->json([
                'status' => 'success',
                'data' => $modules,
                'lesson_plan' => $lessonPlan
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting lesson plan modules: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching lesson plan modules',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new lesson plan.
     */
    public function createLessonPlan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'topic_id' => 'required|exists:learning_topics,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'learning_objectives' => 'required|string',
            'prerequisites' => 'nullable|string',
            'estimated_minutes' => 'nullable|integer',
            'resources' => 'nullable|array',
            'instructor_notes' => 'nullable|string',
            'difficulty_level' => 'nullable|integer|min:1|max:5',
            'is_published' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $lessonPlan = LessonPlan::create($request->all());
            
            return response()->json([
                'status' => 'success',
                'message' => 'Lesson plan created successfully',
                'data' => $lessonPlan
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating lesson plan: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error creating lesson plan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing lesson plan.
     */
    public function updateLessonPlan(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'topic_id' => 'nullable|exists:learning_topics,id',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'learning_objectives' => 'nullable|string',
            'prerequisites' => 'nullable|string',
            'estimated_minutes' => 'nullable|integer',
            'resources' => 'nullable|array',
            'instructor_notes' => 'nullable|string',
            'difficulty_level' => 'nullable|integer|min:1|max:5',
            'is_published' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $lessonPlan = LessonPlan::findOrFail($id);
            $lessonPlan->update($request->all());
            
            return response()->json([
                'status' => 'success',
                'message' => 'Lesson plan updated successfully',
                'data' => $lessonPlan
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating lesson plan: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error updating lesson plan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific module with its exercises.
     */
    public function getModule(Request $request, $moduleId)
    {
        try {
            $module = LessonModule::with(['exercises' => function($query) {
                $query->orderBy('order_index');
            }])->findOrFail($moduleId);
            
            // Get user progress if authenticated
            $userProgress = null;
            if (Auth::check()) {
                $userProgress = ModuleProgress::where('user_id', Auth::id())
                                ->where('module_id', $moduleId)
                                ->first();
            }
            
            return response()->json([
                'status' => 'success',
                'data' => $module,
                'user_progress' => $userProgress
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting module: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching module',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all exercises for a specific module.
     */
    public function getModuleExercises(Request $request, $moduleId)
    {
        try {
            $module = LessonModule::findOrFail($moduleId);
            $exercises = LessonExercise::where('module_id', $moduleId)
                        ->orderBy('order_index')
                        ->get();
            
            return response()->json([
                'status' => 'success',
                'data' => $exercises,
                'module' => $module
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting module exercises: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching module exercises',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new module for a lesson plan.
     */
    public function createModule(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lesson_plan_id' => 'required|exists:lesson_plans,id',
            'title' => 'required|string|max:255',
            'order_index' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'content' => 'required|string',
            'examples' => 'nullable|string',
            'key_points' => 'nullable|string',
            'guidance_notes' => 'nullable|string',
            'estimated_minutes' => 'nullable|integer',
            'teaching_strategy' => 'nullable|array',
            'common_misconceptions' => 'nullable|array',
            'is_published' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check for duplicate order_index
            $existingModule = LessonModule::where('lesson_plan_id', $request->lesson_plan_id)
                            ->where('order_index', $request->order_index)
                            ->first();
            
            if ($existingModule) {
                // Shift all modules with order_index >= the requested order_index
                LessonModule::where('lesson_plan_id', $request->lesson_plan_id)
                    ->where('order_index', '>=', $request->order_index)
                    ->increment('order_index');
            }
            
            $module = LessonModule::create($request->all());
            
            // Update the modules count in the lesson plan
            $lessonPlan = LessonPlan::find($request->lesson_plan_id);
            $lessonPlan->updateModulesCount();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Module created successfully',
                'data' => $module
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating module: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error creating module',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing module.
     */
    public function updateModule(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'lesson_plan_id' => 'nullable|exists:lesson_plans,id',
            'title' => 'nullable|string|max:255',
            'order_index' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'content' => 'nullable|string',
            'examples' => 'nullable|string',
            'key_points' => 'nullable|string',
            'guidance_notes' => 'nullable|string',
            'estimated_minutes' => 'nullable|integer',
            'teaching_strategy' => 'nullable|array',
            'common_misconceptions' => 'nullable|array',
            'is_published' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $module = LessonModule::findOrFail($id);
            
            // Handle order_index change if it's being updated
            if ($request->has('order_index') && $request->order_index != $module->order_index) {
                $lessonPlanId = $request->lesson_plan_id ?? $module->lesson_plan_id;
                
                // Check for duplicate order_index
                $existingModule = LessonModule::where('lesson_plan_id', $lessonPlanId)
                                ->where('order_index', $request->order_index)
                                ->where('id', '!=', $id)
                                ->first();
                
                if ($existingModule) {
                    // Shift all modules with order_index >= the requested order_index
                    LessonModule::where('lesson_plan_id', $lessonPlanId)
                        ->where('order_index', '>=', $request->order_index)
                        ->increment('order_index');
                }
            }
            
            $module->update($request->all());
            
            return response()->json([
                'status' => 'success',
                'message' => 'Module updated successfully',
                'data' => $module
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating module: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error updating module',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific exercise.
     */
    public function getExercise(Request $request, $exerciseId)
    {
        try {
            $exercise = LessonExercise::findOrFail($exerciseId);
            
            // Get user attempts if authenticated
            $userAttempts = null;
            if (Auth::check()) {
                $userAttempts = ExerciseAttempt::where('user_id', Auth::id())
                                ->where('exercise_id', $exerciseId)
                                ->orderBy('attempt_number')
                                ->get();
            }
            
            return response()->json([
                'status' => 'success',
                'data' => $exercise,
                'user_attempts' => $userAttempts
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting exercise: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching exercise',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new exercise for a module.
     */
    public function createExercise(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'module_id' => 'required|exists:lesson_modules,id',
            'title' => 'required|string|max:255',
            'type' => 'required|in:coding,multiple_choice,fill_in_blank,debugging,code_review',
            'description' => 'required|string',
            'instructions' => 'required|string',
            'starter_code' => 'nullable|string',
            'test_cases' => 'nullable|array',
            'expected_output' => 'nullable|array',
            'hints' => 'nullable|array',
            'solution' => 'nullable|array',
            'difficulty' => 'nullable|integer|min:1|max:5',
            'points' => 'nullable|integer',
            'order_index' => 'required|integer|min:0',
            'is_required' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check for duplicate order_index
            $existingExercise = LessonExercise::where('module_id', $request->module_id)
                            ->where('order_index', $request->order_index)
                            ->first();
            
            if ($existingExercise) {
                // Shift all exercises with order_index >= the requested order_index
                LessonExercise::where('module_id', $request->module_id)
                    ->where('order_index', '>=', $request->order_index)
                    ->increment('order_index');
            }
            
            $exercise = LessonExercise::create($request->all());
            
            return response()->json([
                'status' => 'success',
                'message' => 'Exercise created successfully',
                'data' => $exercise
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating exercise: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error creating exercise',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing exercise.
     */
    public function updateExercise(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'module_id' => 'nullable|exists:lesson_modules,id',
            'title' => 'nullable|string|max:255',
            'type' => 'nullable|in:coding,multiple_choice,fill_in_blank,debugging,code_review',
            'description' => 'nullable|string',
            'instructions' => 'nullable|string',
            'starter_code' => 'nullable|string',
            'test_cases' => 'nullable|array',
            'expected_output' => 'nullable|array',
            'hints' => 'nullable|array',
            'solution' => 'nullable|array',
            'difficulty' => 'nullable|integer|min:1|max:5',
            'points' => 'nullable|integer',
            'order_index' => 'nullable|integer|min:0',
            'is_required' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $exercise = LessonExercise::findOrFail($id);
            
            // Handle order_index change if it's being updated
            if ($request->has('order_index') && $request->order_index != $exercise->order_index) {
                $moduleId = $request->module_id ?? $exercise->module_id;
                
                // Check for duplicate order_index
                $existingExercise = LessonExercise::where('module_id', $moduleId)
                                ->where('order_index', $request->order_index)
                                ->where('id', '!=', $id)
                                ->first();
                
                if ($existingExercise) {
                    // Shift all exercises with order_index >= the requested order_index
                    LessonExercise::where('module_id', $moduleId)
                        ->where('order_index', '>=', $request->order_index)
                        ->increment('order_index');
                }
            }
            
            $exercise->update($request->all());
            
            return response()->json([
                'status' => 'success',
                'message' => 'Exercise updated successfully',
                'data' => $exercise
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating exercise: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error updating exercise',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get the user's progress for a specific lesson plan.
     */
    public function getUserLessonProgress(Request $request, $lessonPlanId)
    {
        try {
            // Allow guest use in demo: fallback to user 1 if not authenticated
            $userId = Auth::id() ?? 1;
            
            // Get the lesson plan with modules
            $lessonPlan = LessonPlan::with(['modules' => function($query) {
                $query->orderBy('order_index');
            }])->findOrFail($lessonPlanId);
            
            // Get progress for each module
            $moduleIds = $lessonPlan->modules->pluck('id')->toArray();
            $moduleProgress = ModuleProgress::where('user_id', $userId)
                            ->whereIn('module_id', $moduleIds)
                            ->get()
                            ->keyBy('module_id');
            
            // Calculate overall lesson plan progress as average of module progress percentages
            $totalModules = count($moduleIds);
            $sumPercent = 0;
            $count = 0;
            foreach ($lessonPlan->modules as $module) {
                $progress = $moduleProgress->get($module->id);
                $sumPercent += $progress ? (int) $progress->progress_percentage : 0;
                $count++;
            }
            $overallPercentage = $count > 0 ? (int) round($sumPercent / $count) : 0;
            
            // Format the progress data
            $formattedProgress = [];
            foreach ($lessonPlan->modules as $module) {
                $progress = $moduleProgress->get($module->id);
                
                $formattedProgress[] = [
                    'module_id' => $module->id,
                    'module_title' => $module->title,
                    'order_index' => $module->order_index,
                    'status' => $progress ? $progress->status : 'not_started',
                    'progress_percentage' => $progress ? $progress->progress_percentage : 0,
                    'started_at' => $progress ? $progress->started_at : null,
                    'completed_at' => $progress ? $progress->completed_at : null,
                    'last_activity_at' => $progress ? $progress->last_activity_at : null,
                ];
            }
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'lesson_plan_id' => $lessonPlan->id,
                    'lesson_plan_title' => $lessonPlan->title,
                    'overall_percentage' => $overallPercentage,
                    'completed_modules' => $moduleProgress->where('status', 'completed')->count(),
                    'total_modules' => $totalModules,
                    'module_progress' => $formattedProgress
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting user lesson progress: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching user lesson progress',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get the user's progress for a specific topic as the average of lesson progresses.
     * TopicProgress = average(LessonProgress_j) for all lessons j in the topic
     */
    public function getUserTopicProgress(Request $request, $topicId)
    {
        try {
            // Allow guest use in demo: fallback to user 1 if not authenticated
            $userId = Auth::id() ?? 1;

            $topic = LearningTopic::findOrFail($topicId);
            $lessonPlans = LessonPlan::where('topic_id', $topicId)
                ->where('is_published', true)
                ->orderBy('id')
                ->get();

            $lessonBreakdown = [];
            $lessonPercents = [];

            foreach ($lessonPlans as $plan) {
                $modules = LessonModule::where('lesson_plan_id', $plan->id)
                    ->where('is_published', true)
                    ->orderBy('order_index')
                    ->get();

                $moduleIds = $modules->pluck('id')->toArray();
                $moduleProgress = ModuleProgress::where('user_id', $userId)
                    ->whereIn('module_id', $moduleIds)
                    ->get()
                    ->keyBy('module_id');

                // LessonProgress = average of module progress percentages
                $sumPercent = 0; $count = 0;
                foreach ($modules as $module) {
                    $progress = $moduleProgress->get($module->id);
                    $sumPercent += $progress ? (int) $progress->progress_percentage : 0;
                    $count++;
                }
                $lessonPercent = $count > 0 ? (int) round($sumPercent / $count) : 0;
                $lessonPercents[] = $lessonPercent;

                $lessonBreakdown[] = [
                    'lesson_plan_id' => $plan->id,
                    'lesson_plan_title' => $plan->title,
                    'overall_percentage' => $lessonPercent,
                    'modules_count' => $count,
                ];
            }

            // TopicProgress = average of lesson progresses
            $topicProgress = count($lessonPercents) > 0
                ? (int) round(array_sum($lessonPercents) / count($lessonPercents))
                : 0;

            return response()->json([
                'status' => 'success',
                'data' => [
                    'topic_id' => $topic->id,
                    'topic_title' => $topic->title,
                    'overall_percentage' => $topicProgress,
                    'lessons_count' => count($lessonPercents),
                    'lesson_breakdown' => $lessonBreakdown,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting user topic progress: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching user topic progress',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Record a struggle point for a user on a specific module.
     */
    public function recordStrugglePoint(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'module_id' => 'required|exists:lesson_modules,id',
            'concept' => 'required|string',
            'details' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = Auth::id();
            
            if (!$userId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            // Get or create module progress
            $moduleProgress = ModuleProgress::firstOrCreate(
                [
                    'user_id' => $userId,
                    'module_id' => $request->module_id
                ],
                [
                    'status' => 'in_progress',
                    'started_at' => now(),
                    'last_activity_at' => now()
                ]
            );
            
            // Add the struggle point
            $moduleProgress->addStrugglePoint($request->concept, $request->details);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Struggle point recorded successfully',
                'data' => $moduleProgress
            ]);
        } catch (\Exception $e) {
            Log::error('Error recording struggle point: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error recording struggle point',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get the next available hint for an exercise.
     */
    public function getNextHint(Request $request, $exerciseId)
    {
        try {
            $userId = Auth::id();
            
            if (!$userId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            $exercise = LessonExercise::findOrFail($exerciseId);
            $nextHint = $exercise->getNextHint($userId);
            
            if (!$nextHint) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No more hints available',
                    'data' => null
                ]);
            }
            
            // Record this hint as used in the user's latest attempt
            $latestAttempt = ExerciseAttempt::where('user_id', $userId)
                            ->where('exercise_id', $exerciseId)
                            ->orderBy('attempt_number', 'desc')
                            ->first();
            
            if ($latestAttempt) {
                $latestAttempt->useHint($nextHint['index']);
            } else {
                // Create a new attempt with this hint used
                $attemptNumber = ExerciseAttempt::getNextAttemptNumber($userId, $exerciseId);
                
                ExerciseAttempt::create([
                    'user_id' => $userId,
                    'exercise_id' => $exerciseId,
                    'attempt_number' => $attemptNumber,
                    'hints_used' => [$nextHint['index']]
                ]);
            }
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'hint' => $nextHint['content'],
                    'hint_number' => $nextHint['index'] + 1,
                    'total_hints' => count($exercise->hints)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting next hint: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error getting next hint',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 