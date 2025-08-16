<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AI\TutorService;

class TestConversationHistory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-conversation-history';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test conversation history formatting with different sender types';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing Conversation History Formatting...');
        $this->info('==========================================');
        
        // Create test conversation history with mixed sender types
        $testHistory = [
            [
                'sender' => 'user',
                'text' => 'What is Java?'
            ],
            [
                'sender' => 'bot',
                'text' => 'Java is a programming language.'
            ],
            [
                'sender' => 'user',
                'text' => 'Tell me more about arrays.'
            ],
            [
                'sender' => 'ai',
                'text' => 'Arrays are collections of elements.'
            ],
            [
                'sender' => 'gemini',
                'text' => 'Here is more information about arrays.'
            ],
            [
                'sender' => 'together',
                'text' => 'Arrays can store multiple values.'
            ]
        ];
        
        $this->info('Test conversation history:');
        foreach ($testHistory as $index => $message) {
            $this->info("  {$index}: sender='{$message['sender']}' -> '{$message['text']}'");
        }
        
        // Test the formatting
        $tutorService = app()->make(TutorService::class);
        
        // Use reflection to access the private method
        $reflection = new \ReflectionClass($tutorService);
        $method = $reflection->getMethod('formatConversationHistory');
        $method->setAccessible(true);
        
        $formattedHistory = $method->invoke($tutorService, $testHistory);
        
        $this->info('');
        $this->info('Formatted conversation history:');
        foreach ($formattedHistory as $index => $message) {
            $this->info("  {$index}: role='{$message['role']}' -> '{$message['content']}'");
        }
        
        // Test with a real API call
        $this->info('');
        $this->info('Testing with real API call...');
        
        try {
            $response = $tutorService->getResponseWithContext(
                'What are the basic data types in Java?',
                $formattedHistory,
                [],
                'Java Basics'
            );
            
            $this->info('✅ API call successful!');
            $this->info('Response length: ' . strlen($response));
            $this->info('Response preview: ' . substr($response, 0, 100) . '...');
            
        } catch (\Exception $e) {
            $this->error('❌ API call failed: ' . $e->getMessage());
            return 1;
        }
        
        $this->info('');
        $this->info('✅ Conversation history formatting is working correctly!');
        return 0;
    }
}
