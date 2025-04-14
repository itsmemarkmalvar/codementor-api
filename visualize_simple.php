<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Get all Java lesson plans
$lessonPlans = App\Models\LessonPlan::where('title', 'like', 'Java%')->get();

// Organize them by prerequisites
$plansByPrereq = [];
foreach ($lessonPlans as $plan) {
    $prereq = $plan->prerequisites ?: 'None';
    if (!isset($plansByPrereq[$prereq])) {
        $plansByPrereq[$prereq] = [];
    }
    $plansByPrereq[$prereq][] = $plan;
}

// Display complete learning path
echo "Java Learning Path Hierarchy:\n\n";
echo "1. Foundation Level (No Prerequisites):\n";

// Find foundation plans
if (isset($plansByPrereq['None'])) {
    foreach ($plansByPrereq['None'] as $plan) {
        echo "   - {$plan->title}: {$plan->description}\n";
        displayDependentPlans($plan->title, 2);
    }
}

// Recursive function to display dependent plans
function displayDependentPlans($parentTitle, $level) {
    global $plansByPrereq;
    
    if (isset($plansByPrereq[$parentTitle])) {
        echo str_repeat(' ', $level * 3) . $level . ". After {$parentTitle}:\n";
        
        foreach ($plansByPrereq[$parentTitle] as $plan) {
            echo str_repeat(' ', $level * 3) . "   - {$plan->title}: {$plan->description}\n";
            displayDependentPlans($plan->title, $level + 1);
        }
    }
}

// List all plans in ID order for reference
echo "\n\nAll Java Lesson Plans (by ID):\n\n";
foreach ($lessonPlans->sortBy('id') as $plan) {
    echo "ID {$plan->id}: {$plan->title}\n";
    echo "   Description: {$plan->description}\n";
    echo "   Prerequisites: " . ($plan->prerequisites ?: "None") . "\n";
    echo "   Modules: " . $plan->modules()->count() . "\n\n";
} 