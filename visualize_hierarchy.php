<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Get all Java lesson plans
$lessonPlans = App\Models\LessonPlan::where('title', 'like', 'Java%')->get();

// Build a hierarchical structure
$hierarchy = [];
$foundationPlans = [];

// First, find foundation plans with no prerequisites
foreach ($lessonPlans as $plan) {
    if (empty($plan->prerequisites)) {
        $foundationPlans[] = $plan;
        $hierarchy[$plan->title] = [
            'plan' => $plan,
            'level' => 1,
            'dependents' => []
        ];
    }
}

// Next, build up the hierarchy level by level
$maxLevel = 10; // Prevent infinite loops
$hasChanges = true;

for ($level = 2; $level <= $maxLevel && $hasChanges; $level++) {
    $hasChanges = false;
    
    foreach ($lessonPlans as $plan) {
        // Skip if already placed in hierarchy
        if (isset($hierarchy[$plan->title])) {
            continue;
        }
        
        // Check if this plan's prerequisite is already in the hierarchy
        if (!empty($plan->prerequisites) && isset($hierarchy[$plan->prerequisites])) {
            $hierarchy[$plan->title] = [
                'plan' => $plan,
                'level' => $level,
                'dependents' => []
            ];
            
            // Add this plan as a dependent of its prerequisite
            $hierarchy[$plan->prerequisites]['dependents'][] = $plan->title;
            
            $hasChanges = true;
        }
    }
}

// Display the hierarchy
echo "Java Learning Path Hierarchy:\n\n";

// Function to display the hierarchy with indentation
function displayHierarchy($hierarchy, $currentPlan, $indent = 0) {
    $plan = $hierarchy[$currentPlan]['plan'];
    $level = $hierarchy[$currentPlan]['level'];
    $dependents = $hierarchy[$currentPlan]['dependents'];
    
    echo str_repeat('  ', $indent) . "{$level}. {$plan->title} (ID: {$plan->id})\n";
    echo str_repeat('  ', $indent) . "   Description: {$plan->description}\n";
    echo str_repeat('  ', $indent) . "   Prerequisites: " . ($plan->prerequisites ?: "None") . "\n";
    echo str_repeat('  ', $indent) . "   Modules: " . $plan->modules()->count() . "\n";
    
    foreach ($dependents as $dependent) {
        displayHierarchy($hierarchy, $dependent, $indent + 1);
    }
}

// Display starting from foundation plans
foreach ($foundationPlans as $plan) {
    displayHierarchy($hierarchy, $plan->title);
    echo "\n";
}

// List any orphaned plans (those not connected to the hierarchy)
$orphanedPlans = [];
foreach ($lessonPlans as $plan) {
    if (!isset($hierarchy[$plan->title])) {
        $orphanedPlans[] = $plan;
    }
}

if (count($orphanedPlans) > 0) {
    echo "Orphaned Plans (not connected to hierarchy):\n";
    foreach ($orphanedPlans as $plan) {
        echo "{$plan->title} (ID: {$plan->id}) - Prerequisite: " . ($plan->prerequisites ?: "None") . "\n";
    }
} 