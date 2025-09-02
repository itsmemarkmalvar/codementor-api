<?php
/**
 * Test Phase 3: Performance Recording & AI Preference Poll Integration
 * Standalone PHP script to test the complete data flow
 */

echo "🧪 Testing Phase 3: Performance Recording & AI Preference Poll Integration\n";
echo "================================================================\n\n";

// Database connection
$host = 'localhost';
$port = '3306';
$dbname = 'codementor';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Database connection established\n\n";
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 1: Check if required tables exist
echo "1. ✅ Checking Database Schema...\n";
$requiredTables = [
    'split_screen_sessions',
    'quiz_attempts', 
    'practice_attempts',
    'ai_preference_logs',
    'lesson_plans',
    'lesson_quizzes',
    'practice_problems'
];

foreach ($requiredTables as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "   ✓ Table '$table' exists\n";
        } else {
            echo "   ❌ Table '$table' missing\n";
        }
    } catch (PDOException $e) {
        echo "   ❌ Error checking table '$table': " . $e->getMessage() . "\n";
    }
}

// Test 2: Check table structures
echo "\n2. ✅ Checking Table Structures...\n";
$tableStructures = [
    'split_screen_sessions' => ['id', 'user_id', 'lesson_id', 'engagement_score', 'quiz_triggered', 'practice_triggered', 'created_at'],
    'quiz_attempts' => ['id', 'user_id', 'quiz_id', 'score', 'percentage', 'passed', 'time_spent_seconds', 'created_at'],
    'practice_attempts' => ['id', 'user_id', 'problem_id', 'is_correct', 'points_earned', 'complexity_score', 'time_spent_seconds', 'created_at'],
    'ai_preference_logs' => ['id', 'session_id', 'chosen_ai', 'choice_reason', 'interaction_type', 'topic_id', 'performance_score', 'success_rate', 'created_at']
];

foreach ($tableStructures as $table => $expectedColumns) {
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $missingColumns = array_diff($expectedColumns, $columns);
        if (empty($missingColumns)) {
            echo "   ✓ Table '$table' has all required columns\n";
        } else {
            echo "   ⚠️ Table '$table' missing columns: " . implode(', ', $missingColumns) . "\n";
        }
    } catch (PDOException $e) {
        echo "   ❌ Error checking structure of '$table': " . $e->getMessage() . "\n";
    }
}

// Test 3: Check recent data
echo "\n3. ✅ Checking Recent Data...\n";

// Check recent split screen sessions
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM split_screen_sessions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $recentSessions = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   Recent split screen sessions (24h): $recentSessions\n";
} catch (PDOException $e) {
    echo "   ❌ Error checking recent sessions: " . $e->getMessage() . "\n";
}

// Check recent quiz attempts
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM quiz_attempts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $recentQuizzes = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   Recent quiz attempts (24h): $recentQuizzes\n";
} catch (PDOException $e) {
    echo "   ❌ Error checking recent quizzes: " . $e->getMessage() . "\n";
}

// Check recent practice attempts
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM practice_attempts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $recentPractice = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   Recent practice attempts (24h): $recentPractice\n";
} catch (PDOException $e) {
    echo "   ❌ Error checking recent practice: " . $e->getMessage() . "\n";
}

// Check recent AI preferences
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM ai_preference_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $recentPreferences = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   Recent AI preferences (24h): $recentPreferences\n";
} catch (PDOException $e) {
    echo "   ❌ Error checking recent preferences: " . $e->getMessage() . "\n";
}

// Test 4: Check data linking for TICA-E
echo "\n4. ✅ Checking Data Linking for TICA-E...\n";

