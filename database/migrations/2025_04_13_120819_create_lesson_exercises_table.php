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
        Schema::create('lesson_exercises', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('module_id');
            $table->string('title');
            $table->enum('type', ['coding', 'multiple_choice', 'fill_in_blank', 'debugging', 'code_review']);
            $table->text('description');
            $table->text('instructions');
            $table->text('starter_code')->nullable(); // For coding exercises
            $table->json('test_cases')->nullable(); // For automated verification
            $table->json('expected_output')->nullable();
            $table->json('hints')->nullable(); // Progressive hints
            $table->json('solution')->nullable(); // Reference solution
            $table->integer('difficulty')->default(1); // 1-5 scale
            $table->integer('points')->default(10);
            $table->integer('order_index');
            $table->boolean('is_required')->default(true);
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
        Schema::dropIfExists('lesson_exercises');
    }
};
