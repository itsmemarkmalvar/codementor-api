<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\LearningTopic;
use App\Models\LessonPlan;
use App\Models\LessonModule;
use App\Models\LessonExercise;
use App\Models\ExerciseAttempt;
use App\Models\ModuleProgress;

class ResetTopicsAndLessonsSeeder extends Seeder
{
    /**
     * Danger: wipes topics, lesson plans, modules, exercises and related progress/attempts.
     */
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Child data first
        ExerciseAttempt::truncate();
        ModuleProgress::truncate();
        LessonExercise::truncate();
        LessonModule::truncate();
        LessonPlan::truncate();
        LearningTopic::truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->command->info('Topics, lesson plans, modules, exercises, attempts, and module progress cleared.');
    }
}


