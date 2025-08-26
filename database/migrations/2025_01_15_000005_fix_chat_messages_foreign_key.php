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
            // Drop the old foreign key constraint
            $table->dropForeign(['session_id']);
            
            // Add the new foreign key constraint to preserved_sessions
            $table->foreign('session_id')->references('id')->on('preserved_sessions')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            // Drop the new foreign key constraint
            $table->dropForeign(['session_id']);
            
            // Restore the old foreign key constraint to split_screen_sessions
            $table->foreign('session_id')->references('id')->on('split_screen_sessions')->onDelete('set null');
        });
    }
};
