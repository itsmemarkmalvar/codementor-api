<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TWO-STAGE THRESHOLD SYSTEM TEST ===\n\n";

try {
    // Check database connection
    DB::connection()->getPdo();
    echo "✅ Database connection: OK\n\n";
    
    // Test 1: Check if engagement data is being saved
    echo "🧪 TEST 1: Engagement Data Collection\n";
    echo "=====================================\n";
    
    $sessions = DB::table('split_screen_sessions')
        ->select('id', 'user_id', 'engagement_score', 'quiz_triggered', 'practice_triggered', 'created_at')
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get();
    
    echo "Recent sessions:\n";
    foreach ($sessions as $session) {
        echo "  Session {$session->id}: ";
        echo "User {$session->user_id}, ";
        echo "Engagement: {$session->engagement_score}, ";
        echo "Quiz: " . ($session->quiz_triggered ? 'Y' : 'N') . ", ";
        echo "Practice: " . ($session->practice_triggered ? 'Y' : 'N') . "\n";
    }
    
    // Test 2: Check AI preference logs
    echo "\n🧪 TEST 2: AI Preference Data Collection\n";
    echo "========================================\n";
    
    $preferences = DB::table('ai_preference_logs')
        ->select('id', 'user_id', 'chosen_ai', 'interaction_type', 'created_at')
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get();
    
    echo "Recent AI preferences:\n";
    foreach ($preferences as $pref) {
        echo "  Log {$pref->id}: ";
        echo "User {$pref->user_id}, ";
        echo "Model: {$pref->chosen_ai}, ";
        echo "Type: {$pref->interaction_type}\n";
    }
    
    // Test 3: Check practice attempts
    echo "\n🧪 TEST 3: Practice Attempt Data\n";
    echo "================================\n";
    
    $attempts = DB::table('practice_attempts')
        ->select('id', 'user_id', 'is_correct', 'attribution_model', 'created_at')
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get();
    
    echo "Recent practice attempts:\n";
    foreach ($attempts as $attempt) {
        echo "  Attempt {$attempt->id}: ";
        echo "User {$attempt->user_id}, ";
        echo "Correct: " . ($attempt->is_correct ? 'Yes' : 'No') . ", ";
        echo "Model: {$attempt->attribution_model}\n";
    }
    
    // Test 4: Check TICA-E data flow
    echo "\n🧪 TEST 4: TICA-E Data Flow Analysis\n";
    echo "====================================\n";
    
    // Check if we have enough data for TICA-E analysis
    $totalUsers = DB::table('users')->count();
    $totalSessions = DB::table('split_screen_sessions')->count();
    $totalPreferences = DB::table('ai_preference_logs')->count();
    $totalAttempts = DB::table('practice_attempts')->count();
    
    echo "Data availability for TICA-E:\n";
    echo "  Users: {$totalUsers}\n";
    echo "  Sessions: {$totalSessions}\n";
    echo "  AI Preferences: {$totalPreferences}\n";
    echo "  Practice Attempts: {$totalAttempts}\n";
    
    // Test 5: Simulate engagement threshold flow
    echo "\n🧪 TEST 5: Engagement Threshold Flow Simulation\n";
    echo "===============================================\n";
    
    echo "Expected Flow:\n";
    echo "  1. User starts session → engagement tracking begins\n";
    echo "  2. User reaches 30 points → Quiz triggered\n";
    echo "  3. User continues to 70 points → Practice triggered\n";
    echo "  4. Practice completion → AI preference poll\n";
    echo "  5. All data saved → TICA-E analysis ready\n\n";
    
    // Check if we have sessions with proper engagement flow
    $sessionsWithEngagement = DB::table('split_screen_sessions')
        ->where('engagement_score', '>', 0)
        ->count();
    
    $sessionsWithQuiz = DB::table('split_screen_sessions')
        ->where('quiz_triggered', true)
        ->count();
    
    $sessionsWithPractice = DB::table('split_screen_sessions')
        ->where('practice_triggered', true)
        ->count();
    
    echo "Current Engagement Status:\n";
    echo "  Sessions with engagement: {$sessionsWithEngagement}\n";
    echo "  Sessions with quiz triggered: {$sessionsWithQuiz}\n";
    echo "  Sessions with practice triggered: {$sessionsWithPractice}\n";
    
    // Test 6: Check for data integrity issues
    echo "\n🧪 TEST 6: Data Integrity Check\n";
    echo "===============================\n";
    
    $issues = [];
    
    // Check for sessions without engagement data
    $sessionsWithoutEngagement = DB::table('split_screen_sessions')
        ->where('engagement_score', 0)
        ->count();
    
    if ($sessionsWithoutEngagement > 0) {
        $issues[] = "⚠️  {$sessionsWithoutEngagement} sessions have 0 engagement score";
    }
    
    // Check for orphaned preference logs
    $orphanedPreferences = DB::table('ai_preference_logs as a')
        ->leftJoin('split_screen_sessions as s', 'a.session_id', '=', 's.id')
        ->whereNull('s.id')
        ->count();
    
    if ($orphanedPreferences > 0) {
        $issues[] = "⚠️  {$orphanedPreferences} AI preference logs have invalid session_id";
    }
    
    // Check for sessions with quiz but no practice
    $quizWithoutPractice = DB::table('split_screen_sessions')
        ->where('quiz_triggered', true)
        ->where('practice_triggered', false)
        ->count();
    
    if ($quizWithoutPractice > 0) {
        $issues[] = "ℹ️  {$quizWithoutPractice} sessions have quiz but no practice (normal progression)";
    }
    
    if (empty($issues)) {
        echo "✅ No data integrity issues found\n";
    } else {
        echo "Issues found:\n";
        foreach ($issues as $issue) {
            echo "  {$issue}\n";
        }
    }
    
    // Test 7: Recommendations
    echo "\n🧪 TEST 7: System Recommendations\n";
    echo "=================================\n";
    
    if ($sessionsWithEngagement == 0) {
        echo "🚨 CRITICAL: No engagement data being collected!\n";
        echo "   → Frontend engagement tracker not calling backend\n";
        echo "   → Engagement scores not being saved\n";
        echo "   → TICA-E algorithm will fail\n";
    } elseif ($sessionsWithEngagement < 5) {
        echo "⚠️  WARNING: Limited engagement data\n";
        echo "   → Need more user sessions to test thresholds\n";
        echo "   → Consider testing with multiple users\n";
    } else {
        echo "✅ SUCCESS: Engagement data collection working\n";
        echo "   → {$sessionsWithEngagement} sessions have engagement data\n";
        echo "   → Threshold system should work properly\n";
    }
    
    if ($totalPreferences > 0) {
        echo "✅ SUCCESS: AI preference data being collected\n";
        echo "   → {$totalPreferences} preference logs available\n";
        echo "   → TICA-E algorithm has data to analyze\n";
    } else {
        echo "⚠️  WARNING: No AI preference data\n";
        echo "   → Users not completing practice exercises\n";
        echo "   → Or preference polls not working\n";
    }
    
    echo "\n=== TEST COMPLETED ===\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
