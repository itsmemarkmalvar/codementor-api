<?php
require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\LearningTopic;
use App\Models\LessonPlan;
use App\Models\LessonModule;
use App\Models\LessonExercise;

// Get all topics
$topics = LearningTopic::get(['id', 'title']);
echo "Found " . $topics->count() . " topics in the database:\n";

$missingLessonPlans = [];
$incompleteLessonPlans = [];

foreach ($topics as $topic) {
    echo "\n=== Topic #{$topic->id}: {$topic->title} ===\n";
    
    // Check if there are lesson plans for this topic
    $plans = LessonPlan::where('topic_id', $topic->id)->get();
    if ($plans->isEmpty()) {
        echo "  No lesson plans found for this topic.\n";
        $missingLessonPlans[] = $topic->title;
        continue;
    }
    
    echo "  Found " . $plans->count() . " lesson plan(s):\n";
    
    foreach ($plans as $plan) {
        echo "  - Plan #{$plan->id}: {$plan->title}\n";
        
        // Check if the lesson plan has modules
        $modules = LessonModule::where('lesson_plan_id', $plan->id)->get();
        if ($modules->isEmpty()) {
            echo "    No modules found for this lesson plan.\n";
            $incompleteLessonPlans[] = $plan->title;
            continue;
        }
        
        echo "    Found " . $modules->count() . " module(s):\n";
        
        foreach ($modules as $module) {
            echo "    - Module #{$module->id}: {$module->title}\n";
            
            // Check if the module has exercises
            $exercises = LessonExercise::where('module_id', $module->id)->get();
            if ($exercises->isEmpty()) {
                echo "      No exercises found for this module.\n";
            } else {
                echo "      Found " . $exercises->count() . " exercise(s).\n";
            }
        }
    }
}

echo "\n\n=== SUMMARY ===\n";
echo "Topics without lesson plans (" . count($missingLessonPlans) . "):\n";
if (empty($missingLessonPlans)) {
    echo "  All topics have at least one lesson plan.\n";
} else {
    foreach ($missingLessonPlans as $topicTitle) {
        echo "  - {$topicTitle}\n";
    }
}

echo "\nIncomplete lesson plans (" . count($incompleteLessonPlans) . "):\n";
if (empty($incompleteLessonPlans)) {
    echo "  All lesson plans have at least one module.\n";
} else {
    foreach ($incompleteLessonPlans as $planTitle) {
        echo "  - {$planTitle}\n";
    }
} 