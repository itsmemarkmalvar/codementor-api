<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LessonPlan;
use App\Models\PracticeCategory;
use App\Models\PracticeProblem;
use Illuminate\Support\Str;

class PracticeEnsurePerLesson extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'practice:ensure {--min=3 : Minimum aligned practices per lesson} {--category=Java Fundamentals : Category name to use/create} {--refresh-starters : Refresh starter_code for existing aligned problems to non-solving scaffold}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ensure each published lesson has at least N aligned practice problems (seed baseline if missing)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $min = (int) $this->option('min');
        $categoryName = (string) $this->option('category');

        // Upsert default category
        $category = PracticeCategory::firstOrCreate(
            ['name' => $categoryName],
            [
                'description' => 'Auto-seeded Java fundamentals practice problems',
                'icon' => 'Code2',
                'color' => '#2563EB',
                'display_order' => 0,
                'is_active' => true,
                'required_level' => 0,
            ]
        );

        $lessons = LessonPlan::where('is_published', true)->with('topic')->orderBy('id')->get();
        $createdTotal = 0;

        foreach ($lessons as $lesson) {
            $topicTitle = $lesson->topic->title ?? ($lesson->title ?? 'Java');
            $slugHyphen = Str::slug($topicTitle, '-');
            $slugUnderscore = Str::slug($topicTitle, '_');

            // Count aligned problems by topic_tags heuristic
            $alignedQuery = PracticeProblem::query()->where(function ($q) use ($slugHyphen, $slugUnderscore, $topicTitle) {
                $q->whereJsonContains('topic_tags', $slugHyphen)
                  ->orWhereJsonContains('topic_tags', $slugUnderscore)
                  ->orWhereJsonContains('topic_tags', Str::of($topicTitle)->lower()->toString());
            });
            $alignedCount = (clone $alignedQuery)->count();
            $toCreate = max(0, $min - $alignedCount);

            // Always allow refreshing starters for existing aligned problems
            if ($this->option('refresh-starters')) {
                $this->info("Refreshing starter_code for aligned problems under '{$topicTitle}'...");
                $refreshed = 0;
                $alignedProblems = (clone $alignedQuery)->get();
                foreach ($alignedProblems as $prob) {
                    $prob->starter_code = $this->baselineStarterCode();
                    $prob->save();
                    $refreshed++;
                }
                $this->line("Refreshed starters: {$refreshed}");
            }

            if ($toCreate <= 0) {
                $this->line("Lesson {$lesson->id} '{$lesson->title}' already has {$alignedCount} aligned practices.");
                continue;
            }

            $this->info("Seeding {$toCreate} practices for lesson {$lesson->id} '{$lesson->title}' (topic: {$topicTitle})...");

            for ($i = 1; $i <= $toCreate; $i++) {
                $title = $this->generateTitle($topicTitle, $i);
                $difficulty = $i === 1 ? 'beginner' : 'easy';

                // Provide a scaffold that compiles but DOES NOT solve the task
                $starterCode = $this->baselineStarterCode();
                // Provide a correct reference implementation (not shown to students by default)
                $solutionCode = $this->baselineSolutionCode();

                // Create a small deterministic test set
                $testCases = [
                    ['input' => "3\n1 2 3\n", 'expected_output' => "6"],
                    ['input' => "4\n10 20 30 40\n", 'expected_output' => "100"],
                ];

                PracticeProblem::create([
                    'title' => $title,
                    'category_id' => $category->id,
                    'description' => "Solve a small Java task related to {$topicTitle}.",
                    'instructions' => 'Read N then N integers, output their sum. Keep code readable.',
                    'requirements' => ['Use loops', 'Handle input parsing', 'Print only the sum'],
                    'difficulty_level' => $difficulty,
                    'points' => 100,
                    'estimated_time_minutes' => 15,
                    'complexity_tags' => ['time:O(n)', 'space:O(1)'],
                    'topic_tags' => [$slugHyphen],
                    'starter_code' => $starterCode,
                    'test_cases' => $testCases,
                    'solution_code' => $solutionCode,
                    'expected_output' => array_map(fn($t) => $t['expected_output'], $testCases),
                    'hints' => [
                        'Parse the first integer as the count N.',
                        'Split the next line by spaces to get values.',
                        'Accumulate the sum and print it without extra text.'
                    ],
                    'learning_concepts' => ['Loops', 'Arrays', 'I/O parsing'],
                    'prerequisites' => ['Variables', 'Basic I/O'],
                    'success_rate' => 0,
                    'is_featured' => false,
                    'attempts_count' => 0,
                    'completion_count' => 0,
                ]);

                $createdTotal++;
            }

            
        }

        $this->info("Practice ensure complete. Created {$createdTotal} problems.");
        return self::SUCCESS;
    }

    private function generateTitle(string $topicTitle, int $index): string
    {
        $base = trim($topicTitle);
        if ($base === '') { $base = 'Java Practice'; }
        return $base . ' Practice #' . $index;
    }

    private function baselineStarterCode(): string
    {
        return <<<'JAVA'
import java.util.*;

/**
 * Starter code (guide only):
 * - Read input
 * - Parse numbers
 * - TODO: Implement the correct logic as described by the problem
 *
 * NOTE: This starter prints 0 so tests will fail until you implement the logic.
 */
public class Main {
    public static void main(String[] args) {
        Scanner sc = new Scanner(System.in);
        int n = Integer.parseInt(sc.nextLine().trim());
        String[] parts = sc.nextLine().trim().split(" ");

        // TODO: Replace this placeholder with your solution
        long result = 0;

        // Example parsing loop (you may modify as needed):
        for (int i = 0; i < n && i < parts.length; i++) {
            long value = Long.parseLong(parts[i].trim());
            // Use value to compute the required result
        }

        System.out.println(result);
        sc.close();
    }
}
JAVA;
    }

    private function baselineSolutionCode(): string
    {
        // For now identical to starter; future iterations can include more advanced baseline
        return <<<'JAVA'
import java.util.*;

public class Main {
    public static void main(String[] args) {
        Scanner sc = new Scanner(System.in);
        int n = Integer.parseInt(sc.nextLine().trim());
        String[] parts = sc.nextLine().trim().split(" ");
        long sum = 0;
        for (int i = 0; i < n && i < parts.length; i++) {
            sum += Long.parseLong(parts[i].trim());
        }
        System.out.println(sum);
        sc.close();
    }
}
JAVA;
    }
}


