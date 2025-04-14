<?php
require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\LessonPlan;
use App\Models\LessonModule;
use App\Models\LessonExercise;

// Check if lesson plans exist
$plans = LessonPlan::all();
echo "Found " . $plans->count() . " lesson plans:\n";

foreach ($plans as $plan) {
    echo "- ID: " . $plan->id . ", Title: " . $plan->title . ", Topic ID: " . $plan->topic_id . "\n";
    
    // Get modules for this plan
    $modules = $plan->modules;
    echo "  Contains " . $modules->count() . " modules:\n";
    
    foreach ($modules as $module) {
        echo "  - Module: " . $module->title . "\n";
        
        // Get exercises for this module
        $exercises = $module->exercises;
        echo "    Contains " . $exercises->count() . " exercises:\n";
        
        foreach ($exercises as $exercise) {
            echo "    - Exercise: " . $exercise->title . "\n";
        }
    }
    
    echo "\n";
}

if ($plans->count() == 0) {
    echo "No lesson plans found in the database.\n";
    
    // Check if learning topics exist
    $topicCount = \App\Models\LearningTopic::count();
    echo "There are " . $topicCount . " learning topics in the database.\n";
    
    // Check specifically for Java Basics topic
    $javaTopic = \App\Models\LearningTopic::where('title', 'Java Basics')->first();
    if ($javaTopic) {
        echo "Found Java Basics topic with ID: " . $javaTopic->id . "\n";
    } else {
        echo "Java Basics topic not found.\n";
    }
} 