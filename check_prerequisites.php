<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Get all Java lesson plans
$lessonPlans = App\Models\LessonPlan::where('title', 'like', 'Java%')
    ->get();

echo "Java Lesson Plan Hierarchy:\n\n";

foreach ($lessonPlans as $plan) {
    echo "#{$plan->id}: {$plan->title}\n";
    echo "Description: {$plan->description}\n";
    echo "Prerequisites: " . ($plan->prerequisites ?: "None") . "\n";
    echo "Modules Count: " . $plan->modules()->count() . "\n";
    echo "-------------------------------------------\n";
} 