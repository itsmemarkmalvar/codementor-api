<?php
/**
 * Fix Session Management Issues
 * This script addresses lesson_id and split screen session data issues
 */

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Models\SplitScreenSession;
use App\Models\AIPreferenceLog;

echo "=== Session Management Fix ===\n\n";

// Check if lesson_id column exists in split_screen_sessions table
echo "1. Checking SplitScreenSession table structure...\n";
$hasLessonIdColumn = Schema::hasColumn('split_screen_sessions', 'lesson_id');

if (!$hasLessonIdColumn) {
    echo "   ❌ lesson_id column missing - adding it...\n";
    
    Schema::table('split_screen_sessions', function (Blueprint $table) {
        $table->unsignedBigInteger('lesson_id')->nullable()->after('topic_id');
        $table->foreign('lesson_id')->references('id')->on('lesson_modules')->onDelete('set null');
    });
    
    echo "   ✅ lesson_id column added successfully\n";
} else {
    echo "   ✅ lesson_id column already exists\n";
}

// Update existing sessions to extract lesson_id from metadata
echo "\n2. Updating existing sessions with lesson_id...\n";
$sessions = SplitScreenSession::all();
$updatedCount = 0;

foreach ($sessions as $session) {
    $metadata = $session->session_metadata;
    if (is_array($metadata) && isset($metadata['lesson_id']) && !$session->lesson_id) {
        $session->lesson_id = $metadata['lesson_id'];
        $session->save();
        $updatedCount++;
        echo "   Updated session ID {$session->id} with lesson_id: {$metadata['lesson_id']}\n";
    }
}

echo "   ✅ Updated {$updatedCount} sessions with lesson_id\n";

// Verify data integrity
echo "\n3. Verifying data integrity...\n";
$totalSessions = SplitScreenSession::count();
$sessionsWithLessonId = SplitScreenSession::whereNotNull('lesson_id')->count();
$sessionsWithMetadata = SplitScreenSession::whereNotNull('session_metadata')->count();

echo "   Total SplitScreenSessions: {$totalSessions}\n";
echo "   Sessions with lesson_id: {$sessionsWithLessonId}\n";
echo "   Sessions with metadata: {$sessionsWithMetadata}\n";

// Check AIPreferenceLogs
echo "\n4. Checking AIPreferenceLogs...\n";
$totalLogs = AIPreferenceLog::count();
$logsWithSessionId = AIPreferenceLog::whereNotNull('session_id')->count();

// Check if practice_attempt_id column exists
$hasPracticeAttemptIdColumn = Schema::hasColumn('ai_preference_logs', 'practice_attempt_id');
if ($hasPracticeAttemptIdColumn) {
    $logsWithPracticeId = AIPreferenceLog::whereNotNull('practice_attempt_id')->count();
    echo "   Logs with practice_attempt_id: {$logsWithPracticeId}\n";
} else {
    echo "   practice_attempt_id column not found in ai_preference_logs table\n";
}

echo "   Total AIPreferenceLogs: {$totalLogs}\n";
echo "   Logs with session_id: {$logsWithSessionId}\n";

// Sample data verification
echo "\n5. Sample data verification...\n";
$sampleSession = SplitScreenSession::first();
if ($sampleSession) {
    echo "   Sample SplitScreenSession:\n";
    echo "     ID: {$sampleSession->id}\n";
    echo "     User ID: {$sampleSession->user_id}\n";
    echo "     Topic ID: " . ($sampleSession->topic_id ?? 'NULL') . "\n";
    echo "     Lesson ID: " . ($sampleSession->lesson_id ?? 'NULL') . "\n";
    echo "     Session Type: {$sampleSession->session_type}\n";
    echo "     Status: " . ($sampleSession->ended_at ? 'Ended' : 'Active') . "\n";
    echo "     Metadata: " . json_encode($sampleSession->session_metadata) . "\n";
}

$sampleLog = AIPreferenceLog::first();
if ($sampleLog) {
    echo "   Sample AIPreferenceLog:\n";
    echo "     ID: {$sampleLog->id}\n";
    echo "     User ID: {$sampleLog->user_id}\n";
    echo "     Session ID: " . ($sampleLog->session_id ?? 'NULL') . "\n";
    echo "     Chosen AI: {$sampleLog->chosen_ai}\n";
    echo "     Interaction Type: {$sampleLog->interaction_type}\n";
}

echo "\n=== Session Management Fix Complete ===\n";
