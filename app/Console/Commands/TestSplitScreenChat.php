<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AI\TutorService;
use App\Services\AI\GeminiService;

class TestSplitScreenChat extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-split-screen-chat';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test split-screen chat functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing Split-Screen Chat...');
        $this->info('==========================');
        
        // Simulate conversation history from split-screen chat
        $conversationHistory = [
            [
                'role' => 'user',
                'content' => 'What is Java?'
            ],
            [
                'role' => 'assistant',
                'content' => 'Java is a programming language.'
            ],
            [
                'role' => 'user',
                'content' => 'Tell me about arrays.'
            ],
            [
                'role' => 'assistant',
                'content' => 'Arrays are collections of elements.'
            ],
            [
                'role' => 'user',
                'content' => 'What about loops?'
            ],
            [
                'role' => 'assistant',
                'content' => 'Loops are used to repeat code.'
            ]
        ];
        
        $this->info('Original conversation history:');
        foreach ($conversationHistory as $index => $message) {
            $this->info("  {$index}: {$message['role']} -> {$message['content']}");
        }
        
        // Simulate the split-screen chat logic
        $normalizedHistory = $conversationHistory;
        if (count($normalizedHistory) > 4) {
            $normalizedHistory = array_slice($normalizedHistory, -4);
        }
        
        $this->info('');
        $this->info('Normalized conversation history (max 4):');
        foreach ($normalizedHistory as $index => $message) {
            $this->info("  {$index}: {$message['role']} -> {$message['content']}");
        }
        
        // Create simplified history for Together AI
        $togetherConversationHistory = $normalizedHistory;
        if (count($togetherConversationHistory) > 2) {
            $togetherConversationHistory = array_slice($togetherConversationHistory, -2);
        }
        
        $this->info('');
        $this->info('Together AI conversation history (max 2):');
        foreach ($togetherConversationHistory as $index => $message) {
            $this->info("  {$index}: {$message['role']} -> {$message['content']}");
        }
        
        // Test Together AI with simplified history
        $this->info('');
        $this->info('Testing Together AI with simplified history...');
        
        try {
            $tutorService = app()->make(TutorService::class);
            $response = $tutorService->getResponseWithContext(
                'What are the basic data types in Java?',
                $togetherConversationHistory,
                [],
                'Java Basics'
            );
            
            if (strpos($response, 'Together AI is not configured') !== false || 
                strpos($response, 'having trouble connecting') !== false) {
                $this->error('❌ Together AI returned fallback response');
                $this->error('Response: ' . substr($response, 0, 200) . '...');
                return 1;
            } else {
                $this->info('✅ Together AI successful!');
                $this->info('Response: ' . substr($response, 0, 200) . '...');
            }
        } catch (\Exception $e) {
            $this->error('❌ Together AI exception: ' . $e->getMessage());
            return 1;
        }
        
        $this->info('');
        $this->info('✅ Split-screen chat test passed!');
        return 0;
    }
}
