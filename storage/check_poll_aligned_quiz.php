<?php
error_reporting(E_ALL); ini_set('display_errors', 1);

// Usage: php storage/check_poll_aligned_quiz.php [user_id] [days]
$userId = isset($argv[1]) ? (int)$argv[1] : null; // null means aggregate all users
$days = isset($argv[2]) ? (int)$argv[2] : 30;
$passThreshold = 70.0; // percent

try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=codementor;charset=utf8mb4','root','');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $params = [':days' => $days];
    $where = "interaction_type = 'quiz' AND created_at >= (NOW() - INTERVAL :days DAY)";
    if ($userId) {
        $where .= " AND user_id = :uid";
        $params[':uid'] = $userId;
    }

    $sql = "SELECT chosen_ai, performance_score, time_spent_seconds FROM ai_preference_logs WHERE $where";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        if ($k === ':days') { $stmt->bindValue($k, (int)$v, PDO::PARAM_INT); }
    }
    if ($userId) { $stmt->bindValue(':uid', (int)$userId, PDO::PARAM_INT); }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $byModel = [
        'gemini' => ['total_attempts'=>0,'passed_attempts'=>0,'sum_score'=>0.0,'sum_time'=>0],
        'together' => ['total_attempts'=>0,'passed_attempts'=>0,'sum_score'=>0.0,'sum_time'=>0],
    ];

    foreach ($rows as $r) {
        $m = $r['chosen_ai'];
        if (!isset($byModel[$m])) continue;
        $score = is_numeric($r['performance_score']) ? (float)$r['performance_score'] : 0.0;
        $time = is_numeric($r['time_spent_seconds']) ? (int)$r['time_spent_seconds'] : 0;
        $byModel[$m]['total_attempts'] += 1;
        $byModel[$m]['sum_score'] += $score;
        $byModel[$m]['sum_time'] += $time;
        if ($score >= $passThreshold) $byModel[$m]['passed_attempts'] += 1;
    }

    $out = [];
    foreach ($byModel as $m => $agg) {
        $n = max(0, (int)$agg['total_attempts']);
        $avgScore = $n > 0 ? round($agg['sum_score'] / $n, 2) : 0.0;
        $passRate = $n > 0 ? round(($agg['passed_attempts'] / $n) * 100, 2) : 0.0;
        $out[$m] = [
            'total_attempts' => $n,
            'passed_attempts' => (int)$agg['passed_attempts'],
            'pass_rate' => $passRate,
            'avg_score' => $avgScore,
            'avg_time_spent_seconds' => $n > 0 ? (int)round($agg['sum_time'] / $n) : 0,
        ];
    }

    echo json_encode([
        'user_id' => $userId,
        'window_days' => $days,
        'pass_threshold' => $passThreshold,
        'preference_model_performance' => $out,
    ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) . "\n";
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
}
?>


