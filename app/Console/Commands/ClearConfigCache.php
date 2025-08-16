<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ClearConfigCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clear-config-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear Laravel config cache to ensure environment variables are loaded';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Clearing Laravel config cache...');
        
        try {
            // Clear config cache
            $this->call('config:clear');
            $this->info('✅ Config cache cleared successfully');
            
            // Clear application cache
            $this->call('cache:clear');
            $this->info('✅ Application cache cleared successfully');
            
            // Clear route cache
            $this->call('route:clear');
            $this->info('✅ Route cache cleared successfully');
            
            // Clear view cache
            $this->call('view:clear');
            $this->info('✅ View cache cleared successfully');
            
            $this->info('');
            $this->info('🎉 All caches cleared! Environment variables should now be properly loaded.');
            $this->info('');
            $this->info('Next steps:');
            $this->info('1. Restart your Laravel application');
            $this->info('2. Test Together AI with: php artisan app:test-together-ai');
            
            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Error clearing cache: ' . $e->getMessage());
            return 1;
        }
    }
}
