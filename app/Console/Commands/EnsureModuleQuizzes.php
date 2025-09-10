<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LessonModule;
use App\Models\LessonQuiz;
use App\Models\QuizQuestion;

class EnsureModuleQuizzes extends Command
{
    protected $signature = 'lessons:ensure-quizzes {--limit=0 : Limit modules to process} {--dry-run : Preview without writing}';

    protected $description = 'Ensure every published module has baseline quizzes (E/M/H) with questions.';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $dry = (bool) $this->option('dry-run');

        $modulesQ = LessonModule::where('is_published', true)->orderBy('lesson_plan_id')->orderBy('order_index');
        if ($limit > 0) { $modulesQ->limit($limit); }
        $modules = $modulesQ->get();

        $created = 0; $skipped = 0;

        foreach ($modules as $module) {
            $count = LessonQuiz::where('module_id', $module->id)->count();
            if ($count > 0) { $skipped++; continue; }

            if ($dry) {
                $this->line("[DRY] Would create quizzes for module #{$module->id} ({$module->title})");
                $created++; // count as would-create
                continue;
            }

            $this->createBaselineQuizzes($module->id);
            $created++;
        }

        $this->info($dry
            ? "Dry run complete. Modules needing quizzes: {$created}. Already had quizzes: {$skipped}."
            : "Quizzes created for {$created} modules. {$skipped} modules already had quizzes.");

        return Command::SUCCESS;
    }

    private function createBaselineQuizzes(int $moduleId): void
    {
        $makeQuiz = function (int $difficulty, string $title, int $passing) use ($moduleId): LessonQuiz {
            return LessonQuiz::firstOrCreate(
                [ 'module_id' => $moduleId, 'difficulty' => $difficulty, 'title' => $title ],
                [
                    'description' => $title . ' assessment',
                    'time_limit_minutes' => 15,
                    'passing_score_percent' => $passing,
                    'points_per_question' => 10,
                    'is_published' => true,
                    'order_index' => $difficulty,
                ]
            );
        };

        $easy = $makeQuiz(1, 'Module Checkpoint (Easy)', 60);
        $med  = $makeQuiz(2, 'Module Checkpoint (Medium)', 70);
        $hard = $makeQuiz(3, 'Module Mastery (Hard)', 80);

        $seed = function (LessonQuiz $quiz, array $qs) {
            if ($quiz->questions()->count() > 0) { return; }
            $i = 1;
            foreach ($qs as $q) {
                QuizQuestion::create([
                    'quiz_id' => $quiz->id,
                    'question_text' => $q['text'],
                    'type' => $q['type'],
                    'options' => $q['options'] ?? null,
                    'correct_answers' => $q['answers'],
                    'explanation' => $q['explanation'] ?? null,
                    'points' => 10,
                    'code_snippet' => $q['code'] ?? null,
                    'order_index' => $i++,
                ]);
            }
        };

        $easyQs = [
            [ 'text' => 'Which keyword defines a class in Java?', 'type' => 'multiple_choice', 'options' => ['object','class','define','struct'], 'answers' => ['class'] ],
            [ 'text' => 'True or False: The entry point method is main(String[] args).', 'type' => 'true_false', 'answers' => [true] ],
            [ 'text' => 'Which type holds whole numbers?', 'type' => 'multiple_choice', 'options' => ['String','int','double','boolean'], 'answers' => ['int'] ],
        ];
        $medQs = [
            [ 'text' => 'Which collection disallows duplicates?', 'type' => 'multiple_choice', 'options' => ['List','Set','Map','Deque'], 'answers' => ['Set'] ],
            [ 'text' => 'True or False: Overloading depends on method name and parameter list.', 'type' => 'true_false', 'answers' => [true] ],
            [ 'text' => 'Choose a visibility modifier for same-package and subclasses.', 'type' => 'multiple_choice', 'options' => ['private','protected','public','package'], 'answers' => ['protected'] ],
        ];
        $hardQs = [
            [ 'text' => 'What prints? int x=0; for(int i=1;i<=3;i++){ x+=i; } System.out.println(x);', 'type' => 'multiple_choice', 'options' => ['3','4','6','7'], 'answers' => ['6'] ],
            [ 'text' => 'True or False: HashMap preserves insertion order.', 'type' => 'true_false', 'answers' => [false] ],
            [ 'text' => 'Fill: Immutable list factory method in Java 9+ is _____.', 'type' => 'fill_in_blank', 'answers' => ['List.of','List.of()'] ],
        ];

        $seed($easy, $easyQs);
        $seed($med, $medQs);
        $seed($hard, $hardQs);
    }
}


