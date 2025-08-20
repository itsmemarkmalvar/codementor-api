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
        Schema::create('preserved_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('user_id'); // User identifier
            $table->string('session_identifier')->unique(); // user_id + creation_timestamp
            $table->unsignedBigInteger('topic_id')->nullable(); // Foreign key to learning_topics
            $table->unsignedBigInteger('lesson_id')->nullable(); // Foreign key to lesson_modules
            $table->json('conversation_history')->nullable(); // Store conversation messages
            $table->json('session_metadata')->nullable(); // Additional session data
            $table->boolean('is_active')->default(true); // Session status
            $table->timestamp('last_activity')->nullable(); // Last user interaction
            $table->string('session_type')->default('comparison'); // Type of session
            $table->json('ai_models_used')->nullable(); // AI models used in session
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'is_active']);
            $table->index('last_activity');
            $table->index(['user_id', 'topic_id']);
            
            // Foreign key constraints
            $table->foreign('topic_id')->references('id')->on('learning_topics')->onDelete('set null');
            $table->foreign('lesson_id')->references('id')->on('lesson_modules')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('preserved_sessions');
    }
};
