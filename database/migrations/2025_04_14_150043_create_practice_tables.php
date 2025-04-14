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
        // Create practice categories table
        Schema::create('practice_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->integer('display_order')->default(0);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('required_level')->default(0);
            $table->timestamps();
            
            $table->foreign('parent_id')->references('id')->on('practice_categories')->onDelete('set null');
        });
        
        // Create practice problems table
        Schema::create('practice_problems', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->unsignedBigInteger('category_id');
            $table->text('description');
            $table->text('instructions');
            $table->json('requirements')->nullable();
            $table->enum('difficulty_level', ['beginner', 'easy', 'medium', 'hard', 'expert'])->default('easy');
            $table->integer('points')->default(100);
            $table->integer('estimated_time_minutes')->default(30);
            $table->json('complexity_tags')->nullable();
            $table->json('topic_tags')->nullable();
            $table->text('starter_code')->nullable();
            $table->json('test_cases')->nullable();
            $table->text('solution_code')->nullable();
            $table->json('expected_output')->nullable();
            $table->json('hints')->nullable();
            $table->json('learning_concepts')->nullable();
            $table->json('prerequisites')->nullable();
            $table->decimal('success_rate', 5, 2)->default(0);
            $table->boolean('is_featured')->default(false);
            $table->integer('attempts_count')->default(0);
            $table->integer('completion_count')->default(0);
            $table->timestamps();
            
            $table->foreign('category_id')->references('id')->on('practice_categories')->onDelete('cascade');
        });
        
        // Create practice attempts table
        Schema::create('practice_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('problem_id');
            $table->text('submitted_code')->nullable();
            $table->json('execution_result')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->integer('points_earned')->default(0);
            $table->integer('time_spent_seconds')->nullable();
            $table->integer('attempt_number')->default(1);
            $table->integer('last_hint_index')->default(-1);
            $table->json('hints_used')->nullable();
            $table->json('compiler_errors')->nullable();
            $table->json('runtime_errors')->nullable();
            $table->json('test_case_results')->nullable();
            $table->integer('execution_time_ms')->nullable();
            $table->integer('memory_usage_kb')->nullable();
            $table->text('feedback')->nullable();
            $table->string('status')->default('started');
            $table->json('struggle_points')->nullable();
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('problem_id')->references('id')->on('practice_problems')->onDelete('cascade');
            
            $table->index(['user_id', 'problem_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('practice_attempts');
        Schema::dropIfExists('practice_problems');
        Schema::dropIfExists('practice_categories');
    }
}; 