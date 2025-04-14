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
        Schema::create('lesson_modules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lesson_plan_id');
            $table->string('title');
            $table->integer('order_index');
            $table->text('description')->nullable();
            $table->text('content'); // Main teaching material
            $table->text('examples')->nullable(); // Code examples
            $table->text('key_points')->nullable(); // Bullet points of important concepts
            $table->text('guidance_notes')->nullable(); // Notes for the AI tutor
            $table->integer('estimated_minutes')->default(15);
            $table->json('teaching_strategy')->nullable(); // Specific teaching approach for this module
            $table->json('common_misconceptions')->nullable(); // Common issues students face
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            
            // Foreign key
            $table->foreign('lesson_plan_id')->references('id')->on('lesson_plans')->onDelete('cascade');
            
            // Ensure proper ordering
            $table->unique(['lesson_plan_id', 'order_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_modules');
    }
};
