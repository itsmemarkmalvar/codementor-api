<?php
error_reporting(E_ALL); ini_set('display_errors', 1);
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=codementor;charset=utf8mb4','root','');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $prefs = $pdo->query("SELECT id,user_id,session_id,interaction_type,chosen_ai,performance_score,success_rate,time_spent_seconds,attempt_count,topic_id,attribution_model,attribution_confidence,created_at FROM ai_preference_logs ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    $quizzes = $pdo->query("SELECT id,user_id,quiz_id,percentage,passed,attribution_model,attribution_confidence,attribution_delay_sec,created_at FROM quiz_attempts ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

    echo "AI Preference Logs (latest 10):\n";
    foreach ($prefs as $r) { echo json_encode($r, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n"; }
    echo "\nQuiz Attempts (latest 10):\n";
    foreach ($quizzes as $r) { echo json_encode($r, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n"; }
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
}
?>
