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
        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quiz_id');
            $table->text('question_text');
            $table->enum('type', ['multiple_choice', 'true_false', 'fill_in_blank', 'code_snippet'])->default('multiple_choice');
            $table->json('options')->nullable(); // JSON array of options for multiple choice
            $table->json('correct_answers'); // JSON array of correct answers or answer patterns
            $table->text('explanation')->nullable(); // Explanation of the correct answer
            $table->integer('points')->default(10);
            $table->text('code_snippet')->nullable(); // For code-related questions
            $table->integer('order_index');
            $table->timestamps();
            
            // Foreign key
            $table->foreign('quiz_id')->references('id')->on('lesson_quizzes')->onDelete('cascade');
            
            // Ensure proper ordering
            $table->unique(['quiz_id', 'order_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_questions');
    }
};
