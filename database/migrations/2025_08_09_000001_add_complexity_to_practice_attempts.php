<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('practice_attempts', function (Blueprint $table) {
            if (!Schema::hasColumn('practice_attempts', 'complexity_score')) {
                $table->decimal('complexity_score', 5, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('practice_attempts', function (Blueprint $table) {
            if (Schema::hasColumn('practice_attempts', 'complexity_score')) {
                $table->dropColumn('complexity_score');
            }
        });
    }
};


