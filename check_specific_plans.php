<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Check for specific lesson plans
$methodsPlan = App\Models\LessonPlan::where('title', 'Java Methods in Depth')->first();
$fileIOPlan = App\Models\LessonPlan::where('title', 'Java File I/O')->first();

echo "Checking for specific lesson plans:\n\n";

if ($methodsPlan) {
    echo "Java Methods in Depth (ID: {$methodsPlan->id})\n";
    echo "Description: {$methodsPlan->description}\n";
    echo "Prerequisites: " . ($methodsPlan->prerequisites ?: "None") . "\n";
    echo "Modules Count: " . $methodsPlan->modules()->count() . "\n";
} else {
    echo "Java Methods in Depth plan not found\n";
}

echo "-------------------------------------------\n";

if ($fileIOPlan) {
    echo "Java File I/O (ID: {$fileIOPlan->id})\n";
    echo "Description: {$fileIOPlan->description}\n";
    echo "Prerequisites: " . ($fileIOPlan->prerequisites ?: "None") . "\n";
    echo "Modules Count: " . $fileIOPlan->modules()->count() . "\n";
} else {
    echo "Java File I/O plan not found\n";
}

echo "-------------------------------------------\n";

// Check IDs 10 and 11 specifically as these were reported in the earlier check
$plan10 = App\Models\LessonPlan::find(10);
$plan11 = App\Models\LessonPlan::find(11);

if ($plan10) {
    echo "Plan ID 10: {$plan10->title}\n";
    echo "Description: {$plan10->description}\n";
    echo "Prerequisites: " . ($plan10->prerequisites ?: "None") . "\n";
} else {
    echo "No plan with ID 10 found\n";
}

echo "-------------------------------------------\n";

if ($plan11) {
    echo "Plan ID 11: {$plan11->title}\n";
    echo "Description: {$plan11->description}\n";
    echo "Prerequisites: " . ($plan11->prerequisites ?: "None") . "\n";
} else {
    echo "No plan with ID 11 found\n";
} 