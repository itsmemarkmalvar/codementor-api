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
        // Skip this migration if learning_topics doesn't exist yet
        if (!Schema::hasTable('learning_topics')) {
            return;
        }
        
        Schema::create('user_progress', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('topic_id');
            $table->integer('progress_percentage')->default(0);
            $table->enum('status', ['not_started', 'in_progress', 'completed'])->default('not_started');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->integer('time_spent_minutes')->default(0);
            $table->integer('exercises_completed')->default(0);
            $table->integer('exercises_total')->default(0);
            $table->json('completed_subtopics')->nullable(); // JSON array of completed subtopics
            $table->integer('current_streak_days')->default(0);
            $table->dateTime('last_interaction_at')->nullable();
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('topic_id')->references('id')->on('learning_topics')->onDelete('cascade');
            
            // Ensure unique combination of user and topic
            $table->unique(['user_id', 'topic_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_progress');
    }
};
