<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DATABASE SCHEMA CHECK ===\n\n";

try {
    // Check AI preference logs schema
    echo "AI Preference Logs Schema:\n";
    $columns = DB::select('DESCRIBE ai_preference_logs');
    foreach ($columns as $col) {
        echo "  {$col->Field} ({$col->Type})\n";
    }
    
    echo "\nSplit Screen Sessions Schema:\n";
    $columns = DB::select('DESCRIBE split_screen_sessions');
    foreach ($columns as $col) {
        echo "  {$col->Field} ({$col->Type})\n";
    }
    
    echo "\nPractice Attempts Schema:\n";
    $columns = DB::select('DESCRIBE practice_attempts');
    foreach ($columns as $col) {
        echo "  {$col->Field} ({$col->Type})\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
