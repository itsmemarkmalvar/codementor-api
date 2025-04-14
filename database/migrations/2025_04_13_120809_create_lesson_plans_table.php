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
        Schema::create('lesson_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('topic_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('learning_objectives');
            $table->text('prerequisites')->nullable();
            $table->integer('estimated_minutes')->default(60);
            $table->json('resources')->nullable(); // External links, references
            $table->text('instructor_notes')->nullable();
            $table->integer('difficulty_level')->default(1); // 1-5 scale
            $table->integer('modules_count')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            
            // Foreign key
            $table->foreign('topic_id')->references('id')->on('learning_topics')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_plans');
    }
};
