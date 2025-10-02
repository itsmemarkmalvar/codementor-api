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
        Schema::create('engagement_analytics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('lesson_id');
            $table->integer('total_sessions')->default(0);
            $table->integer('total_engagement_score')->default(0);
            $table->integer('average_session_duration')->default(0);
            $table->decimal('completion_rate', 5, 2)->default(0.00);
            $table->integer('total_events')->default(0);
            $table->json('events_by_type')->nullable();
            $table->timestamp('last_engaged_at')->useCurrent();
            $table->timestamps();

            // Indexes
            $table->unique(['user_id', 'lesson_id'], 'unique_user_lesson_analytics');
            $table->index('user_id', 'engagement_analytics_user_id_index');
            $table->index('lesson_id', 'engagement_analytics_lesson_id_index');
            $table->index('last_engaged_at', 'engagement_analytics_last_engaged_at_index');

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
        Schema::dropIfExists('engagement_analytics');
    }
};
