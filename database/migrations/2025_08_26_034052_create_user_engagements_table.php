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
        Schema::create('user_engagements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('lesson_id');
            $table->string('session_id')->nullable();
            $table->integer('engagement_score')->default(0);
            $table->integer('total_events')->default(0);
            $table->integer('session_duration_seconds')->default(0);
            $table->boolean('is_threshold_reached')->default(false);
            $table->enum('triggered_activity', ['quiz', 'practice', ''])->nullable();
            $table->enum('assessment_sequence', ['quiz', 'practice', ''])->nullable();
            $table->timestamp('last_activity')->useCurrent();
            $table->timestamps();

            // Indexes
            $table->unique(['user_id', 'lesson_id', 'session_id'], 'unique_user_lesson_session');
            $table->index(['user_id', 'lesson_id'], 'user_engagements_user_id_lesson_id_index');
            $table->index('session_id', 'user_engagements_session_id_index');

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('lesson_id')->references('id')->on('lesson_modules')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_engagements');
    }
};
