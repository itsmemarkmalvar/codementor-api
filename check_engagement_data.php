<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== ENGAGEMENT DATA AUDIT ===\n\n";

try {
    // Check database connection
    DB::connection()->getPdo();
    echo "✅ Database connection: OK\n\n";
    
    // Check split screen sessions
    $sessions = DB::table('split_screen_sessions')
        ->select('id', 'user_id', 'engagement_score', 'quiz_triggered', 'practice_triggered', 'user_choice', 'created_at')
        ->orderBy('created_at', 'desc')
        ->limit(10)
        ->get();
    
    echo "📊 Split Screen Sessions (Last 10):\n";
    echo "Total sessions: " . DB::table('split_screen_sessions')->count() . "\n\n";
    
    foreach ($sessions as $session) {
        echo "Session {$session->id}:\n";
        echo "  User ID: {$session->user_id}\n";
        echo "  Engagement Score: {$session->engagement_score}\n";
        echo "  Quiz Triggered: " . ($session->quiz_triggered ? 'YES' : 'NO') . "\n";
        echo "  Practice Triggered: " . ($session->practice_triggered ? 'YES' : 'NO') . "\n";
        echo "  User Choice: " . ($session->user_choice ?? 'NONE') . "\n";
        echo "  Created: {$session->created_at}\n";
        echo "  ---\n";
    }
    
    // Check AI preference logs
    $prefLogs = DB::table('ai_preference_logs')->count();
    echo "\n📈 AI Preference Logs: {$prefLogs} total entries\n";
    
    // Check recent preference logs
    $recentPrefs = DB::table('ai_preference_logs')
        ->select('id', 'chosen_ai', 'interaction_type', 'created_at')
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get();
    
    echo "\nRecent AI Preferences:\n";
    foreach ($recentPrefs as $pref) {
        echo "  {$pref->chosen_ai} - {$pref->interaction_type} ({$pref->created_at})\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
