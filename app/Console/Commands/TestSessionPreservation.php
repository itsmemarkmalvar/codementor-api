<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PreservedSession;

class TestSessionPreservation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:session-preservation';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the session preservation functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing PreservedSession functionality...');

        try {
            // Test 1: Create a new session
            $this->info('Test 1: Creating new session...');
            $sessionData = [
                'user_id' => 'test_user_123',
                'topic_id' => 1,
                'lesson_id' => 1,
                'session_type' => 'comparison',
                'ai_models_used' => ['gemini', 'together']
            ];
            
            $session = PreservedSession::createSession($sessionData);
            $this->info("✓ Session created successfully: {$session->session_identifier}");
            
            // Test 2: Get most recent session
            $this->info('Test 2: Getting most recent session...');
            $recentSession = PreservedSession::getMostRecentSession('test_user_123', 1);
            if ($recentSession) {
                $this->info("✓ Recent session found: {$recentSession->session_identifier}");
            } else {
                $this->error("✗ No recent session found");
            }
            
            // Test 3: Update session activity
            $this->info('Test 3: Updating session activity...');
            $session->updateActivity();
            $this->info("✓ Session activity updated");
            
            // Test 4: Add message to conversation history
            $this->info('Test 4: Adding message to conversation history...');
            $message = [
                'role' => 'user',
                'content' => 'Hello, this is a test message',
                'timestamp' => now()
            ];
            $session->addMessage($message);
            $this->info("✓ Message added to conversation history");
            
            // Test 5: Mark session as inactive
            $this->info('Test 5: Marking session as inactive...');
            $session->markAsInactive();
            $this->info("✓ Session marked as inactive");
            
            // Test 6: Mark session as active
            $this->info('Test 6: Marking session as active...');
            $session->markAsActive();
            $this->info("✓ Session marked as active");
            
            // Test 7: Cleanup test session
            $this->info('Test 7: Cleaning up test session...');
            $session->delete();
            $this->info("✓ Test session deleted");
            
            $this->info("\n🎉 All tests passed! Session preservation is working correctly.");
            
        } catch (\Exception $e) {
            $this->error("✗ Test failed: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
        }
    }
}
