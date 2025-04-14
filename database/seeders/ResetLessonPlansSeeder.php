<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LessonPlan;
use App\Models\LessonModule;
use App\Models\LessonExercise;
use App\Models\ExerciseAttempt;
use App\Models\ModuleProgress;
use Illuminate\Support\Facades\DB;

class ResetLessonPlansSeeder extends Seeder
{
    /**
     * Run the database seeds to clean up existing lesson plan data.
     */
    public function run(): void
    {
        // Disable foreign key checks to allow cascading deletes
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        // Clear all exercise attempts
        ExerciseAttempt::truncate();
        $this->command->info('Cleared all exercise attempts.');
        
        // Clear all module progress
        ModuleProgress::truncate();
        $this->command->info('Cleared all module progress.');
        
        // Clear all exercises
        LessonExercise::truncate();
        $this->command->info('Cleared all lesson exercises.');
        
        // Clear all modules
        LessonModule::truncate();
        $this->command->info('Cleared all lesson modules.');
        
        // Clear all lesson plans
        LessonPlan::truncate();
        $this->command->info('Cleared all lesson plans.');
        
        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        
        $this->command->info('All lesson plan data has been reset. You can now run the LessonPlanSeeder to add fresh data.');
    }
} 