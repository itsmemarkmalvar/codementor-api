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
            
            // Foreign key for hierarchical topics
            $table->foreign('parent_id')->references('id')->on('learning_topics')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_topics');
    }
};
