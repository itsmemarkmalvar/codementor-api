<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LearningTopic;

class TopicSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seed only Java programming topics
        $topics = [
            [
                'title' => 'Java Basics',
                'description' => 'Learn the fundamentals of Java programming language.',
                'difficulty_level' => 'beginner',
                'order' => 1,
                'learning_objectives' => 'Understand Java syntax, variables, data types, and control structures.',
                'estimated_hours' => '10',
                'is_active' => true,
            ],
            [
                'title' => 'Java Advanced Concepts',
                'description' => 'Advanced Java programming techniques and patterns.',
                'difficulty_level' => 'intermediate',
                'order' => 2,
                'learning_objectives' => 'Master advanced Java concepts like concurrency, networking, and design patterns.',
                'estimated_hours' => '15',
                'is_active' => true,
            ],
            [
                'title' => 'Java Enterprise Development',
                'description' => 'Enterprise application development with Java.',
                'difficulty_level' => 'advanced',
                'order' => 3,
                'learning_objectives' => 'Build enterprise applications using Spring, Hibernate, and related technologies.',
                'estimated_hours' => '20',
                'is_active' => true,
            ],
        ];

        foreach ($topics as $topic) {
            LearningTopic::create($topic);
        }
    }
} 