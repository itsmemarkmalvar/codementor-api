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
        // If user_progress table doesn't exist yet, create it first
        if (!Schema::hasTable('user_progress')) {
            // Create the learning_topics table if it doesn't exist
            if (!Schema::hasTable('learning_topics')) {
                Schema::create('learning_topics', function (Blueprint $table) {
                    $table->id();
                    $table->string('title');
                    $table->text('description')->nullable();
                    $table->string('difficulty_level')->default('beginner');
                    $table->integer('order')->default(0);
                    $table->unsignedBigInteger('parent_id')->nullable();
                    $table->text('learning_objectives')->nullable();
                    $table->text('prerequisites')->nullable();
                    $table->string('estimated_hours')->nullable();
                    $table->string('icon')->nullable();
                    $table->boolean('is_active')->default(true);
                    $table->timestamps();
                });
            }
            
            // Now create the user_progress table
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
                $table->json('completed_subtopics')->nullable();
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

        // Now we can add the progress_data column
        Schema::table('user_progress', function (Blueprint $table) {
            if (!Schema::hasColumn('user_progress', 'progress_data')) {
                $table->json('progress_data')->nullable()->after('completed_subtopics');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_progress', function (Blueprint $table) {
            $table->dropColumn('progress_data');
        });
    }
};
