<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestEnvVars extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-env-vars';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test environment variables for proper loading';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing environment variables:');
        $this->info('---------------------------');
        
        // App configuration
        $this->info('APP_ENV: ' . env('APP_ENV', 'Not set'));
        $this->info('APP_URL: ' . env('APP_URL', 'Not set'));
        
        // AI Service configuration
        $apiKey = env('GEMINI_API_KEY', '');
        $hasKey = !empty($apiKey);
        $maskedKey = $hasKey ? substr($apiKey, 0, 4) . '...' . substr($apiKey, -4) : 'Not Set';
        
        $this->info('GEMINI_API_KEY: ' . $maskedKey);
        $this->info('GEMINI_API_KEY exists: ' . ($hasKey ? 'Yes' : 'No'));
        
        // Database configuration (for reference)
        $this->info('DB_CONNECTION: ' . env('DB_CONNECTION', 'Not set'));
        $this->info('DB_HOST: ' . env('DB_HOST', 'Not set'));
        
        // CORS configuration
        $this->info('CORS allowed origins: ' . implode(', ', config('cors.allowed_origins', [])));
        $this->info('CORS supports credentials: ' . (config('cors.supports_credentials', false) ? 'Yes' : 'No'));
        
        return 0;
    }
}
