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
      * Compare Gemini vs Together on tutoring impact.
      * Params:
      *  - window (e.g., '30d')
      *  - k_runs (int)
      *  - lookahead_min (int)
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

        // Pair within user
        $users = $userModelAgg->pluck('user_id')->unique();
        $paired = [];
        foreach ($users as $u) {
            $g = $userModelAgg->first(function ($x) use ($u) {
                return ($x['user_id'] === $u) && ($x['model'] === 'gemini');
            });
            $t = $userModelAgg->first(function ($x) use ($u) {
                return ($x['user_id'] === $u) && ($x['model'] === 'together');
            });
            if ($g && $t) {
                $paired[] = [
                    'user_id' => $u,
                    'd_success1' => ($g['success1'] ?? 0) - ($t['success1'] ?? 0),
                    'd_ttf_min' => ($g['ttf_min'] ?? 0) - ($t['ttf_min'] ?? 0),
                    'd_delta_errors' => ($g['delta_errors'] ?? 0) - ($t['delta_errors'] ?? 0),
                    'd_delta_quiz' => ($g['delta_quiz'] ?? 0) - ($t['delta_quiz'] ?? 0),
                    'd_rating' => ($g['rating'] ?? 0) - ($t['rating'] ?? 0),
                    'd_delta_progress' => ($g['delta_progress'] ?? 0) - ($t['delta_progress'] ?? 0),
                    'd_fallback_rate' => ($g['fallback_rate'] ?? 0) - ($t['fallback_rate'] ?? 0),
                    'd_latency_ms' => ($g['latency_ms'] ?? 0) - ($t['latency_ms'] ?? 0),
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
            'delta_quiz' => $mean($paired, 'd_delta_quiz'),
            'rating' => $mean($paired, 'd_rating'),
            'delta_progress' => $mean($paired, 'd_delta_progress'),
            'fallback_rate' => $mean($paired, 'd_fallback_rate'),
            'latency_ms' => $mean($paired, 'd_latency_ms'),
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
            'user_model' => $userModelAgg,
            'paired' => $pairedStats,
            'winner' => $winner,
            'per_reply' => $perReply,
        ]);
    }
}


