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
        Schema::create('ai_preference_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('session_id')->nullable();
            $table->unsignedBigInteger('topic_id')->nullable();
            $table->enum('interaction_type', ['quiz', 'practice', 'code_execution']);
            $table->enum('chosen_ai', ['gemini', 'together', 'both', 'neither']);
            $table->text('choice_reason')->nullable();
            $table->decimal('performance_score', 5, 2)->nullable();
            $table->decimal('success_rate', 5, 2)->nullable();
            $table->integer('time_spent_seconds')->nullable();
            $table->integer('attempt_count')->default(1);
            $table->enum('difficulty_level', ['beginner', 'easy', 'medium', 'hard', 'expert'])->nullable();
            $table->json('context_data')->nullable();
            $table->unsignedBigInteger('attribution_chat_message_id')->nullable();
            $table->string('attribution_model', 20)->nullable();
            $table->decimal('attribution_confidence', 5, 2)->nullable();
            $table->integer('attribution_delay_sec')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('session_id')->references('id')->on('split_screen_sessions')->onDelete('set null');
            $table->foreign('topic_id')->references('id')->on('learning_topics')->onDelete('set null');
            $table->foreign('attribution_chat_message_id')->references('id')->on('chat_messages')->onDelete('set null');

            // Indexes
            $table->index(['user_id', 'created_at']);
            $table->index(['interaction_type', 'created_at']);
            $table->index(['chosen_ai', 'created_at']);
            $table->index(['attribution_chat_message_id']);
            $table->index(['topic_id', 'interaction_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_preference_logs');
    }
};
