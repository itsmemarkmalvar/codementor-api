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
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('session_id')->nullable()->after('user_id');
            $table->foreign('session_id')->references('id')->on('split_screen_sessions')->onDelete('set null');
            $table->index(['session_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropForeign(['session_id']);
            $table->dropIndex(['session_id', 'created_at']);
            $table->dropColumn('session_id');
        });
    }
};
