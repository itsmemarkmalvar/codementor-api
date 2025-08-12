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
     * Params: window (e.g., '30d'), k_runs (int), lookahead_min (int)
     */
    public function compareModels(Request $request)
    {
        $userId = Auth::id();
        $window = $request->get('window', '30d');
        $k = (int) $request->get('k_runs', 3);
        $lookahead = (int) $request->get('lookahead_min', 30);

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
        $runs = PracticeAttempt::where('user_id', $userId)
            ->whereBetween('created_at', [$start, $now])
            ->orderBy('created_at')
            ->get(['id','created_at','is_correct','compiler_errors','runtime_errors']);

        $quizzes = QuizAttempt::where('user_id', $userId)
            ->whereBetween('created_at', [$start, $now])
            ->orderBy('created_at')
            ->get(['id','created_at','percentage']);

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

            // Baseline K runs before t
            $prior = $runs->filter(fn($r) => Carbon::parse($r->created_at)->lt($t))->take(-$k);
            $post = $runs->filter(fn($r) => Carbon::parse($r->created_at)->gt($t) && Carbon::parse($r->created_at)->lte($windowEnd));

            $firstPost = $post->first();
            $success1 = $firstPost ? (int) $firstPost->is_correct : 0;

            // Time to first success
            $ttfMin = null;
            foreach ($post as $r) {
                if ($r->is_correct) { $ttfMin = Carbon::parse($r->created_at)->diffInMinutes($t); break; }
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
            $quizAfter = $quizzes->filter(fn($q) => Carbon::parse($q->created_at)->between($t, $t->copy()->addDay()));
            $quizBefore = $quizzes->filter(fn($q) => Carbon::parse($q->created_at)->between($t->copy()->subDays(7), $t));
            $dq = null;
            if ($avg($quizAfter) !== null && $avg($quizBefore) !== null) { $dq = $avg($quizAfter) - $avg($quizBefore); }

            $key = $msg->user_id . '|' . $msg->model;
            $byUserModel[$key]['user_id'] = $msg->user_id;
            $byUserModel[$key]['model'] = $msg->model;
            $byUserModel[$key]['items'][] = [
                'success1' => $success1,
                'ttf_min' => $ttfMin,
                'delta_errors' => $deltaErrors,
                'delta_quiz' => $dq,
                'rating' => $msg->user_rating,
                'fallback' => (int) ($msg->is_fallback ?? 0),
                'latency' => (int) ($msg->response_time_ms ?? 0),
            ];
        }

        // Aggregate per user/model
        $userModelAgg = collect($byUserModel)->map(function($v) {
            $items = collect($v['items']);
            $count = $items->count();
            return [
                'user_id' => $v['user_id'],
                'model' => $v['model'],
                'n' => $count,
                'success1' => $items->avg('success1') ?? 0,
                'ttf_min' => $items->filter(fn($x)=>$x['ttf_min']!==null)->avg('ttf_min'),
                'delta_errors' => $items->avg('delta_errors'),
                'delta_quiz' => $items->filter(fn($x)=>$x['delta_quiz']!==null)->avg('delta_quiz'),
                'rating' => $items->filter(fn($x)=>$x['rating']!==null)->avg('rating'),
                'fallback_rate' => $items->avg('fallback'),
                'latency_ms' => $items->filter(fn($x)=>$x['latency']>0)->avg('latency'),
            ];
        })->values();

        // Pair within user
        $users = $userModelAgg->pluck('user_id')->unique();
        $paired = [];
        foreach ($users as $u) {
            $g = $userModelAgg->firstWhere(fn($x)=>$x['user_id']===$u && $x['model']==='gemini');
            $t = $userModelAgg->firstWhere(fn($x)=>$x['user_id']===$u && $x['model']==='together');
            if ($g && $t) {
                $paired[] = [
                    'user_id' => $u,
                    'd_success1' => ($g['success1'] ?? 0) - ($t['success1'] ?? 0),
                    'd_ttf_min' => ($g['ttf_min'] ?? 0) - ($t['ttf_min'] ?? 0),
                    'd_delta_errors' => ($g['delta_errors'] ?? 0) - ($t['delta_errors'] ?? 0),
                    'd_delta_quiz' => ($g['delta_quiz'] ?? 0) - ($t['delta_quiz'] ?? 0),
                    'd_rating' => ($g['rating'] ?? 0) - ($t['rating'] ?? 0),
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
            return ['n'=>$n,'mean'=>$mu,'sd'=>$sd,'se'=>$se];
        };

        $pairedStats = [
            'success1' => $mean($paired, 'd_success1'),
            'ttf_min' => $mean($paired, 'd_ttf_min'),
            'delta_errors' => $mean($paired, 'd_delta_errors'),
            'delta_quiz' => $mean($paired, 'd_delta_quiz'),
            'rating' => $mean($paired, 'd_rating'),
            'fallback_rate' => $mean($paired, 'd_fallback_rate'),
            'latency_ms' => $mean($paired, 'd_latency_ms'),
        ];

        return response()->json([
            'window' => $window,
            'k_runs' => $k,
            'lookahead_min' => $lookahead,
            'user_model' => $userModelAgg,
            'paired' => $pairedStats,
        ]);
    }
}


