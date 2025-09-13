<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_lesson_completions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('lesson_plan_id');
            $table->timestamp('completed_at')->nullable();
            // source: modules | engagement | both
            $table->string('source')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'lesson_plan_id'], 'ulc_user_lesson_unique');
            $table->index(['lesson_plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_lesson_completions');
    }
};


