<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LessonPlan;
use App\Models\LearningTopic;

class UpdateJavaLessonPlanOrder extends Seeder
{
    /**
     * Run the database seeds to update Java lesson plan order and prerequisites.
     */
    public function run(): void
    {
        // Find the Java Basics topic
        $javaTopic = LearningTopic::where('title', 'Java Basics')->first();
        
        if (!$javaTopic) {
            $this->command->info('Java Basics topic not found. Skipping order update.');
            return;
        }
        
        // Define the hierarchical order of Java lesson plans
        $lessonPlanOrder = [
            'Java Fundamentals' => [
                'order' => 1,
                'prerequisites' => null,
                'description' => 'Step 1: Learn the fundamental building blocks of Java programming language.'
            ],
            'Java Control Flow' => [
                'order' => 2,
                'prerequisites' => 'Java Fundamentals',
                'description' => 'Step 2: Master control flow statements like conditionals and loops.'
            ],
            'Java Methods in Depth' => [
                'order' => 3,
                'prerequisites' => 'Java Control Flow',
                'description' => 'Step 3: Learn to create and use methods to organize and reuse code.'
            ],
            'Java Object-Oriented Programming' => [
                'order' => 4,
                'prerequisites' => 'Java Methods in Depth',
                'description' => 'Step 4: Understand object-oriented concepts like classes, objects, and inheritance.'
            ],
            'Java Exception Handling' => [
                'order' => 5,
                'prerequisites' => 'Java Object-Oriented Programming',
                'description' => 'Step 5: Master exception handling to build robust applications.'
            ],
            'Java File I/O' => [
                'order' => 6,
                'prerequisites' => 'Java Exception Handling',
                'description' => 'Step 6: Learn to read from and write to files in Java applications.'
            ],
            'Java Data Structures' => [
                'order' => 7,
                'prerequisites' => 'Java Object-Oriented Programming',
                'description' => 'Step 7: Explore essential data structures and the Java Collections Framework.'
            ],
            'Java Concurrency' => [
                'order' => 8,
                'prerequisites' => 'Java Data Structures',
                'description' => 'Step 8: Learn advanced multithreading and concurrency concepts.'
            ]
        ];
        
        $this->command->info('Updating Java lesson plan hierarchy and prerequisites...');
        
        // Update each lesson plan
        foreach ($lessonPlanOrder as $title => $details) {
            $lessonPlan = LessonPlan::where('title', $title)
                ->where('topic_id', $javaTopic->id)
                ->first();
                
            if ($lessonPlan) {
                $lessonPlan->description = $details['description'];
                $lessonPlan->prerequisites = $details['prerequisites'];
                // If you have an order/sequence field, update it: $lessonPlan->sequence_order = $details['order'];
                $lessonPlan->save();
                
                $this->command->info("Updated {$title} lesson plan (ID: {$lessonPlan->id})");
            } else {
                $this->command->warn("Lesson plan '{$title}' not found");
            }
        }
        
        $this->command->info('Java lesson plan hierarchy updated successfully.');
    }
} 