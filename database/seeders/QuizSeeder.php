<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LessonPlan;
use App\Models\LessonModule;
use App\Models\LessonQuiz;
use App\Models\QuizQuestion;

class QuizSeeder extends Seeder
{
    public function run(): void
    {
        // Pick the first published lesson plan with at least one module
        $lessonPlan = LessonPlan::where('is_published', true)->orderBy('id')->first();
        if (!$lessonPlan) {
            $this->command->warn('No published lesson plans found; skipping QuizSeeder');
            return;
        }

        $module = LessonModule::where('lesson_plan_id', $lessonPlan->id)
            ->where('is_published', true)
            ->orderBy('order_index')
            ->first();
        if (!$module) {
            $this->command->warn('No published modules found for lesson plan; skipping QuizSeeder');
            return;
        }

        // Helper to avoid duplicates
        $ensureQuiz = function (int $difficulty, string $title, int $passing) use ($module) {
            $quiz = LessonQuiz::firstOrCreate(
                [ 'module_id' => $module->id, 'difficulty' => $difficulty, 'title' => $title ],
                [
                    'description' => $title . ' assessment',
                    'time_limit_minutes' => 20,
                    'passing_score_percent' => $passing,
                    'points_per_question' => 10,
                    'is_published' => true,
                    'order_index' => $difficulty,
                ]
            );
            return $quiz;
        };

        $easy = $ensureQuiz(1, 'Module Checkpoint (Easy)', 60);
        $medium = $ensureQuiz(2, 'Module Checkpoint (Medium)', 70);
        $hard = $ensureQuiz(3, 'Module Mastery (Hard)', 80);

        // Seed questions if none exist
        $seedQuestions = function (LessonQuiz $quiz, array $questions) {
            if ($quiz->questions()->count() > 0) { return; }
            $order = 1;
            foreach ($questions as $q) {
                QuizQuestion::create([
                    'quiz_id' => $quiz->id,
                    'question_text' => $q['text'],
                    'type' => $q['type'],
                    'options' => $q['options'] ?? null,
                    'correct_answers' => $q['answers'],
                    'explanation' => $q['explanation'] ?? null,
                    'points' => 10,
                    'code_snippet' => $q['code'] ?? null,
                    'order_index' => $order++,
                ]);
            }
        };

        $easyQs = [
            [ 'text' => 'Which keyword defines a class in Java?', 'type' => 'multiple_choice', 'options' => ['object','class','define','struct'], 'answers' => ['class'] ],
            [ 'text' => 'True or False: A Java file can contain multiple public classes.', 'type' => 'true_false', 'answers' => [false] ],
            [ 'text' => 'Which method is the entry point of a Java application?', 'type' => 'multiple_choice', 'options' => ['run','start','main','execute'], 'answers' => ['main'] ],
            [ 'text' => 'Fill in the blank: Access modifier for visibility within same package and subclasses is _____.', 'type' => 'fill_in_blank', 'answers' => ['protected'] ],
        ];

        $mediumQs = [
            [ 'text' => 'Which collection does not allow duplicates?', 'type' => 'multiple_choice', 'options' => ['List','Set','Map','Queue'], 'answers' => ['Set'] ],
            [ 'text' => 'Select all that are primitive types.', 'type' => 'multiple_choice', 'options' => ['String','int','double','Integer'], 'answers' => ['int','double'] ],
            [ 'text' => 'True or False: Overloading depends on method name and parameter list.', 'type' => 'true_false', 'answers' => [true] ],
            [ 'text' => 'Which keyword prevents inheritance?', 'type' => 'multiple_choice', 'options' => ['static','final','const','sealed'], 'answers' => ['final'] ],
        ];

        $hardQs = [
            [ 'text' => 'What is the output?', 'type' => 'multiple_choice', 'code' => "int x=0; for(int i=0;i<3;i++){ x += i; } System.out.println(x);", 'options' => ['0','3','6','9'], 'answers' => ['3'] ],
            [ 'text' => 'True or False: HashMap iteration order is predictable across JVMs.', 'type' => 'true_false', 'answers' => [false] ],
            [ 'text' => 'Fill: The JVM memory area storing class metadata is called the _____.', 'type' => 'fill_in_blank', 'answers' => ['metaspace','meta space'] ],
            [ 'text' => 'Which statement creates an immutable list (Java 9+)?', 'type' => 'multiple_choice', 'options' => ['new ArrayList<>()','Collections.unmodifiableList(new ArrayList<>())','List.of(1,2,3)','Arrays.asList(1,2,3)'], 'answers' => ['List.of(1,2,3)'] ],
        ];

        $seedQuestions($easy, $easyQs);
        $seedQuestions($medium, $mediumQs);
        $seedQuestions($hard, $hardQs);

        $this->command->info('QuizSeeder: Seeded E/M/H quizzes with questions for module ID '.$module->id);
    }
}


