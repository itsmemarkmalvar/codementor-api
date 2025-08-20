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
        Schema::create('split_screen_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('topic_id')->nullable();
            $table->string('session_type')->default('comparison'); // comparison, single
            $table->json('ai_models_used'); // ['gemini', 'together'] or ['gemini'] or ['together']
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('total_messages')->default(0);
            $table->integer('engagement_score')->default(0); // Hit threshold tracking
            $table->boolean('quiz_triggered')->default(false);
            $table->boolean('practice_triggered')->default(false);
            $table->string('user_choice')->nullable(); // 'gemini', 'together', 'both', 'neither'
            $table->text('choice_reason')->nullable();
            $table->boolean('clarification_needed')->default(false);
            $table->text('clarification_request')->nullable();
            $table->json('session_metadata')->nullable(); // Additional session data
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('topic_id')->references('id')->on('learning_topics')->onDelete('set null');
            
            // Indexes for performance
            $table->index(['user_id', 'created_at']);
            $table->index(['session_type', 'created_at']);
            $table->index(['user_choice', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('split_screen_sessions');
    }
};
