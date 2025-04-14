<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('exercise_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('exercise_id');
            $table->integer('attempt_number');
            $table->text('submitted_code')->nullable();
            $table->json('submitted_answer')->nullable(); // For non-coding exercises
            $table->boolean('is_correct')->default(false);
            $table->integer('score')->default(0);
            $table->json('test_results')->nullable(); // Results of running test cases
            $table->text('feedback')->nullable(); // AI feedback on the attempt
            $table->json('hints_used')->nullable(); // Track which hints were used
            $table->integer('time_spent_seconds')->default(0);
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('exercise_id')->references('id')->on('lesson_exercises')->onDelete('cascade');
            
            // Each user can have multiple attempts at an exercise, but each attempt must have a unique number
            $table->unique(['user_id', 'exercise_id', 'attempt_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exercise_attempts');
    }
};
