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
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('quiz_id');
            $table->integer('score')->default(0);
            $table->integer('max_possible_score');
            $table->float('percentage', 5, 2)->default(0.00);
            $table->boolean('passed')->default(false);
            $table->json('question_responses'); // JSON with question IDs and user responses
            $table->json('correct_questions')->nullable(); // Array of question IDs answered correctly
            $table->integer('time_spent_seconds')->default(0);
            $table->integer('attempt_number')->default(1);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('quiz_id')->references('id')->on('lesson_quizzes')->onDelete('cascade');
            
            // Index for faster lookups
            $table->index(['user_id', 'quiz_id', 'attempt_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
    }
};
