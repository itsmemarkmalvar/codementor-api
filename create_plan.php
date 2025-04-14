<?php
require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\LessonPlan;
use App\Models\LessonModule;
use App\Models\LearningTopic;

// Get the first topic id
$topic = LearningTopic::first();

if (!$topic) {
    echo "No topics found. Please run the topic seeder first.\n";
    exit;
}

echo "Using topic: " . $topic->title . " (ID: " . $topic->id . ")\n";

// Create a lesson plan
$plan = LessonPlan::create([
    'topic_id' => $topic->id,
    'title' => 'Introduction to ' . $topic->title,
    'description' => 'A comprehensive introduction to ' . $topic->title . ' concepts and practices.',
    'learning_objectives' => 'Understand the basics of ' . $topic->title,
    'prerequisites' => 'None',
    'estimated_minutes' => 60,
    'difficulty_level' => 1,
    'is_published' => true
]);

echo "Created lesson plan: " . $plan->title . " (ID: " . $plan->id . ")\n";

// Create a module
$module = LessonModule::create([
    'lesson_plan_id' => $plan->id,
    'title' => 'Getting Started with ' . $topic->title,
    'order_index' => 0,
    'description' => 'Learn the fundamentals of ' . $topic->title,
    'content' => 'Here is where detailed content would go for ' . $topic->title,
    'examples' => 'Example 1: Basic usage of ' . $topic->title,
    'key_points' => 'Key points about ' . $topic->title,
    'estimated_minutes' => 30,
    'is_published' => true
]);

echo "Created module: " . $module->title . " (ID: " . $module->id . ")\n";

echo "Done! You can now try accessing the lesson plan in the application.\n"; 