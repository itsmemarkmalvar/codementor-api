<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\PracticeAttempt;
use App\Models\QuizAttempt;
use App\Models\UserProgress;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
     /**
      * TICA-E (Tutor Impact Comparative Algorithm - Extended)
      * 
      * Extends original TICA with poll-based user preference metrics.
      * Combines causal analysis (original TICA) with preference correlation.
      * 
      * Key Extensions:
      * - Poll-driven Success1 calculation
      * - Preference-based rating system
      * - Enhanced error reduction metrics
      * - Multi-source attribution
      * 
      * Compare Gemini vs Together on tutoring impact using hybrid causal-preference analysis.
      * Params:
      *  - window (e.g., '30d')
      *  - k_runs (int) - legacy parameter for backward compatibility
      *  - lookahead_min (int) - legacy parameter for backward compatibility
      *  - topic_id (optional filter)
      *  - difficulty (optional filter: beginner|easy|medium|hard|expert)
      *  - nmin (int, minimum sample size to show stats)
      */
     public function compareModels(Request $request)
    {
        $userId = Auth::id();
        $window = $request->get('window', '30d');
        $k = (int) $request->get('k_runs', 3);
        $lookahead = (int) $request->get('lookahead_min', 30);
         $topicId = $request->get('topic_id');
         $difficulty = $request->get('difficulty');
         $nmin = (int) $request->get('nmin', 5);
         $useAttributionFirst = filter_var($request->get('use_attribution_first', 'true'), FILTER_VALIDATE_BOOLEAN);
         $quizPassThreshold = (int) $request->get('quiz_pass_percent', 70);

        // Window start
        $now = Carbon::now();
        $start = match (true) {
            str_ends_with($window, 'd') => $now->copy()->subDays((int) rtrim($window, 'd')),
            str_ends_with($window, 'w') => $now->copy()->subWeeks((int) rtrim($window, 'w')),
            default => $now->copy()->subDays(30),
        };

        // Load chat messages within window for this user, tagged with model
        $messages = ChatMessage::where('user_id', $userId)
            ->whereNotNull('model')
            ->when($topicId, fn($q) => $q->where('topic_id', $topicId))
            ->whereBetween('created_at', [$start, $now])
            ->orderBy('created_at')
            ->get();

        if ($messages->isEmpty()) {
            return response()->json([
                'window' => $window,
                'k_runs' => $k,
                'lookahead_min' => $lookahead,
                'models' => (object) [],
                'paired' => (object) [],
            ]);
        }

        // Preload practice and quiz attempts for joins
        $runsQuery = PracticeAttempt::where('user_id', $userId)
            ->whereBetween('created_at', [$start, $now])
            ->orderBy('created_at');

        // Optional difficulty filter via related PracticeProblem
        if (!empty($difficulty)) {
            $runsQuery->whereHas('problem', function($q) use ($difficulty) {
                $q->where('difficulty_level', $difficulty);
            });
        }

        $runs = $runsQuery->get(['id','created_at','is_correct','compiler_errors','runtime_errors','attribution_chat_message_id']);

        $quizzes = QuizAttempt::where('user_id', $userId)
            ->whereBetween('created_at', [$start, $now])
            ->orderBy('created_at')
            ->get(['id','created_at','percentage','attribution_chat_message_id']);

        // Load progress snapshots to compute Δprogress around replies (optional; may be sparse)
        $progress = UserProgress::where('user_id', $userId)
            ->when($topicId, fn($q)=>$q->where('topic_id', $topicId))
            ->whereBetween('updated_at', [$start, $now])
            ->orderBy('updated_at')
            ->get(['topic_id','updated_at','progress_percentage']);

        $perReply = [];
        $byUserModel = [];

        foreach ($messages as $idx => $msg) {
            $t = Carbon::parse($msg->created_at);
            $nextT = $messages[$idx + 1]->created_at ?? null;
            $windowEnd = $t->copy()->addMinutes($lookahead);
            if ($nextT) {
                $next = Carbon::parse($nextT);
                if ($next->lessThan($windowEnd)) { $windowEnd = $next; }
            }

            // Attribution-aware linking
            $attrRuns = $runs->filter(fn($r) => $useAttributionFirst && ($r->attribution_chat_message_id === $msg->id));
            $attrQuizzes = $quizzes->filter(fn($q) => $useAttributionFirst && ($q->attribution_chat_message_id === $msg->id));

            // Baseline K runs before t
            $prior = $runs->filter(fn($r) => Carbon::parse($r->created_at)->lt($t))->take(-$k);
            $post = $runs->filter(fn($r) => Carbon::parse($r->created_at)->gt($t) && Carbon::parse($r->created_at)->lte($windowEnd));

            // Determine success1 (composite)
            $firstPost = $attrRuns->first() ?? $post->first();
            $successRun = $firstPost ? (int) $firstPost->is_correct : 0;
            // Quiz pass within lookahead/day (prefer attribution)
            $quizAfter = $attrQuizzes->isNotEmpty()
                ? $attrQuizzes
                : $quizzes->filter(fn($q) => Carbon::parse($q->created_at)->between($t, $t->copy()->addDay()));
            $firstQuizPass = $quizAfter->first(fn($q) => (int) $q->percentage >= $quizPassThreshold);
            $successQuiz = $firstQuizPass ? 1 : 0;
            $success1 = max($successRun, $successQuiz);

            // Time to first success
            $ttfMin = null;
            foreach ($post as $r) {
                if ($r->is_correct) { $ttfMin = Carbon::parse($r->created_at)->diffInMinutes($t); break; }
            }
            // Prefer attribution timing if present
            if ($ttfMin === null && $attrRuns->isNotEmpty()) {
                $firstAttrSuccess = $attrRuns->first(fn($r)=>$r->is_correct);
                if ($firstAttrSuccess) { $ttfMin = Carbon::parse($firstAttrSuccess->created_at)->diffInMinutes($t); }
            }

            // Error reduction using counts length
            $errorCount = function($r) {
                $c = is_array($r->compiler_errors ?? null) ? count($r->compiler_errors) : 0;
                $p = is_array($r->runtime_errors ?? null) ? count($r->runtime_errors) : 0;
                return (int) ($c + $p);
            };
            $errorsPrior = $prior->sum($errorCount);
            $errorsPost = $post->take($k)->sum($errorCount);
            $deltaErrors = $errorsPrior - $errorsPost;

            // Quiz gain
            $avg = function($coll) { return $coll->count() ? $coll->avg('percentage') : null; };
            $quizAfter = $quizAfter; // already computed with attribution preference
            $quizBefore = $quizzes->filter(fn($q) => Carbon::parse($q->created_at)->between($t->copy()->subDays(7), $t));
            $dq = null;
            if ($avg($quizAfter) !== null && $avg($quizBefore) !== null) { $dq = $avg($quizAfter) - $avg($quizBefore); }

            $key = $msg->user_id . '|' . $msg->model;
            $byUserModel[$key]['user_id'] = $msg->user_id;
            $byUserModel[$key]['model'] = $msg->model;
            // Progress delta in lookahead window
            $pBefore = $progress->last(fn($p)=> Carbon::parse($p->updated_at)->lte($t));
            $pAfter = $progress->first(fn($p)=> Carbon::parse($p->updated_at)->gt($t) && Carbon::parse($p->updated_at)->lte($windowEnd));
            $dProgress = null;
            if ($pBefore && $pAfter) { $dProgress = (int)$pAfter->progress_percentage - (int)$pBefore->progress_percentage; }

            $byUserModel[$key]['items'][] = [
                'success1' => $success1,
                'ttf_min' => $ttfMin,
                'delta_errors' => $deltaErrors,
                'delta_quiz' => $dq,
                'delta_progress' => $dProgress,
                'rating' => $msg->user_rating,
                'fallback' => (int) ($msg->is_fallback ?? 0),
                'latency' => (int) ($msg->response_time_ms ?? 0),
            ];
            $perReply[] = [
                'message_id' => $msg->id,
                'model' => $msg->model,
                't' => $t,
                'success1' => $success1,
                'ttf_min' => $ttfMin,
                'delta_errors' => $deltaErrors,
                'delta_quiz' => $dq,
                'delta_progress' => $dProgress,
            ];
        }

        // Aggregate per user/model
        $userModelAgg = collect($byUserModel)->map(function($v) use ($nmin) {
            $items = collect($v['items']);
            $count = $items->count();
            $row = [
                'user_id' => $v['user_id'],
                'model' => $v['model'],
                'n' => $count,
                'success1' => $items->avg('success1') ?? 0,
                'ttf_min' => $items->filter(fn($x)=>$x['ttf_min']!==null)->avg('ttf_min'),
                'delta_errors' => $items->avg('delta_errors'),
                'delta_quiz' => $items->filter(fn($x)=>$x['delta_quiz']!==null)->avg('delta_quiz'),
                'rating' => $items->filter(fn($x)=>$x['rating']!==null)->avg('rating'),
                'delta_progress' => $items->filter(fn($x)=>$x['delta_progress']!==null)->avg('delta_progress'),
                'fallback_rate' => $items->avg('fallback'),
                'latency_ms' => $items->filter(fn($x)=>$x['latency']>0)->avg('latency'),
            ];

            // Suppress metrics if sample size is below threshold
            if ($count < $nmin) {
                $row['success1'] = null;
                $row['ttf_min'] = null;
                $row['delta_errors'] = null;
                $row['delta_quiz'] = null;
                $row['rating'] = null;
                $row['fallback_rate'] = null;
                $row['latency_ms'] = null;
            }

            return $row;
        })->values();



        // Enhanced TICA metrics: Preference rates and clarification requests
        $preferenceRates = $this->calculatePreferenceRates($userId, $start);
        $clarificationMetrics = $this->calculateClarificationMetrics($userId, $start);
        $splitScreenMetrics = $this->calculateSplitScreenMetrics($userId, $start);
        
        // NEW: Practice-based metrics for AI comparison
        $practiceMetrics = $this->calculatePracticeBasedMetrics($userId, $start);
        
        // Transform practice metrics to match expected format
        $practiceBasedUserModel = [];
        foreach (['gemini', 'together'] as $model) {
            $metrics = $practiceMetrics[$model];
            if ($metrics['total_polls'] > 0) {
                $practiceBasedUserModel[] = [
                    'user_id' => $userId,
                    'model' => $model,
                    'n' => $metrics['total_polls'],
                    'success1' => $metrics['next_run_success'],
                    'ttf_min' => $metrics['time_to_fix'],
                    'delta_errors' => $metrics['error_reduction'],
                    'rating' => $metrics['rating'],
                    'fallback_rate' => 0, // Not applicable for practice-based metrics
                    'latency_ms' => null, // Not applicable for practice-based metrics
                    'practice_success' => $metrics['practice_success'],
                    'practice_attempts' => $metrics['practice_attempts']
                ];
            }
        }
        
        // Update paired statistics calculation to use practice-based metrics
        $users = collect($practiceBasedUserModel)->pluck('user_id')->unique();
        $paired = [];
        foreach ($users as $u) {
            $g = collect($practiceBasedUserModel)->first(function ($x) use ($u) {
                return ($x['user_id'] === $u) && ($x['model'] === 'gemini');
            });
            $t = collect($practiceBasedUserModel)->first(function ($x) use ($u) {
                return ($x['user_id'] === $u) && ($x['model'] === 'together');
            });
            if ($g && $t) {
                $paired[] = [
                    'user_id' => $u,
                    'd_success1' => ($g['success1'] ?? 0) - ($t['success1'] ?? 0),
                    'd_ttf_min' => ($g['ttf_min'] ?? 0) - ($t['ttf_min'] ?? 0),
                    'd_delta_errors' => ($g['delta_errors'] ?? 0) - ($t['delta_errors'] ?? 0),
                    'd_rating' => ($g['rating'] ?? 0) - ($t['rating'] ?? 0),
                    'd_practice_success' => ($g['practice_success'] ?? 0) - ($t['practice_success'] ?? 0),
                    'd_practice_attempts' => ($g['practice_attempts'] ?? 0) - ($t['practice_attempts'] ?? 0),
                ];
            }
        }

        $mean = function($arr, $key) {
            $vals = collect($arr)->pluck($key)->filter(fn($v)=>$v!==null)->values();
            $n = $vals->count();
            if ($n===0) return null;
            $mu = $vals->avg();
            $sd = sqrt($vals->reduce(fn($c,$v)=>$c + pow($v-$mu,2), 0) / max(1,$n-1));
            $se = $sd / max(1,sqrt($n));
            // 95% CI
            $ciLow = $mu - 1.96*$se;
            $ciHigh = $mu + 1.96*$se;
            return ['n'=>$n,'mean'=>$mu,'sd'=>$sd,'se'=>$se,'ci_low'=>$ciLow,'ci_high'=>$ciHigh];
        };

        $pairedStats = [
            'success1' => $mean($paired, 'd_success1'),
            'ttf_min' => $mean($paired, 'd_ttf_min'),
            'delta_errors' => $mean($paired, 'd_delta_errors'),
            'rating' => $mean($paired, 'd_rating'),
            'practice_success' => $mean($paired, 'd_practice_success'),
            'practice_attempts' => $mean($paired, 'd_practice_attempts'),
        ];

        // Suppress paired stats when n < nmin
        foreach ($pairedStats as $kStat => $stat) {
            if (is_array($stat) && isset($stat['n']) && $stat['n'] < $nmin) {
                $pairedStats[$kStat]['mean'] = null;
                $pairedStats[$kStat]['ci_low'] = null;
                $pairedStats[$kStat]['ci_high'] = null;
            }
        }

        // Winner rule
        $winner = null;
        $succ = $pairedStats['success1'] ?? null;
        if (is_array($succ) && $succ['mean'] !== null) {
            if ($succ['ci_low'] > 0) { $winner = 'gemini'; }
            elseif ($succ['ci_high'] < 0) { $winner = 'together'; }
        }

        return response()->json([
            'algorithm' => 'TICA-E',
            'algorithm_name' => 'Tutor Impact Comparative Algorithm - Extended',
            'window' => $window,
            'k_runs' => $k,
            'lookahead_min' => $lookahead,
            'nmin' => $nmin,
            'filters' => [
                'topic_id' => $topicId,
                'difficulty' => $difficulty,
            ],
            'params' => [
                'use_attribution_first' => $useAttributionFirst,
                'quiz_pass_percent' => $quizPassThreshold,
            ],
            'user_model' => $practiceBasedUserModel, // Use practice-based metrics instead
            'paired' => $pairedStats,
            'winner' => $winner,
            'per_reply' => $perReply,
            'enhanced_tica' => [
                'preference_rates' => $preferenceRates,
                'clarification_metrics' => $clarificationMetrics,
                'split_screen_metrics' => $splitScreenMetrics,
            ],
        ]);
    }

    /**
     * Calculate AI preference rates from split-screen sessions AND practice polls
     */
    private function calculatePreferenceRates($userId, $startDate)
    {
        // Get split-screen session preferences
        $sessions = \App\Models\SplitScreenSession::where('user_id', $userId)
            ->where('started_at', '>=', $startDate)
            ->whereNotNull('user_choice')
            ->get();

        // Get practice poll preferences
        $practicePolls = \App\Models\AIPreferenceLog::where('user_id', $userId)
            ->where('interaction_type', 'practice')
            ->where('created_at', '>=', $startDate)
            ->get();

        // Get code execution poll preferences (only for ratings)
        $codeExecutionPolls = \App\Models\AIPreferenceLog::where('user_id', $userId)
            ->where('interaction_type', 'code_execution')
            ->where('created_at', '>=', $startDate)
            ->get();

        // Combine all choices
        $sessionChoices = $sessions->pluck('user_choice');
        $pollChoices = $practicePolls->pluck('chosen_ai');
        $codeExecutionChoices = $codeExecutionPolls->pluck('chosen_ai');
        
        $allChoices = $sessionChoices->merge($pollChoices)->merge($codeExecutionChoices);
        $totalChoices = $allChoices->count();

        if ($totalChoices === 0) {
            return [
                'total_choices' => 0,
                'gemini_preference_rate' => 0,
                'together_preference_rate' => 0,
                'both_preference_rate' => 0,
                'neither_preference_rate' => 0,
                'sources' => [
                    'split_screen_sessions' => 0,
                    'practice_polls' => 0
                ]
            ];
        }

        return [
            'total_choices' => $totalChoices,
            'gemini_preference_rate' => round(($allChoices->filter(fn($c) => $c === 'gemini')->count() / $totalChoices) * 100, 2),
            'together_preference_rate' => round(($allChoices->filter(fn($c) => $c === 'together')->count() / $totalChoices) * 100, 2),
            'both_preference_rate' => round(($allChoices->filter(fn($c) => $c === 'both')->count() / $totalChoices) * 100, 2),
            'neither_preference_rate' => round(($allChoices->filter(fn($c) => $c === 'neither')->count() / $totalChoices) * 100, 2),
            'sources' => [
                'split_screen_sessions' => $sessionChoices->count(),
                'practice_polls' => $pollChoices->count(),
                'code_execution_polls' => $codeExecutionChoices->count()
            ]
        ];
    }

    /**
     * Calculate clarification request metrics
     */
    private function calculateClarificationMetrics($userId, $startDate)
    {
        $sessions = \App\Models\SplitScreenSession::where('user_id', $userId)
            ->where('started_at', '>=', $startDate)
            ->get();

        $totalSessions = $sessions->count();
        $clarificationSessions = $sessions->where('clarification_needed', true)->count();

        return [
            'total_sessions' => $totalSessions,
            'clarification_requests' => $clarificationSessions,
            'clarification_rate' => $totalSessions > 0 ? round(($clarificationSessions / $totalSessions) * 100, 2) : 0,
            'avg_session_duration_minutes' => $totalSessions > 0 ? round($sessions->avg('duration_minutes'), 2) : 0,
        ];
    }

    /**
     * Calculate split-screen specific metrics
     */
    private function calculateSplitScreenMetrics($userId, $startDate)
    {
        $sessions = \App\Models\SplitScreenSession::where('user_id', $userId)
            ->where('started_at', '>=', $startDate)
            ->get();

        $totalSessions = $sessions->count();
        if ($totalSessions === 0) {
            return [
                'total_sessions' => 0,
                'avg_engagement_score' => 0,
                'quiz_trigger_rate' => 0,
                'practice_trigger_rate' => 0,
                'engagement_threshold_rate' => 0,
            ];
        }

        return [
            'total_sessions' => $totalSessions,
            'avg_engagement_score' => round($sessions->avg('engagement_score'), 2),
            'quiz_trigger_rate' => round(($sessions->where('quiz_triggered', true)->count() / $totalSessions) * 100, 2),
            'practice_trigger_rate' => round(($sessions->where('practice_triggered', true)->count() / $totalSessions) * 100, 2),
            'engagement_threshold_rate' => round(($sessions->filter(fn($s) => $s->shouldTriggerEngagement())->count() / $totalSessions) * 100, 2),
        ];
    }

    /**
     * TICA-E: Calculate practice-based metrics for AI comparison
     * 
     * Implements the core TICA-E algorithm using poll-based user preference data.
     * 
     * Algorithm Components:
     * - Success1: (SuccessfulPolls_m / TotalPolls_m) × 100
     * - TTF_min: Average(TimeSpent_seconds) / 60
     * - ΔErrors: max(0, 5 - Average(AttemptCount))
     * - Rating: (PollChoices_m / TotalPolls) × 100
     * - PracticeSuccess: Average(SuccessRate_m) / 100
     * 
     * Based on user's AI preference polls and practice performance
     */
    private function calculatePracticeBasedMetrics($userId, $startDate)
    {
        // Get practice polls within the time window
        $practicePolls = \App\Models\AIPreferenceLog::where('user_id', $userId)
            ->where('interaction_type', 'practice')
            ->where('created_at', '>=', $startDate)
            ->get();

        $metrics = [
            'gemini' => [
                'next_run_success' => 0,
                'time_to_fix' => null,
                'error_reduction' => 0,
                'rating' => 0,
                'practice_success' => 0,
                'practice_attempts' => 0,
                'total_polls' => 0,
                'successful_polls' => 0
            ],
            'together' => [
                'next_run_success' => 0,
                'time_to_fix' => null,
                'error_reduction' => 0,
                'rating' => 0,
                'practice_success' => 0,
                'practice_attempts' => 0,
                'total_polls' => 0,
                'successful_polls' => 0
            ]
        ];

        if ($practicePolls->count() === 0) {
            return $metrics;
        }

        $totalPolls = $practicePolls->count();

        // Calculate metrics for each AI
        foreach (['gemini', 'together'] as $ai) {
            $aiPolls = $practicePolls->where('chosen_ai', $ai);
            $aiTotalPolls = $aiPolls->count();
            
            if ($aiTotalPolls === 0) {
                continue;
            }

            // 1. Next run success (based on performance_score > 0)
            $successfulPolls = $aiPolls->where('performance_score', '>', 0)->count();
            $metrics[$ai]['next_run_success'] = $aiTotalPolls > 0 ? ($successfulPolls / $aiTotalPolls) : 0;
            $metrics[$ai]['successful_polls'] = $successfulPolls;

            // 2. Time to fix (average time_spent_seconds in minutes)
            $avgTimeSpent = $aiPolls->avg('time_spent_seconds');
            $metrics[$ai]['time_to_fix'] = $avgTimeSpent ? round($avgTimeSpent / 60, 1) : null;

            // 3. Error reduction (based on attempt_count - lower is better)
            $avgAttempts = $aiPolls->avg('attempt_count');
            // Convert to error reduction score (inverse of attempts)
            $metrics[$ai]['error_reduction'] = $avgAttempts ? max(0, 5 - $avgAttempts) : 0; // 5 attempts = 0 reduction, 1 attempt = 4 reduction

            // 4. Rating (percentage of times user chose this AI)
            $metrics[$ai]['rating'] = $totalPolls > 0 ? ($aiTotalPolls / $totalPolls) : 0;

            // 5. Practice success (success_rate from polls)
            $avgSuccessRate = $aiPolls->avg('success_rate');
            $metrics[$ai]['practice_success'] = $avgSuccessRate ? ($avgSuccessRate / 100) : 0;

            // 6. Practice attempts (total number of attempts for this AI)
            $metrics[$ai]['practice_attempts'] = $aiTotalPolls;

            $metrics[$ai]['total_polls'] = $aiTotalPolls;
        }

        return $metrics;
    }

    /**
     * Get comprehensive AI preference analysis from key interaction points
     * Focuses on the three main areas where users make AI preference choices
     */
    public function getAIPreferenceAnalysis(Request $request)
    {
        $userId = Auth::id();
        $window = $request->get('window', '30d');
        $topicId = $request->get('topic_id');
        $difficulty = $request->get('difficulty');

        // Window start
        $now = Carbon::now();
        $start = match (true) {
            str_ends_with($window, 'd') => $now->copy()->subDays((int) rtrim($window, 'd')),
            str_ends_with($window, 'w') => $now->copy()->subWeeks((int) rtrim($window, 'w')),
            default => $now->copy()->subDays(30),
        };

        // 1. Code Execution Analysis (Solo Room)
        $codeExecutionData = $this->analyzeCodeExecutionPreferences($userId, $start, $topicId);

        // 2. Practice Problem Analysis
        $practiceData = $this->analyzePracticePreferences($userId, $start, $topicId, $difficulty);

        // 3. Quiz Analysis
        $quizData = $this->analyzeQuizPreferences($userId, $start, $topicId, $difficulty);

        // 4. Overall Preference Summary
        $overallSummary = $this->calculateOverallPreferenceSummary($codeExecutionData, $practiceData, $quizData);

        // 5. Performance Correlation Analysis
        $performanceCorrelation = $this->analyzePerformanceCorrelation($userId, $start, $topicId);

        return response()->json([
            'status' => 'success',
            'data' => [
                'window' => $window,
                'filters' => [
                    'topic_id' => $topicId,
                    'difficulty' => $difficulty,
                ],
                'code_execution_analysis' => $codeExecutionData,
                'practice_analysis' => $practiceData,
                'quiz_analysis' => $quizData,
                'overall_summary' => $overallSummary,
                'performance_correlation' => $performanceCorrelation,
            ]
        ]);
    }

    /**
     * Analyze AI preferences from code execution in solo room
     */
    private function analyzeCodeExecutionPreferences($userId, $startDate, $topicId = null)
    {
        // Get preference logs for code execution
        $preferenceLogs = \App\Models\AIPreferenceLog::where('user_id', $userId)
            ->where('interaction_type', 'code_execution')
            ->where('created_at', '>=', $startDate)
            ->when($topicId, fn($q) => $q->where('topic_id', $topicId))
            ->get();

        $totalChoices = $preferenceLogs->count();
        
        // Get code execution attempts with attribution for performance metrics
        $codeExecutions = \App\Models\PracticeAttempt::where('user_id', $userId)
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('attribution_model')
            ->when($topicId, fn($q) => $q->whereHas('problem.category', fn($sq) => $sq->where('topic_id', $topicId)))
            ->get();

        $totalExecutions = $codeExecutions->count();
        $successfulExecutions = $codeExecutions->where('is_correct', true)->count();
        $successRate = $totalExecutions > 0 ? round(($successfulExecutions / $totalExecutions) * 100, 2) : 0;

        // Analyze preferences by success rate
        $preferencesBySuccess = [];
        foreach (['gemini', 'together'] as $model) {
            $modelExecutions = $codeExecutions->where('attribution_model', $model);
            $modelTotal = $modelExecutions->count();
            $modelSuccess = $modelExecutions->where('is_correct', true)->count();
            
            $preferencesBySuccess[$model] = [
                'total_attempts' => $modelTotal,
                'successful_attempts' => $modelSuccess,
                'success_rate' => $modelTotal > 0 ? round(($modelSuccess / $modelTotal) * 100, 2) : 0,
            ];
        }

        // Get user choice preferences from preference logs
        $choices = $preferenceLogs->pluck('chosen_ai');

        return [
            'total_code_executions' => $totalExecutions,
            'overall_success_rate' => $successRate,
            'preferences_by_success' => $preferencesBySuccess,
            'user_choice_breakdown' => [
                'total_choices' => $totalChoices,
                'gemini_preference_rate' => $totalChoices > 0 ? round(($choices->filter(fn($c) => $c === 'gemini')->count() / $totalChoices) * 100, 2) : 0,
                'together_preference_rate' => $totalChoices > 0 ? round(($choices->filter(fn($c) => $c === 'together')->count() / $totalChoices) * 100, 2) : 0,
                'both_preference_rate' => $totalChoices > 0 ? round(($choices->filter(fn($c) => $c === 'both')->count() / $totalChoices) * 100, 2) : 0,
                'neither_preference_rate' => $totalChoices > 0 ? round(($choices->filter(fn($c) => $c === 'neither')->count() / $totalChoices) * 100, 2) : 0,
            ],
            'recent_executions' => $codeExecutions->take(10)->map(function($execution) {
                return [
                    'id' => $execution->id,
                    'created_at' => $execution->created_at,
                    'model' => $execution->attribution_model,
                    'success' => $execution->is_correct,
                    'execution_time_ms' => $execution->execution_time_ms,
                    'compiler_errors' => $execution->compiler_errors,
                    'runtime_errors' => $execution->runtime_errors,
                ];
            }),
        ];
    }

    /**
     * Analyze AI preferences from practice problem completion
     */
    private function analyzePracticePreferences($userId, $startDate, $topicId = null, $difficulty = null)
    {
        // Get preference logs for practice
        $preferenceLogs = \App\Models\AIPreferenceLog::where('user_id', $userId)
            ->where('interaction_type', 'practice')
            ->where('created_at', '>=', $startDate)
            ->when($topicId, fn($q) => $q->where('topic_id', $topicId))
            ->when($difficulty, fn($q) => $q->where('difficulty_level', $difficulty))
            ->get();

        $totalChoices = $preferenceLogs->count();
        
        // Get practice attempts with attribution
        $practiceAttempts = \App\Models\PracticeAttempt::where('user_id', $userId)
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('attribution_model')
            ->when($topicId, fn($q) => $q->whereHas('problem.category', fn($sq) => $sq->where('topic_id', $topicId)))
            ->when($difficulty, fn($q) => $q->whereHas('problem', fn($sq) => $sq->where('difficulty_level', $difficulty)))
            ->get();

        $totalAttempts = $practiceAttempts->count();
        $successfulAttempts = $practiceAttempts->where('is_correct', true)->count();
        $overallSuccessRate = $totalAttempts > 0 ? round(($successfulAttempts / $totalAttempts) * 100, 2) : 0;

        // Analyze by model
        $modelAnalysis = [];
        foreach (['gemini', 'together'] as $model) {
            $modelAttempts = $practiceAttempts->where('attribution_model', $model);
            $modelTotal = $modelAttempts->count();
            $modelSuccess = $modelAttempts->where('is_correct', true)->count();
            
            $modelAnalysis[$model] = [
                'total_attempts' => $modelTotal,
                'successful_attempts' => $modelSuccess,
                'success_rate' => $modelTotal > 0 ? round(($modelSuccess / $modelTotal) * 100, 2) : 0,
                'avg_execution_time_ms' => $modelTotal > 0 ? round($modelAttempts->avg('execution_time_ms'), 2) : 0,
                'avg_attempts_per_problem' => $modelTotal > 0 ? round($modelAttempts->groupBy('problem_id')->avg(fn($group) => $group->count()), 2) : 0,
            ];
        }

        // Get user choice preferences from preference logs
        $choices = $preferenceLogs->pluck('chosen_ai');

        return [
            'total_practice_attempts' => $totalAttempts,
            'overall_success_rate' => $overallSuccessRate,
            'model_performance' => $modelAnalysis,
            'user_choice_breakdown' => [
                'total_choices' => $totalChoices,
                'gemini_preference_rate' => $totalChoices > 0 ? round(($choices->filter(fn($c) => $c === 'gemini')->count() / $totalChoices) * 100, 2) : 0,
                'together_preference_rate' => $totalChoices > 0 ? round(($choices->filter(fn($c) => $c === 'together')->count() / $totalChoices) * 100, 2) : 0,
                'both_preference_rate' => $totalChoices > 0 ? round(($choices->filter(fn($c) => $c === 'both')->count() / $totalChoices) * 100, 2) : 0,
                'neither_preference_rate' => $totalChoices > 0 ? round(($choices->filter(fn($c) => $c === 'neither')->count() / $totalChoices) * 100, 2) : 0,
            ],
            'difficulty_breakdown' => $practiceAttempts->groupBy('problem.difficulty_level')->map(function($attempts, $difficulty) {
                $total = $attempts->count();
                $success = $attempts->where('is_correct', true)->count();
                return [
                    'total_attempts' => $total,
                    'success_rate' => $total > 0 ? round(($success / $total) * 100, 2) : 0,
                ];
            }),
        ];
    }

    /**
     * Analyze AI preferences from quiz completion
     */
    private function analyzeQuizPreferences($userId, $startDate, $topicId = null, $difficulty = null)
    {
        // Get preference logs for quiz
        $preferenceLogs = \App\Models\AIPreferenceLog::where('user_id', $userId)
            ->where('interaction_type', 'quiz')
            ->where('created_at', '>=', $startDate)
            ->when($topicId, fn($q) => $q->where('topic_id', $topicId))
            ->when($difficulty, fn($q) => $q->where('difficulty_level', $difficulty))
            ->get();

        $totalChoices = $preferenceLogs->count();
        
        // Get quiz attempts with attribution
        $quizAttempts = \App\Models\QuizAttempt::where('user_id', $userId)
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('attribution_model')
            ->when($topicId, fn($q) => $q->whereHas('quiz.module.lessonPlan', fn($sq) => $sq->where('topic_id', $topicId)))
            ->when($difficulty, fn($q) => $q->whereHas('quiz', fn($sq) => $sq->where('difficulty', $difficulty)))
            ->get();

        $totalAttempts = $quizAttempts->count();
        $passedAttempts = $quizAttempts->where('passed', true)->count();
        $overallPassRate = $totalAttempts > 0 ? round(($passedAttempts / $totalAttempts) * 100, 2) : 0;

        // Analyze by model
        $modelAnalysis = [];
        foreach (['gemini', 'together'] as $model) {
            $modelAttempts = $quizAttempts->where('attribution_model', $model);
            $modelTotal = $modelAttempts->count();
            $modelPassed = $modelAttempts->where('passed', true)->count();
            
            $modelAnalysis[$model] = [
                'total_attempts' => $modelTotal,
                'passed_attempts' => $modelPassed,
                'pass_rate' => $modelTotal > 0 ? round(($modelPassed / $modelTotal) * 100, 2) : 0,
                'avg_score' => $modelTotal > 0 ? round($modelAttempts->avg('percentage'), 2) : 0,
                'avg_time_spent_seconds' => $modelTotal > 0 ? round($modelAttempts->avg('time_spent_seconds'), 2) : 0,
            ];
        }

        // Get user choice preferences from preference logs
        $preferenceLogs = \App\Models\AIPreferenceLog::where('user_id', $userId)
            ->where('interaction_type', 'quiz')
            ->where('created_at', '>=', $startDate)
            ->when($topicId, fn($q) => $q->where('topic_id', $topicId))
            ->when($difficulty, fn($q) => $q->where('difficulty_level', $difficulty))
            ->get();

        $choices = $preferenceLogs->pluck('chosen_ai');
        $totalChoices = $choices->count();

        return [
            'total_quiz_attempts' => $totalAttempts,
            'overall_pass_rate' => $overallPassRate,
            'model_performance' => $modelAnalysis,
            'user_choice_breakdown' => [
                'total_choices' => $totalChoices,
                'gemini_preference_rate' => $totalChoices > 0 ? round(($choices->filter(fn($c) => $c === 'gemini')->count() / $totalChoices) * 100, 2) : 0,
                'together_preference_rate' => $totalChoices > 0 ? round(($choices->filter(fn($c) => $c === 'together')->count() / $totalChoices) * 100, 2) : 0,
                'both_preference_rate' => $totalChoices > 0 ? round(($choices->filter(fn($c) => $c === 'both')->count() / $totalChoices) * 100, 2) : 0,
                'neither_preference_rate' => $totalChoices > 0 ? round(($choices->filter(fn($c) => $c === 'neither')->count() / $totalChoices) * 100, 2) : 0,
            ],
            'difficulty_breakdown' => $quizAttempts->groupBy('quiz.difficulty')->map(function($attempts, $difficulty) {
                $total = $attempts->count();
                $passed = $attempts->where('passed', true)->count();
                return [
                    'total_attempts' => $total,
                    'pass_rate' => $total > 0 ? round(($passed / $total) * 100, 2) : 0,
                    'avg_score' => $total > 0 ? round($attempts->avg('percentage'), 2) : 0,
                ];
            }),
        ];
    }

    /**
     * Calculate overall preference summary across all interaction types
     */
    private function calculateOverallPreferenceSummary($codeData, $practiceData, $quizData)
    {
        $allChoices = [];
        
        // Collect all user choices
        if ($codeData['user_choice_breakdown']['total_choices'] > 0) {
            $allChoices['code_execution'] = $codeData['user_choice_breakdown'];
        }
        if ($practiceData['user_choice_breakdown']['total_choices'] > 0) {
            $allChoices['practice'] = $practiceData['user_choice_breakdown'];
        }
        if ($quizData['user_choice_breakdown']['total_choices'] > 0) {
            $allChoices['quiz'] = $quizData['user_choice_breakdown'];
        }

        if (empty($allChoices)) {
            return [
                'total_interactions' => 0,
                'overall_preferences' => [
                    'gemini' => 0,
                    'together' => 0,
                    'both' => 0,
                    'neither' => 0,
                ],
                'preferences_by_interaction_type' => [],
            ];
        }

        // Calculate weighted overall preferences
        $totalInteractions = array_sum(array_column($allChoices, 'total_choices'));
        $overallPreferences = [
            'gemini' => 0,
            'together' => 0,
            'both' => 0,
            'neither' => 0,
        ];

        foreach ($allChoices as $type => $data) {
            $weight = $data['total_choices'] / $totalInteractions;
            $overallPreferences['gemini'] += ($data['gemini_preference_rate'] * $weight);
            $overallPreferences['together'] += ($data['together_preference_rate'] * $weight);
            $overallPreferences['both'] += ($data['both_preference_rate'] * $weight);
            $overallPreferences['neither'] += ($data['neither_preference_rate'] * $weight);
        }

        return [
            'total_interactions' => $totalInteractions,
            'overall_preferences' => array_map('round', $overallPreferences, array_fill(0, 4, 2)),
            'preferences_by_interaction_type' => $allChoices,
        ];
    }

    /**
     * Analyze correlation between AI preference and performance
     */
    private function analyzePerformanceCorrelation($userId, $startDate, $topicId = null)
    {
        // Get all attempts with attribution and user choices
        $practiceAttempts = \App\Models\PracticeAttempt::where('user_id', $userId)
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('attribution_model')
            ->when($topicId, fn($q) => $q->whereHas('problem.category', fn($sq) => $sq->where('topic_id', $topicId)))
            ->get();

        $quizAttempts = \App\Models\QuizAttempt::where('user_id', $userId)
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('attribution_model')
            ->when($topicId, fn($q) => $q->whereHas('quiz.module.lessonPlan', fn($sq) => $sq->where('topic_id', $topicId)))
            ->get();

        // Analyze performance by preference
        $preferencePerformance = [];
        foreach (['gemini', 'together', 'both', 'neither'] as $preference) {
            $preferencePerformance[$preference] = [
                'practice_success_rate' => 0,
                'quiz_pass_rate' => 0,
                'avg_quiz_score' => 0,
                'total_attempts' => 0,
            ];
        }

        // Calculate performance metrics for each preference
        $sessions = \App\Models\SplitScreenSession::where('user_id', $userId)
            ->where('started_at', '>=', $startDate)
            ->whereNotNull('user_choice')
            ->when($topicId, fn($q) => $q->where('topic_id', $topicId))
            ->get();

        foreach ($sessions as $session) {
            $preference = $session->user_choice;
            if (!isset($preferencePerformance[$preference])) continue;

            // Get attempts within this session's timeframe
            $sessionStart = $session->started_at;
            $sessionEnd = $session->ended_at ?? now();

            $sessionPracticeAttempts = $practiceAttempts->filter(function($attempt) use ($sessionStart, $sessionEnd) {
                return $attempt->created_at >= $sessionStart && $attempt->created_at <= $sessionEnd;
            });

            $sessionQuizAttempts = $quizAttempts->filter(function($attempt) use ($sessionStart, $sessionEnd) {
                return $attempt->created_at >= $sessionStart && $attempt->created_at <= $sessionEnd;
            });

            $preferencePerformance[$preference]['total_attempts'] += $sessionPracticeAttempts->count() + $sessionQuizAttempts->count();
            
            if ($sessionPracticeAttempts->count() > 0) {
                $successRate = ($sessionPracticeAttempts->where('is_correct', true)->count() / $sessionPracticeAttempts->count()) * 100;
                $preferencePerformance[$preference]['practice_success_rate'] = round($successRate, 2);
            }

            if ($sessionQuizAttempts->count() > 0) {
                $passRate = ($sessionQuizAttempts->where('passed', true)->count() / $sessionQuizAttempts->count()) * 100;
                $avgScore = $sessionQuizAttempts->avg('percentage');
                $preferencePerformance[$preference]['quiz_pass_rate'] = round($passRate, 2);
                $preferencePerformance[$preference]['avg_quiz_score'] = round($avgScore, 2);
            }
        }

        return $preferencePerformance;
    }

    /**
     * Get enhanced analytics for split-screen sessions
     */
    public function getSplitScreenAnalytics(Request $request)
    {
        $userId = Auth::id();
        $window = $request->get('window', '30d');
        
        // Window start
        $now = Carbon::now();
        $start = match (true) {
            str_ends_with($window, 'd') => $now->copy()->subDays((int) rtrim($window, 'd')),
            str_ends_with($window, 'w') => $now->copy()->subWeeks((int) rtrim($window, 'w')),
            default => $now->copy()->subDays(30),
        };

        $sessions = \App\Models\SplitScreenSession::where('user_id', $userId)
            ->where('started_at', '>=', $start)
            ->with(['topic', 'chatMessages'])
            ->get();

        $totalSessions = $sessions->count();
        
        if ($totalSessions === 0) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'window' => $window,
                    'total_sessions' => 0,
                    'preference_rates' => null,
                    'clarification_metrics' => null,
                    'engagement_metrics' => null,
                    'session_breakdown' => [],
                ]
            ]);
        }

        // Calculate preference rates
        $preferenceRates = $this->calculatePreferenceRates($userId, $start);
        
        // Calculate clarification metrics
        $clarificationMetrics = $this->calculateClarificationMetrics($userId, $start);
        
        // Calculate engagement metrics
        $engagementMetrics = [
            'avg_engagement_score' => round($sessions->avg('engagement_score'), 2),
            'max_engagement_score' => $sessions->max('engagement_score'),
            'engagement_threshold_rate' => round(($sessions->filter(fn($s) => $s->shouldTriggerEngagement())->count() / $totalSessions) * 100, 2),
            'quiz_trigger_rate' => round(($sessions->where('quiz_triggered', true)->count() / $totalSessions) * 100, 2),
            'practice_trigger_rate' => round(($sessions->where('practice_triggered', true)->count() / $totalSessions) * 100, 2),
            'avg_session_duration_minutes' => round($sessions->avg('duration_minutes'), 2),
        ];

        // Session breakdown by topic
        $sessionBreakdown = $sessions->groupBy('topic_id')
            ->map(function ($topicSessions, $topicId) {
                $topic = $topicSessions->first()->topic;
                return [
                    'topic_id' => $topicId,
                    'topic_title' => $topic ? $topic->title : 'Unknown Topic',
                    'session_count' => $topicSessions->count(),
                    'avg_engagement_score' => round($topicSessions->avg('engagement_score'), 2),
                    'preference_breakdown' => $this->calculateTopicPreferenceBreakdown($topicSessions),
                ];
            })
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => [
                'window' => $window,
                'total_sessions' => $totalSessions,
                'preference_rates' => $preferenceRates,
                'clarification_metrics' => $clarificationMetrics,
                'engagement_metrics' => $engagementMetrics,
                'session_breakdown' => $sessionBreakdown,
            ]
        ]);
    }

    /**
     * Calculate preference breakdown for a specific topic
     */
    private function calculateTopicPreferenceBreakdown($sessions)
    {
        $sessionsWithChoices = $sessions->whereNotNull('user_choice');
        $totalChoices = $sessionsWithChoices->count();
        
        if ($totalChoices === 0) {
            return [
                'total_choices' => 0,
                'gemini_count' => 0,
                'together_count' => 0,
                'both_count' => 0,
                'neither_count' => 0,
            ];
        }

        $choices = $sessionsWithChoices->pluck('user_choice');
        
        return [
            'total_choices' => $totalChoices,
            'gemini_count' => $choices->filter(fn($c) => $c === 'gemini')->count(),
            'together_count' => $choices->filter(fn($c) => $c === 'together')->count(),
            'both_count' => $choices->filter(fn($c) => $c === 'both')->count(),
            'neither_count' => $choices->filter(fn($c) => $c === 'neither')->count(),
        ];
    }
}


