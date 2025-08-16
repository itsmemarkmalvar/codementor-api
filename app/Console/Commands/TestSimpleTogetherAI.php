<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AI\TutorService;
use Illuminate\Support\Facades\Http;

class TestSimpleTogetherAI extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-simple-together-ai';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Together AI with minimal request to bypass conversation history issues';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing Together AI with Minimal Request...');
        $this->info('==========================================');
        
        $apiKey = env('TOGETHER_API_KEY', '');
        $apiUrl = env('TOGETHER_API_URL', 'https://api.together.xyz/v1');
        
        if (empty($apiKey)) {
            $this->error('❌ No API key found');
            return 1;
        }
        
        // Test 1: Direct API call with minimal request
        $this->info('1. Testing direct API call with minimal request...');
        
        $minimalRequest = [
            'model' => 'mistralai/Mixtral-8x7B-Instruct-v0.1',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a helpful Java programming tutor. Provide clear, concise explanations.'
                ],
                [
                    'role' => 'user',
                    'content' => 'What are the basic data types in Java?'
                ]
            ],
            'temperature' => 0.7,
            'max_tokens' => 600,
        ];
        
        try {
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ])->post($apiUrl . '/chat/completions', $minimalRequest);
            
            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['choices'][0]['message']['content'])) {
                    $this->info('✅ Direct API call successful!');
                    $this->info('Response: ' . substr($data['choices'][0]['message']['content'], 0, 200) . '...');
                } else {
                    $this->error('❌ Unexpected response structure');
                    $this->error('Response: ' . json_encode($data));
                    return 1;
                }
            } else {
                $this->error('❌ Direct API call failed');
                $this->error('Status: ' . $response->status());
                $this->error('Response: ' . $response->body());
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('❌ Direct API call exception: ' . $e->getMessage());
            return 1;
        }
        
        // Test 2: Service method with empty conversation history
        $this->info('');
        $this->info('2. Testing service method with empty conversation history...');
        
        try {
            $tutorService = app()->make(TutorService::class);
            $response = $tutorService->getResponseWithContext(
                'What are the basic data types in Java?',
                [], // Empty conversation history
                [],
                'Java Basics'
            );
            
            if (strpos($response, 'Together AI is not configured') !== false || 
                strpos($response, 'having trouble connecting') !== false) {
                $this->error('❌ Service method returned fallback response');
                $this->error('Response: ' . substr($response, 0, 200) . '...');
                return 1;
            } else {
                $this->info('✅ Service method successful!');
                $this->info('Response: ' . substr($response, 0, 200) . '...');
            }
        } catch (\Exception $e) {
            $this->error('❌ Service method exception: ' . $e->getMessage());
            return 1;
        }
        
        // Test 3: Service method with simple conversation history
        $this->info('');
        $this->info('3. Testing service method with simple conversation history...');
        
        $simpleHistory = [
            [
                'role' => 'user',
                'content' => 'What is Java?'
            ],
            [
                'role' => 'assistant',
                'content' => 'Java is a programming language.'
            ]
        ];
        
        try {
            $response = $tutorService->getResponseWithContext(
                'What are the basic data types in Java?',
                $simpleHistory,
                [],
                'Java Basics'
            );
            
            if (strpos($response, 'Together AI is not configured') !== false || 
                strpos($response, 'having trouble connecting') !== false) {
                $this->error('❌ Service method with history returned fallback response');
                $this->error('Response: ' . substr($response, 0, 200) . '...');
                return 1;
            } else {
                $this->info('✅ Service method with history successful!');
                $this->info('Response: ' . substr($response, 0, 200) . '...');
            }
        } catch (\Exception $e) {
            $this->error('❌ Service method with history exception: ' . $e->getMessage());
            return 1;
        }
        
        $this->info('');
        $this->info('✅ All tests passed! Together AI is working correctly.');
        return 0;
    }
}
