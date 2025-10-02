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
        Schema::table('split_screen_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('lesson_id')->nullable()->after('topic_id');
            $table->foreign('lesson_id')->references('id')->on('lesson_plans')->onDelete('set null');
            $table->index(['user_id', 'lesson_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('split_screen_sessions', function (Blueprint $table) {
            $table->dropForeign(['lesson_id']);
            $table->dropIndex(['user_id', 'lesson_id', 'created_at']);
            $table->dropColumn('lesson_id');
        });
    }
};

