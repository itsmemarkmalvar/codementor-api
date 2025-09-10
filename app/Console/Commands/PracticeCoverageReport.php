<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LessonPlan;
use App\Models\PracticeProblem;
use Illuminate\Support\Str;

class PracticeCoverageReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'practice:coverage {--output=storage/app/practice_coverage.csv : Output CSV path}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate coverage report of practice problems aligned to each published lesson';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $outputPath = base_path($this->option('output'));
        $this->info('Generating practice coverage to: ' . $outputPath);

        // Ensure directory exists
        @mkdir(dirname($outputPath), 0777, true);

        $fh = fopen($outputPath, 'w');
        fputcsv($fh, [
            'lesson_id', 'lesson_title', 'topic_id', 'topic_title',
            'aligned_total', 'beginner', 'easy', 'medium', 'hard', 'expert'
        ]);

        // Heuristic: we align by topic title slug in PracticeProblem.topic_tags (JSON)
        $lessons = LessonPlan::with('topic')->where('is_published', true)->get();

        foreach ($lessons as $lesson) {
            $topicTitle = $lesson->topic?->title ?? '';
            $slugUnderscore = Str::of($topicTitle)->lower()->slug('_');   // e.g., java_basics
            $slugHyphen     = Str::of($topicTitle)->lower()->slug('-');   // e.g., java-basics
            $lowerTitle     = Str::of($topicTitle)->lower()->toString();  // e.g., java basics

            // Count problems whose topic_tags include any of the slug variants or lowercased title
            $aligned = PracticeProblem::query()
                ->where(function ($q) use ($slugUnderscore, $slugHyphen, $lowerTitle) {
                    $q->whereJsonContains('topic_tags', $slugUnderscore)
                      ->orWhereJsonContains('topic_tags', $slugHyphen)
                      ->orWhereJsonContains('topic_tags', $lowerTitle);
                })
                ->get();

            $byDiff = [
                'beginner' => 0, 'easy' => 0, 'medium' => 0, 'hard' => 0, 'expert' => 0,
            ];
            foreach ($aligned as $p) {
                $lvl = $p->difficulty_level ?? 'easy';
                if (isset($byDiff[$lvl])) $byDiff[$lvl]++;
            }

            fputcsv($fh, [
                $lesson->id,
                $lesson->title,
                $lesson->topic_id,
                $topicTitle,
                $aligned->count(),
                $byDiff['beginner'],
                $byDiff['easy'],
                $byDiff['medium'],
                $byDiff['hard'],
                $byDiff['expert'],
            ]);
        }

        fclose($fh);
        $this->info('Practice coverage report generated.');
        return Command::SUCCESS;
    }
}


