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
        Schema::create('engagement_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_engagement_id');
            $table->enum('event_type', [
                'message',
                'code_execution',
                'scroll',
                'interaction',
                'time',
                'quiz_completion',
                'practice_completion',
                'lesson_start',
                'lesson_complete'
            ]);
            $table->integer('points');
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['user_engagement_id', 'event_type'], 'engagement_events_user_engagement_id_event_type_index');
            $table->index('created_at', 'engagement_events_created_at_index');

            // Foreign keys
            $table->foreign('user_engagement_id')->references('id')->on('user_engagements')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('engagement_events');
    }
};
