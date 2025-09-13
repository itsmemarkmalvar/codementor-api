<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LessonPlan;
use App\Models\LessonModule;
use App\Models\ModuleProgress;
use App\Models\SplitScreenSession;
use App\Models\UserLessonCompletion;

class BackfillLessonCompletions extends Command
{
    protected $signature = 'lessons:backfill-completions {--dry-run}';
    protected $description = 'Scan existing progress and sessions to backfill user_lesson_completions rows';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $countInserted = 0;

        $lessons = LessonPlan::where('is_published', true)->get(['id']);
        foreach ($lessons as $lesson) {
            $moduleIds = LessonModule::where('lesson_plan_id', $lesson->id)->pluck('id')->all();
            if (empty($moduleIds)) { continue; }

            // Users with any progress in these modules
            $userIds = ModuleProgress::whereIn('module_id', $moduleIds)->pluck('user_id')->unique()->all();
            foreach ($userIds as $userId) {
                // Module-based completion
                $progress = ModuleProgress::whereIn('module_id', $moduleIds)
                    ->where('user_id', $userId)
                    ->get(['progress_percentage','status']);
                $avg = $progress->count() ? (int) round($progress->avg('progress_percentage')) : 0;
                $completeModules = $avg >= 100 || ($progress->count() > 0 && $progress->every(fn($p) => ((int)$p->progress_percentage) >= 100 || $p->status === 'completed'));

                // Engagement-based completion
                $latest = SplitScreenSession::where('user_id', $userId)
                    ->where('lesson_id', $lesson->id)
                    ->orderByDesc('updated_at')
                    ->first(['engagement_score','practice_completed']);
                $completeEng = $latest && (((int) ($latest->engagement_score ?? 0)) >= 70 || (bool) ($latest->practice_completed ?? false));

                if ($completeModules || $completeEng) {
                    $source = $completeModules && $completeEng ? 'both' : ($completeModules ? 'modules' : 'engagement');
                    if ($dry) {
                        $this->line("[DRY] user={$userId} lesson={$lesson->id} -> {$source}");
                    } else {
                        $row = UserLessonCompletion::updateOrCreate(
                            ['user_id' => $userId, 'lesson_plan_id' => $lesson->id],
                            ['completed_at' => now(), 'source' => $source]
                        );
                        if ($row->wasRecentlyCreated) { $countInserted++; }
                    }
                }
            }
        }

        $this->info($dry ? 'Dry-run complete.' : ("Backfill complete. Inserted {$countInserted} new rows."));
        return 0;
    }
}


