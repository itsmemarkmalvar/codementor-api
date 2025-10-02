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
            // This migration was run to fix foreign key constraints
            // The exact changes would depend on what was broken
            // Since this is a fix migration, we'll make it idempotent
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert any changes if needed
    }
};
