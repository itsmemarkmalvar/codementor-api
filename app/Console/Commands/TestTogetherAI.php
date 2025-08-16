<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AI\TutorService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestTogetherAI extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-together-ai';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Together AI API key and connectivity';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing Together AI Configuration...');
        $this->info('=====================================');
        
        // Test API key configuration
        $this->info('1. Testing API Key Configuration:');
        $envKey = env('TOGETHER_API_KEY', '');
        $configKey = config('services.together.api_key', '');
        
        $this->info("   ENV TOGETHER_API_KEY: " . ($envKey ? substr($envKey, 0, 10) . '...' : 'NOT SET'));
        $this->info("   CONFIG services.together.api_key: " . ($configKey ? substr($configKey, 0, 10) . '...' : 'NOT SET'));
        
        $hasKey = !empty($envKey) || !empty($configKey);
        $this->info("   Status: " . ($hasKey ? '✅ Configured' : '❌ Not Configured'));
        
        if (!$hasKey) {
            $this->error('❌ No API key found. Please set TOGETHER_API_KEY in your .env file.');
            return 1;
        }
        
        // Test service instantiation
        $this->info('2. Testing Service Instantiation:');
        try {
            $tutorService = app()->make(TutorService::class);
            $this->info("   TutorService: ✅ Successfully instantiated");
        } catch (\Exception $e) {
            $this->error("   TutorService: ❌ Failed to instantiate - " . $e->getMessage());
            return 1;
        }
        
        // Test API connectivity
        $this->info('3. Testing API Connectivity:');
        $apiKey = $envKey ?: $configKey;
        $apiUrl = env('TOGETHER_API_URL', 'https://api.together.xyz/v1');
        
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post($apiUrl . '/chat/completions', [
                'model' => 'mistralai/Mixtral-8x7B-Instruct-v0.1',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => 'Hello, this is a test message. Please respond with "API test successful".'
                    ]
                ],
                'max_tokens' => 50,
                'temperature' => 0.7
            ]);
            
            if ($response->successful()) {
                $this->info("   API Test: ✅ Success - Status " . $response->status());
                $data = $response->json();
                if (isset($data['choices'][0]['message']['content'])) {
                    $this->info("   Response: " . trim($data['choices'][0]['message']['content']));
                }
            } else {
                $this->error("   API Test: ❌ Failed - Status " . $response->status());
                $this->error("   Error: " . $response->body());
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("   API Test: ❌ Exception - " . $e->getMessage());
            return 1;
        }
        
        // Test service method
        $this->info('4. Testing Service Method:');
        try {
            $response = $tutorService->getResponse(
                'What is the capital of France?',
                [],
                [],
                'Test Topic'
            );
            
            if (strpos($response, 'Together AI is not configured') !== false) {
                $this->error("   Service Test: ❌ Service returned fallback message");
                $this->error("   Response: " . substr($response, 0, 100) . "...");
                return 1;
            } else {
                $this->info("   Service Test: ✅ Success");
                $this->info("   Response: " . substr($response, 0, 100) . "...");
            }
        } catch (\Exception $e) {
            $this->error("   Service Test: ❌ Exception - " . $e->getMessage());
            return 1;
        }
        
        $this->info('');
        $this->info('✅ Together AI is properly configured and working!');
        return 0;
    }
}
