<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\LessonPlan;
use App\Models\LessonModule;
use App\Models\LessonExercise;
use App\Models\ModuleProgress;
use App\Models\ExerciseAttempt;
use App\Models\LearningTopic;
use App\Models\PracticeProblem;
use App\Models\SplitScreenSession;
use App\Models\UserLessonCompletion;
use Laravel\Sanctum\PersonalAccessToken;
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
			$includeUnpublished = $request->boolean('include_unpublished', false);
			$query = LessonPlan::where('topic_id', $topicId);
			if (!$includeUnpublished) {
				$query->where('is_published', true);
			}
			// Enforce a canonical order for Java Basics lessons
			if ($topic->title === 'Java Basics') {
				$sequence = [
					'Java Fundamentals',
					'Java Control Flow',
					'Java Methods in Depth',
					'Java Object-Oriented Programming',
					'Java Exception Handling',
					'Java File I/O',
					'Java Data Structures',
				];
				$quoted = array_map(function ($t) { return "'".str_replace("'", "\\'", $t)."'"; }, $sequence);
				$orderExpr = 'FIELD(title, '.implode(', ', $quoted).')';
				$lessonPlans = $query->orderByRaw($orderExpr)->orderBy('id')->get();
			} else {
				$lessonPlans = $query->orderBy('id')->get();
			}
            
            // Attach lock state based on prerequisites and user completions
            try {
                $userId = Auth::id();
                if ($userId) {
                    $completedLessonIds = \App\Models\UserLessonCompletion::where('user_id', $userId)->pluck('lesson_plan_id')->toArray();
                    foreach ($lessonPlans as $plan) {
                        $reqIds = [];
                        if (!empty($plan->prerequisites)) {
                            $reqIds = array_values(array_filter(array_map('intval', array_map('trim', explode(',', $plan->prerequisites)))));
                        }
                        $missing = array_values(array_diff($reqIds, $completedLessonIds));
                        $plan->is_locked = count($missing) > 0;
                        $plan->missing_prereq_ids = $missing;
                    }
                } else {
                    foreach ($lessonPlans as $plan) {
                        $plan->is_locked = !empty($plan->prerequisites);
                        $plan->missing_prereq_ids = [];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to compute lesson lock state', ['err' => $e->getMessage()]);
            }
            
            // Attach lock state per lesson based on prerequisites and the user's completions
            try {
                // Resolve user id even on public route: try auth, then bearer token
                $userId = Auth::id();
                if (!$userId) {
                    $authHeader = $request->header('Authorization');
                    if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
                        $raw = substr($authHeader, 7);
                        $pat = PersonalAccessToken::findToken($raw);
                        if ($pat) { $userId = (int) $pat->tokenable_id; }
                    }
                }
                if (!$userId) { $userId = 1; }

                // Helper to decide completion when no explicit row exists
                $isCompleted = function (int $planId) use ($userId): bool {
                    // Module-based completion: average progress 100 or all completed
                    $moduleIds = LessonModule::where('lesson_plan_id', $planId)->pluck('id')->toArray();
                    if (!empty($moduleIds)) {
                        $mods = ModuleProgress::where('user_id', $userId)
                            ->whereIn('module_id', $moduleIds)
                            ->get(['progress_percentage','status']);
                        if ($mods->count() > 0) {
                            $avg = (int) round($mods->avg('progress_percentage'));
                            $allCompleted = $mods->every(function ($m) {
                                return (int)($m->progress_percentage ?? 0) >= 100 || ($m->status === 'completed');
                            });
                            if ($avg >= 100 || $allCompleted) { return true; }
                        }
                    }
                    // Engagement-based completion: practice done or score >= 70
                    $sess = SplitScreenSession::where('user_id', $userId)
                        ->where('lesson_id', $planId)
                        ->orderByDesc('updated_at')
                        ->first(['engagement_score','practice_completed']);
                    if ($sess && (((int)($sess->engagement_score ?? 0)) >= 70 || (bool)($sess->practice_completed ?? false))) {
                        return true;
                    }
                    return false;
                };

                $explicitCompletions = UserLessonCompletion::where('user_id', $userId)
                    ->pluck('lesson_plan_id')->toArray();
                $completedSet = array_fill_keys($explicitCompletions, true);

                // Resolve prerequisites tokens → IDs; compute lock
                foreach ($lessonPlans as $plan) {
                    if (!isset($completedSet[$plan->id]) && $isCompleted((int)$plan->id)) {
                        $completedSet[$plan->id] = true;
                    }
                    $reqIds = [];
                    if (!empty($plan->prerequisites)) {
                        $tokens = array_map('trim', explode(',', (string)$plan->prerequisites));
                        foreach ($tokens as $tok) {
                            if ($tok === '') { continue; }
                            if (ctype_digit($tok)) {
                                $reqIds[] = (int)$tok;
                                continue;
                            }
                            // Resolve by exact title first, then prefix match
                            $lp = LessonPlan::where('title', $tok)->first(['id']);
                            if (!$lp) { $lp = LessonPlan::where('title', 'like', $tok.'%')->first(['id']); }
                            if ($lp) { $reqIds[] = (int)$lp->id; }
                        }
                        $reqIds = array_values(array_unique(array_filter($reqIds))); // clean
                    }
                    $missing = [];
                    if (!empty($reqIds)) {
                        foreach ($reqIds as $rid) {
                            if (!isset($completedSet[$rid]) && !$isCompleted((int)$rid)) {
                                $missing[] = (int)$rid;
                            }
                        }
                    }
                    $plan->is_locked = count($missing) > 0;
                    $plan->missing_prereq_ids = $missing;
                }
            } catch (\Throwable $e) {
                Log::warning('Lesson lock computation failed', ['error' => $e->getMessage()]);
            }

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
			$includeUnpublished = $request->boolean('include_unpublished', false);
			Log::info('Getting lesson plans', ['include_unpublished' => $includeUnpublished]);
			
			$base = LessonPlan::query();
			if (!$includeUnpublished) { $base->where('is_published', true); }
			// Prefer a stable order across topics: topic then title, with Java Basics in canonical order
			$lessonPlans = $base->orderBy('topic_id')
				->orderBy('id')
				->get();
            
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
			$includeUnpublished = $request->boolean('include_unpublished', false);
			$lessonPlan = LessonPlan::with(['modules' => function($query) use ($includeUnpublished) {
				if (!$includeUnpublished) {
					$query->where('is_published', true);
				}
				$query->orderBy('order_index');
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
			$includeUnpublished = $request->boolean('include_unpublished', false);
			$modulesQuery = LessonModule::where('lesson_plan_id', $lessonPlanId);
			if (!$includeUnpublished) {
				$modulesQuery->where('is_published', true);
			}
			$modules = $modulesQuery->orderBy('order_index')->get();
            
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
     * Return practice problems related to a module based on topic and keywords.
     */
    public function getRelatedPracticeProblems(Request $request, $moduleId)
    {
        try {
            $module = LessonModule::findOrFail($moduleId);
            $lessonPlan = LessonPlan::findOrFail($module->lesson_plan_id);
            $topic = LearningTopic::find($lessonPlan->topic_id);

            // Build keyword list from module title and key points
            $keywords = [];
            $titleWords = preg_split('/\W+/', strtolower($module->title));
            foreach ($titleWords as $w) {
                if (strlen($w) >= 3) { $keywords[] = $w; }
            }
            if (!empty($module->key_points)) {
                $kp = is_string($module->key_points) ? json_decode($module->key_points, true) : $module->key_points;
                if (is_array($kp)) {
                    foreach ($kp as $kpItem) {
                        $pieces = preg_split('/\W+/', strtolower((string) $kpItem));
                        foreach ($pieces as $p) { if (strlen($p) >= 3) { $keywords[] = $p; } }
                    }
                }
            }
            if ($topic && $topic->title) {
                $keywords[] = strtolower($topic->title);
            }

            // Deduplicate
            $keywords = array_values(array_unique(array_filter($keywords)));

            // Query practice problems matching any keyword in title/description/topic_tags/learning_concepts
            $query = PracticeProblem::query();
            if (!empty($keywords)) {
                $query->where(function($q) use ($keywords) {
                    foreach ($keywords as $kw) {
                        $q->orWhere('title', 'like', "%$kw%")
                          ->orWhere('description', 'like', "%$kw%")
                          ->orWhere('learning_concepts', 'like', "%$kw%")
                          ->orWhereJsonContains('topic_tags', $kw);
                    }
                });
            }

            $problems = $query
                ->orderByDesc('success_rate')
                ->limit(8)
                ->get(['id','title','difficulty_level','points','success_rate','topic_tags']);

            return response()->json([
                'status' => 'success',
                'data' => $problems,
                'keywords' => $keywords,
                'topic' => $topic ? ($topic->title ?? null) : null,
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting related practice problems: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching related practice problems',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the authenticated user's progress for a specific module.
     * Endpoint used by FE when user clicks "Mark as complete" or progresses within a module.
     * Body: { status: 'not_started'|'in_progress'|'completed', time_spent_minutes?: int }
     */
    public function updateModuleProgress(Request $request, $moduleId)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:not_started,in_progress,completed',
            'time_spent_minutes' => 'nullable|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
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

            // Ensure module exists
            $module = LessonModule::findOrFail($moduleId);

            // Get or create progress row
            $progress = ModuleProgress::firstOrCreate(
                [ 'user_id' => $userId, 'module_id' => (int) $moduleId ],
                [ 'status' => 'not_started', 'progress_percentage' => 0, 'started_at' => now() ]
            );

            // Optional time accumulation
            if ($request->filled('time_spent_minutes')) {
                $progress->time_spent_seconds = (int) ($progress->time_spent_seconds ?? 0) + ((int) $request->time_spent_minutes * 60);
            }

            // Update status
            $status = $request->get('status');
            if ($status === 'completed') {
                $progress->markAsCompleted();
            } elseif ($status === 'in_progress') {
                $progress->markAsStarted();
            } else {
                // Reset to not started
                $progress->status = 'not_started';
                $progress->progress_percentage = 0;
                $progress->save();
            }

            // Recompute percentage from exercise completions
            $progress->refresh();
            $progress->updateProgressPercentage();

            return response()->json([
                'status' => 'success',
                'message' => 'Module progress updated',
                'data' => $progress
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating module progress: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error updating module progress',
                'error' => $e->getMessage(),
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
            
            // Calculate module-based overall lesson plan progress as average of module percentages
            $totalModules = count($moduleIds);
            $sumPercent = 0; $count = 0;
            foreach ($lessonPlan->modules as $module) {
                $progress = $moduleProgress->get($module->id);
                $sumPercent += $progress ? (int) $progress->progress_percentage : 0;
                $count++;
            }
            $overallPercentage = $count > 0 ? (int) round($sumPercent / $count) : 0;

            // Engagement-based overall progress (requested behavior): scale engagement to 0-100.
            // We map 70 engagement points (practice threshold) to 100%.
            $engagementOverall = null; $engagementScore = null; $progressSource = 'modules';
            try {
                // Use FQCN to avoid missing imports
                $latestSession = \App\Models\SplitScreenSession::where('user_id', $userId)
                    ->where('lesson_id', (int) $lessonPlanId)
                    ->orderByDesc('updated_at')
                    ->first();

                if ($latestSession) {
                    $engagementScore = (int) ($latestSession->engagement_score ?? 0);
                    // 70 -> 100%, cap at 100
                    $engagementOverall = (int) max(0, min(100, round(($engagementScore / 70) * 100)));
                    $progressSource = 'engagement';
                }
            } catch (\Throwable $e) {
                // Fall back silently if sessions not available
                \Log::warning('Engagement progress lookup failed', ['lessonPlanId' => $lessonPlanId, 'error' => $e->getMessage()]);
            }
            
            // If lesson is complete (modules 100% or engagement-mapped 100%), stamp a completion row
            try {
                $isCompleteByModules = ($overallPercentage >= 100);
                $isCompleteByEngagement = ($engagementOverall !== null && $engagementOverall >= 100);
                if ($isCompleteByModules || $isCompleteByEngagement) {
                    \App\Models\UserLessonCompletion::updateOrCreate(
                        [ 'user_id' => $userId, 'lesson_plan_id' => (int) $lessonPlanId ],
                        [
                            'completed_at' => now(),
                            'source' => $isCompleteByModules && $isCompleteByEngagement ? 'both' : ($isCompleteByModules ? 'modules' : 'engagement')
                        ]
                    );
                }
            } catch (\Throwable $e) {
                \Log::warning('Unable to stamp lesson completion', ['lessonPlanId' => $lessonPlanId, 'userId' => $userId, 'err' => $e->getMessage()]);
            }

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
                    // New: engagement-driven overall percentage and meta
                    'engagement_overall_percentage' => $engagementOverall,
                    'engagement_score' => $engagementScore,
                    'progress_source' => $engagementOverall !== null ? 'engagement' : $progressSource,
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