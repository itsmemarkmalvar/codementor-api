<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration fixes the order dependency issues by creating the learning_sessions table first
     * and then allowing the chat_messages table to reference it correctly.
     */
    public function up(): void
    {
        // First, create the learning_topics table if it doesn't exist
        if (!Schema::hasTable('learning_topics')) {
            Schema::create('learning_topics', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('difficulty_level')->default('beginner'); // beginner, intermediate, advanced
                $table->integer('order')->default(0); // For ordering topics in a curriculum
                $table->unsignedBigInteger('parent_id')->nullable(); // For hierarchical topics
                $table->text('learning_objectives')->nullable(); // JSON or comma-separated list
                $table->text('prerequisites')->nullable(); // JSON or comma-separated list
                $table->string('estimated_hours')->nullable(); // Estimated hours to complete
                $table->string('icon')->nullable(); // Icon for UI representation
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Then create the learning_sessions table if it doesn't exist
        if (!Schema::hasTable('learning_sessions')) {
            Schema::create('learning_sessions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('topic_id')->nullable();
                $table->string('title');
                $table->timestamp('started_at');
                $table->timestamp('last_activity_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                
                // Foreign keys
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                if (Schema::hasTable('learning_topics')) {
                    $table->foreign('topic_id')->references('id')->on('learning_topics')->onDelete('set null');
                }
            });
        }

        // Mark the migrations as completed in the migrations table
        $migrations = [
            '2025_03_22_153340_create_learning_topics_table',
            '2025_03_22_153349_create_learning_sessions_table'
        ];

        foreach ($migrations as $migration) {
            if (!DB::table('migrations')->where('migration', $migration)->exists()) {
                DB::table('migrations')->insert([
                    'migration' => $migration,
                    'batch' => 2
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Do nothing in down to avoid data loss
    }
};
