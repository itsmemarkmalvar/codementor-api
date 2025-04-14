<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Get all Java lesson plans
$lessonPlans = App\Models\LessonPlan::where('title', 'like', 'Java%')->get();

echo "Found " . $lessonPlans->count() . " Java lesson plans:\n\n";

foreach ($lessonPlans as $plan) {
    echo "- {$plan->title} (ID: {$plan->id})\n";
} 