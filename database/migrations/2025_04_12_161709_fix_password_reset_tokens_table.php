<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add the previous migration to the migrations table
        // This will mark it as complete without running it again
        if (!DB::table('migrations')->where('migration', '2025_03_18_051227_create_password_reset_tokens_table')->exists()) {
            DB::table('migrations')->insert([
                'migration' => '2025_03_18_051227_create_password_reset_tokens_table',
                'batch' => 1
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the migration record
        DB::table('migrations')->where('migration', '2025_03_18_051227_create_password_reset_tokens_table')->delete();
    }
};