try {
    // Find sessions with engagement scores >= 30 (quiz threshold)
    $stmt = $pdo->query("
        SELECT id, user_id, engagement_score, quiz_triggered, practice_triggered, created_at 
        FROM split_screen_sessions 
        WHERE engagement_score >= 30 
        ORDER BY created_at DESC 
        LIMIT 3
    ");
    $completeSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($completeSessions) > 0) {
        echo "   Sessions with engagement >= 30:\n";
        foreach ($completeSessions as $session) {
            echo "     Session {$session['id']}:\n";
            
            // Check for quiz attempts
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM quiz_attempts 
                WHERE user_id = ? AND created_at >= ?
            ");
            $stmt->execute([$session['user_id'], $session['created_at']]);
            $quizAttempts = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            echo "       Quiz attempts: $quizAttempts\n";
            
            // Check for practice attempts
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM practice_attempts 
                WHERE user_id = ? AND created_at >= ?
            ");
            $stmt->execute([$session['user_id'], $session['created_at']]);
            $practiceAttempts = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            echo "       Practice attempts: $practiceAttempts\n";
            
            // Check for AI preferences
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM ai_preference_logs 
                WHERE session_id = ?
            ");
            $stmt->execute([$session['id']]);
            $preferences = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            echo "       AI preferences: $preferences\n";
            
            // Check for complete TICA-E data
            if ($quizAttempts > 0 && $preferences > 0) {
                echo "       ✓ Complete TICA-E data available\n";
            } else {
                echo "       ⚠️ Incomplete TICA-E data\n";
            }
        }
    } else {
        echo "   No sessions with engagement >= 30 found\n";
    }
} catch (PDOException $e) {
    echo "   ❌ Error checking data linking: " . $e->getMessage() . "\n";
}

// Test 5: Check threshold logic
echo "\n5. ✅ Checking Threshold Logic...\n";

try {
    // Check sessions that should trigger quiz (30 points)
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM split_screen_sessions 
        WHERE engagement_score >= 30 AND engagement_score < 70
    ");
    $quizTriggerSessions = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   Sessions eligible for quiz trigger (30-69): $quizTriggerSessions\n";
    
    // Check sessions that should trigger practice (70 points)
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM split_screen_sessions 
        WHERE engagement_score >= 70
    ");
    $practiceTriggerSessions = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   Sessions eligible for practice trigger (70+): $practiceTriggerSessions\n";
    
    // Check sessions with both triggers
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM split_screen_sessions 
        WHERE quiz_triggered = 1 AND practice_triggered = 1
    ");
    $bothTriggered = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   Sessions with both triggers: $bothTriggered\n";
    
} catch (PDOException $e) {
    echo "   ❌ Error checking threshold logic: " . $e->getMessage() . "\n";
}

// Test 6: Check API endpoint availability (manual verification)
echo "\n6. ✅ API Endpoint Verification (Manual Check Required)...\n";
echo "   Please verify these endpoints are accessible:\n";
echo "   - POST /api/sessions/{sessionId}/engagement\n";
echo "   - POST /api/quiz-attempts/{attemptId}/submit\n";
echo "   - POST /api/ai-preference-logs\n";
echo "   - GET /api/analytics/models/compare\n";

// Test 7: Check data integrity
echo "\n7. ✅ Checking Data Integrity...\n";

try {
    // Check for orphaned AI preference logs
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM ai_preference_logs al 
        LEFT JOIN split_screen_sessions s ON al.session_id = s.id 
        WHERE s.id IS NULL
    ");
    $orphanedLogs = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   Orphaned AI preference logs: $orphanedLogs\n";
    
    // Check for sessions without engagement data
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM split_screen_sessions 
        WHERE engagement_score IS NULL
    ");
    $sessionsWithoutEngagement = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   Sessions without engagement data: $sessionsWithoutEngagement\n";
    
} catch (PDOException $e) {
    echo "   ❌ Error checking data integrity: " . $e->getMessage() . "\n";
}

echo "\n🎯 Phase 3 Integration Test Complete!\n";
echo "=====================================\n";
echo "If all tests pass, the system is ready for TICA-E analysis.\n";
echo "Any issues found should be addressed before proceeding.\n";
