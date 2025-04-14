<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        
        // Choose ONE approach for topic seeding - simple or hierarchical
        
        // OPTION 1: Simple topics only (uncomment this line)
        $this->call(TopicSeeder::class);
        
        // OPTION 2: Full topic hierarchy with subtopics (comment out for now)
        // $this->call(TopicHierarchySeeder::class);
        
        // Seed lesson plans after topics are created
        $this->call(LessonPlanSeeder::class);
        
        // Seed advanced Java lesson plans
        $this->call(AdvancedJavaLessonPlanSeeder::class);
        
        // Update Java lesson plan hierarchy and prerequisites
        $this->call(UpdateJavaLessonPlanOrder::class);
        
        // Seed practice resources and their associations with practice problems
        $this->call(PracticeResourceSeeder::class);
    }
}
