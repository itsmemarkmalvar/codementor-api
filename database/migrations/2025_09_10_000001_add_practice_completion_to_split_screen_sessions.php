<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('split_screen_sessions', function (Blueprint $table) {
            $table->timestamp('practice_required_at')->nullable()->after('practice_triggered');
            $table->boolean('practice_completed')->default(false)->after('practice_required_at');
            $table->timestamp('practice_completed_at')->nullable()->after('practice_completed');
        });
    }

    public function down(): void
    {
        Schema::table('split_screen_sessions', function (Blueprint $table) {
            $table->dropColumn(['practice_required_at', 'practice_completed', 'practice_completed_at']);
        });
    }
};


