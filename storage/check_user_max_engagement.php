<?php
// Lightweight diagnostic to inspect max engagement per user and per session
// Usage:
//   php storage\\check_user_max_engagement.php            # Top 10 sessions by engagement
//   php storage\\check_user_max_engagement.php  <userId>   # Summary + sessions for a user

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';

/** @var \Illuminate\Contracts\Console\Kernel $kernel */
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

/** @var \Illuminate\\Database\\Eloquent\\Model $sessionModel */
/** @var \Illuminate\\Database\\Eloquent\\Model $userModel */
/** @var \Illuminate\\Database\\Eloquent\\Model $lessonPlanModel */

/** @noinspection PhpUndefinedClassInspection */
$sessionModel = new \App\Models\SplitScreenSession();
$userModel = new \App\Models\User();
$lessonPlanModel = new \App\Models\LessonPlan();

$args = $_SERVER['argv'] ?? [];
$userId = $args[1] ?? null;

if ($userId) {
    $user = $userModel->find($userId);
    if (!$user) {
        fwrite(STDERR, "User {$userId} not found\n");
        exit(1);
    }
    echo "=== Max Engagement for User #{$user->id} ({$user->email}) ===\n";
    $maxSession = $sessionModel->where('user_id', $user->id)
        ->orderByDesc('engagement_score')
        ->first();
    if (!$maxSession) {
        echo "No sessions found for this user.\n";
        exit(0);
    }
    $lesson = $lessonPlanModel->find($maxSession->lesson_id);
    echo sprintf(
        "Max Score: %d | Session ID: %d | Lesson: %s (%d) | Updated: %s\n",
        (int) $maxSession->engagement_score,
        (int) $maxSession->id,
        $lesson?->title ?? 'N/A',
        (int) ($lesson?->id ?? 0),
        (string) $maxSession->updated_at
    );
    echo "--- Recent Sessions (top 10 by engagement) ---\n";
    $sessions = $sessionModel->where('user_id', $user->id)
        ->orderByDesc('engagement_score')
        ->limit(10)
        ->get();
    foreach ($sessions as $s) {
        $lp = $lessonPlanModel->find($s->lesson_id);
        printf(
            "#%d | score=%d | quiz=%s | practice=%s | lesson=%s | updated=%s\n",
            (int) $s->id,
            (int) $s->engagement_score,
            $s->quiz_triggered ? 'yes' : 'no',
            $s->practice_triggered ? 'yes' : 'no',
            $lp?->title ?? 'N/A',
            (string) $s->updated_at
        );
    }
    exit(0);
}

echo "=== Top 10 Sessions by Engagement (all users) ===\n";
$top = $sessionModel->orderByDesc('engagement_score')->limit(10)->get();
foreach ($top as $s) {
    $u = $userModel->find($s->user_id);
    $lp = $lessonPlanModel->find($s->lesson_id);
    printf(
        "user=%s | session=%d | score=%d | quiz=%s | practice=%s | lesson=%s | updated=%s\n",
        $u?->email ?? (string) $s->user_id,
        (int) $s->id,
        (int) $s->engagement_score,
        $s->quiz_triggered ? 'yes' : 'no',
        $s->practice_triggered ? 'yes' : 'no',
        $lp?->title ?? 'N/A',
        (string) $s->updated_at
    );
}

// Global aggregates
$maxScore = $sessionModel->max('engagement_score');
$avgScore = round((float) $sessionModel->avg('engagement_score'), 2);
$totalSessions = $sessionModel->count();
echo "--- Aggregates ---\n";
echo "total_sessions={$totalSessions} avg_engagement_score={$avgScore} max_engagement_score={$maxScore}\n";


