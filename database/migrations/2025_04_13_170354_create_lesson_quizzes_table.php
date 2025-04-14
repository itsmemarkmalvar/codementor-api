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
        Schema::create('lesson_quizzes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('module_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('difficulty', ['beginner', 'intermediate', 'advanced'])->default('intermediate');
            $table->integer('time_limit_minutes')->default(10);
            $table->integer('passing_score_percent')->default(70);
            $table->integer('points_per_question')->default(10);
            $table->boolean('is_published')->default(false);
            $table->integer('order_index');
            $table->timestamps();
            
            // Foreign key
            $table->foreign('module_id')->references('id')->on('lesson_modules')->onDelete('cascade');
            
            // Ensure proper ordering
            $table->unique(['module_id', 'order_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_quizzes');
    }
};
