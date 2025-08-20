<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PreservedSession;
use Illuminate\Support\Facades\Log;

class CleanupInactiveSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sessions:cleanup {--days=5 : Number of days of inactivity before cleanup}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up inactive preserved sessions after specified days of inactivity';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = $this->option('days');
        $cutoffDate = now()->subDays($days);

        $this->info("Cleaning up sessions inactive for more than {$days} days...");

        // Get count of sessions to be deleted
        $sessionsToDelete = PreservedSession::where('last_activity', '<', $cutoffDate)
            ->where('is_active', false)
            ->count();

        if ($sessionsToDelete === 0) {
            $this->info('No inactive sessions found to clean up.');
            return 0;
        }

        $this->info("Found {$sessionsToDelete} inactive sessions to delete.");

        if ($this->confirm('Do you want to proceed with the cleanup?')) {
            // Delete inactive sessions
            $deletedCount = PreservedSession::where('last_activity', '<', $cutoffDate)
                ->where('is_active', false)
                ->delete();

            $this->info("Successfully deleted {$deletedCount} inactive sessions.");

            // Log the cleanup
            Log::info("CleanupInactiveSessions: Deleted {$deletedCount} sessions inactive for more than {$days} days.");

            return 0;
        } else {
            $this->info('Cleanup cancelled.');
            return 1;
        }
    }
}
