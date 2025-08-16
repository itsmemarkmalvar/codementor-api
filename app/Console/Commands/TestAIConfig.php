<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AI\TutorService;
use App\Services\AI\GeminiService;

class TestAIConfig extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-ai-config';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test AI configuration and API keys';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing AI Configuration...');
        $this->info('==========================');
        
        // Test Gemini AI
        $this->info('Testing Gemini AI:');
        $geminiKey = env('GEMINI_API_KEY', '');
        $hasGeminiKey = !empty($geminiKey);
        $maskedGeminiKey = $hasGeminiKey ? substr($geminiKey, 0, 4) . '...' . substr($geminiKey, -4) : 'Not Set';
        
        $this->info("  GEMINI_API_KEY: {$maskedGeminiKey}");
        $this->info("  Status: " . ($hasGeminiKey ? '✅ Configured' : '❌ Not Configured'));
        
        // Test Together AI
        $this->info('Testing Together AI:');
        $togetherKey = env('TOGETHER_API_KEY', '');
        $hasTogetherKey = !empty($togetherKey);
        $maskedTogetherKey = $hasTogetherKey ? substr($togetherKey, 0, 4) . '...' . substr($togetherKey, -4) : 'Not Set';
        
        $this->info("  TOGETHER_API_KEY: {$maskedTogetherKey}");
        $this->info("  Status: " . ($hasTogetherKey ? '✅ Configured' : '❌ Not Configured'));
        
        // Test service instantiation
        $this->info('Testing Service Instantiation:');
        try {
            $geminiService = app()->make(GeminiService::class);
            $this->info("  GeminiService: ✅ Successfully instantiated");
        } catch (\Exception $e) {
            $this->error("  GeminiService: ❌ Failed to instantiate - " . $e->getMessage());
        }
        
        try {
            $tutorService = app()->make(TutorService::class);
            $this->info("  TutorService: ✅ Successfully instantiated");
        } catch (\Exception $e) {
            $this->error("  TutorService: ❌ Failed to instantiate - " . $e->getMessage());
        }
        
        // Summary
        $this->info('');
        $this->info('Summary:');
        if ($hasGeminiKey && $hasTogetherKey) {
            $this->info('✅ Both AI services are configured and ready to use.');
        } elseif ($hasGeminiKey && !$hasTogetherKey) {
            $this->warn('⚠️  Only Gemini AI is configured. Together AI will show fallback messages.');
            $this->info('To fix Together AI, add TOGETHER_API_KEY to your .env file.');
        } elseif (!$hasGeminiKey && $hasTogetherKey) {
            $this->warn('⚠️  Only Together AI is configured. Gemini AI will show fallback messages.');
            $this->info('To fix Gemini AI, add GEMINI_API_KEY to your .env file.');
        } else {
            $this->error('❌ No AI services are configured. Both will show fallback messages.');
            $this->info('To fix this, add both GEMINI_API_KEY and TOGETHER_API_KEY to your .env file.');
        }
        
        return 0;
    }
}
