<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LessonModule;

class PopulateModuleContent extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'lessons:populate-content {--dry-run : Show changes without saving} {--limit=0 : Limit number of modules to process} {--force : Overwrite existing short/placeholder content}';

    /**
     * The console command description.
     */
    protected $description = 'Populate lesson_modules.content from existing metadata (description, key_points, examples, guidance).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $query = LessonModule::query();
        if ($limit > 0) { $query->limit($limit); }
        $modules = $query->get();

        $dryRun = (bool) $this->option('dry-run');
        $updated = 0; $skipped = 0;

        foreach ($modules as $module) {
            $original = (string) ($module->content ?? '');
            $shouldOverwrite = $this->shouldPopulate($original) || (bool) $this->option('force');
            if (!$shouldOverwrite) { $skipped++; continue; }

            $md = $this->composeMarkdown($module);
            if ($dryRun) {
                $this->line("[DRY] Would populate module #{$module->id} '{$module->title}' ({$module->lesson_plan_id}) with " . strlen($md) . ' chars');
            } else {
                $module->content = $md;
                $module->save();
                $updated++;
            }
        }

        $this->info($dryRun
            ? "Dry run complete. Skipped {$skipped}. Would update: {$updated}."
            : "Updated {$updated} modules. Skipped {$skipped} already populated modules.");

        return Command::SUCCESS;
    }

    private function shouldPopulate(string $original): bool
    {
        $trimmed = trim($original);
        if ($trimmed === '') { return true; }
        // Placeholder detection
        $lower = strtolower($trimmed);
        if (strpos($lower, 'content coming soon') !== false) { return true; }
        if (strpos($lower, 'coming soon') !== false) { return true; }
        // If content is extremely short (< 40 chars), consider it placeholder
        if (mb_strlen($trimmed) < 40) { return true; }
        return false;
    }

    private function composeMarkdown(LessonModule $module): string
    {
        $lines = [];
        $lines[] = '# ' . ($module->title ?? 'Module');
        if ($module->description) {
            $lines[] = '';
            $lines[] = $module->description;
        }
        if ($module->key_points) {
            $lines[] = '';
            $lines[] = '## Key Points';
            foreach (explode(',', (string) $module->key_points) as $point) {
                $point = trim($point);
                if ($point !== '') { $lines[] = '- ' . $point; }
            }
        }
        if ($module->examples) {
            $lines[] = '';
            $lines[] = '## Example';
            $lines[] = '```java';
            $lines[] = trim((string) $module->examples);
            $lines[] = '```';
        }
        $guidance = $module->guidance_notes;
        if (is_string($guidance)) {
            $guidance = array_filter(array_map('trim', preg_split('/[\n\r]+|\.|,|;/', $guidance)));
        }
        if (is_array($guidance) && count($guidance) > 0) {
            $lines[] = '';
            $lines[] = '## Guidance Notes';
            foreach ($guidance as $n) { $lines[] = '- ' . (string) $n; }
        }
        $mis = $module->common_misconceptions;
        if (is_string($mis)) {
            $mis = array_filter(array_map('trim', preg_split('/[\n\r]+|\.|,|;/', $mis)));
        }
        if (is_array($mis) && count($mis) > 0) {
            $lines[] = '';
            $lines[] = '## Common Misconceptions';
            foreach ($mis as $n) { $lines[] = '- ' . (string) $n; }
        }
        $lines[] = '';
        $lines[] = '> Tip: Use the AI Tutor (Split Screen) for walkthroughs and practice.';
        return implode("\n", $lines);
    }
}


