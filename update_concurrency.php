<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Find and update Java Concurrency plan
$concurrencyPlan = App\Models\LessonPlan::where('title', 'Java Concurrency')->first();

if ($concurrencyPlan) {
    echo "Updating Java Concurrency plan (ID: {$concurrencyPlan->id})...\n";
    
    $concurrencyPlan->description = 'Step 8: Learn advanced multithreading and concurrency concepts.';
    $concurrencyPlan->prerequisites = 'Java Data Structures';
    $concurrencyPlan->save();
    
    echo "Updated successfully.\n";
} else {
    echo "Java Concurrency plan not found.\n";
    
    // Try to find by ID if title search fails
    $plan6 = App\Models\LessonPlan::find(6);
    if ($plan6) {
        echo "Found plan with ID 6: {$plan6->title}\n";
        
        $plan6->description = 'Step 8: Learn advanced multithreading and concurrency concepts.';
        $plan6->prerequisites = 'Java Data Structures';
        $plan6->save();
        
        echo "Updated successfully.\n";
    } else {
        echo "No plan with ID 6 found.\n";
    }
} 