<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== BACKEND ENGAGEMENT API INTEGRATION TEST ===\n\n";

try {
    // Test 1: Check if SplitScreenSession model has new methods
    echo "1. Testing SplitScreenSession Model Methods...\n";
    
    $session = new \App\Models\SplitScreenSession();
    
    // Test new threshold methods
    if (method_exists($session, 'shouldTriggerQuiz')) {
        echo "✅ shouldTriggerQuiz method exists\n";
    } else {
        echo "❌ shouldTriggerQuiz method missing\n";
    }
    
    if (method_exists($session, 'shouldTriggerPractice')) {
        echo "✅ shouldTriggerPractice method exists\n";
    } else {
        echo "❌ shouldTriggerPractice method missing\n";
    }
    
    if (method_exists($session, 'getThresholdStatus')) {
        echo "✅ getThresholdStatus method exists\n";
    } else {
        echo "❌ getThresholdStatus method missing\n";
    }
    
    // Test 2: Check database schema
    echo "\n2. Testing Database Schema...\n";
    
    $columns = DB::select('DESCRIBE split_screen_sessions');
    $requiredColumns = ['engagement_score', 'quiz_triggered', 'practice_triggered'];
    
    foreach ($requiredColumns as $col) {
        $found = false;
        foreach ($columns as $column) {
            if ($column->Field === $col) {
                echo "✅ Column '{$col}' exists ({$column->Type})\n";
                $found = true;
                break;
            }
        }
        if (!$found) {
            echo "❌ Column '{$col}' missing\n";
        }
    }
    
    // Test 3: Check API routes
    echo "\n3. Testing API Routes...\n";
    
    $routes = Route::getRoutes();
    $engagementRoutes = [
        'POST /api/sessions/{sessionId}/engagement',
        'GET /api/sessions/{sessionId}/threshold-status'
    ];
    
    foreach ($engagementRoutes as $route) {
        $found = false;
        foreach ($routes as $r) {
            if (strpos($r->uri, 'sessions') !== false && 
                (strpos($r->uri, 'engagement') !== false || strpos($r->uri, 'threshold-status') !== false)) {
                echo "✅ Route found: {$r->methods[0]} {$r->uri}\n";
                $found = true;
                break;
            }
        }
        if (!$found) {
            echo "❌ Route not found: {$route}\n";
        }
    }
    
    // Test 4: Check recent sessions for engagement data
    echo "\n4. Testing Recent Sessions Data...\n";
    
    $recentSessions = DB::table('split_screen_sessions')
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get();
    
    if ($recentSessions->count() > 0) {
        echo "Found {$recentSessions->count()} recent sessions:\n";
        
        foreach ($recentSessions as $session) {
            echo "  Session ID: {$session->id}\n";
            echo "    User ID: {$session->user_id}\n";
            echo "    Engagement Score: {$session->engagement_score}\n";
            echo "    Quiz Triggered: " . ($session->quiz_triggered ? 'Yes' : 'No') . "\n";
            echo "    Practice Triggered: " . ($session->practice_triggered ? 'Yes' : 'No') . "\n";
            echo "    Created: {$session->created_at}\n";
            echo "    ---\n";
        }
    } else {
        echo "No recent sessions found\n";
    }
    
    // Test 5: Test threshold logic with sample data
    echo "\n5. Testing Threshold Logic...\n";
    
    // Create a test session instance
    $testSession = new \App\Models\SplitScreenSession();
    $testSession->engagement_score = 0;
    $testSession->quiz_triggered = false;
    $testSession->practice_triggered = false;
    
    echo "Initial state:\n";
    echo "  Engagement Score: {$testSession->engagement_score}\n";
    echo "  Quiz Triggered: " . ($testSession->quiz_triggered ? 'Yes' : 'No') . "\n";
    echo "  Practice Triggered: " . ($testSession->practice_triggered ? 'Yes' : 'No') . "\n";
    
    // Test quiz threshold (30 points)
    $testSession->engagement_score = 30;
    $shouldTriggerQuiz = $testSession->shouldTriggerQuiz();
    echo "  At 30 points - Should trigger quiz: " . ($shouldTriggerQuiz ? 'Yes' : 'No') . "\n";
    
    // Test practice threshold (70 points) without quiz
    $testSession->engagement_score = 70;
    $shouldTriggerPractice = $testSession->shouldTriggerPractice();
    echo "  At 70 points without quiz - Should trigger practice: " . ($shouldTriggerPractice ? 'Yes' : 'No') . "\n";
    
    // Test practice threshold (70 points) with quiz
    $testSession->quiz_triggered = true;
    $shouldTriggerPractice = $testSession->shouldTriggerPractice();
    echo "  At 70 points with quiz - Should trigger practice: " . ($shouldTriggerPractice ? 'Yes' : 'No') . "\n";
    
    // Test threshold status
    $thresholdStatus = $testSession->getThresholdStatus();
    echo "\nThreshold Status:\n";
    echo "  Quiz Threshold: {$thresholdStatus['quiz_threshold']}\n";
    echo "  Practice Threshold: {$thresholdStatus['practice_threshold']}\n";
    echo "  Current Score: {$thresholdStatus['current_score']}\n";
    echo "  Quiz Unlocked: " . ($thresholdStatus['quiz_unlocked'] ? 'Yes' : 'No') . "\n";
    echo "  Practice Unlocked: " . ($thresholdStatus['practice_unlocked'] ? 'Yes' : 'No') . "\n";
    echo "  Points to Quiz: {$thresholdStatus['points_to_quiz']}\n";
    echo "  Points to Practice: {$thresholdStatus['points_to_practice']}\n";
    
    // Test 6: Check if engagement data is being saved
    echo "\n6. Testing Engagement Data Persistence...\n";
    
    $sessionsWithEngagement = DB::table('split_screen_sessions')
        ->where('engagement_score', '>', 0)
        ->count();
    
    echo "Sessions with engagement > 0: {$sessionsWithEngagement}\n";
    
    if ($sessionsWithEngagement > 0) {
        echo "✅ Engagement data is being saved\n";
    } else {
        echo "⚠️  No sessions with engagement data found\n";
        echo "   This might indicate the frontend is not calling the engagement API\n";
    }
    
    // Test 7: Check for threshold triggers in database
    echo "\n7. Testing Threshold Triggers in Database...\n";
    
    $quizTriggered = DB::table('split_screen_sessions')
        ->where('quiz_triggered', true)
        ->count();
    
    $practiceTriggered = DB::table('split_screen_sessions')
        ->where('practice_triggered', true)
        ->count();
    
    echo "Sessions with quiz triggered: {$quizTriggered}\n";
    echo "Sessions with practice triggered: {$practiceTriggered}\n";
    
    if ($quizTriggered > 0 || $practiceTriggered > 0) {
        echo "✅ Threshold triggers are working\n";
    } else {
        echo "⚠️  No threshold triggers found\n";
        echo "   This might indicate thresholds haven't been reached yet\n";
    }
    
    echo "\n=== TEST COMPLETED ===\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
