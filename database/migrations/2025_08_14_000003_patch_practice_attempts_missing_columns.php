<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('practice_attempts')) { return; }

        Schema::table('practice_attempts', function (Blueprint $table) {
            if (!Schema::hasColumn('practice_attempts', 'compiler_errors')) {
                $table->json('compiler_errors')->nullable()->after('hints_used');
            }
            if (!Schema::hasColumn('practice_attempts', 'runtime_errors')) {
                $table->json('runtime_errors')->nullable()->after('compiler_errors');
            }
            if (!Schema::hasColumn('practice_attempts', 'test_case_results')) {
                $table->json('test_case_results')->nullable()->after('runtime_errors');
            }
            if (!Schema::hasColumn('practice_attempts', 'execution_time_ms')) {
                $table->integer('execution_time_ms')->nullable()->after('test_case_results');
            }
            if (!Schema::hasColumn('practice_attempts', 'memory_usage_kb')) {
                $table->integer('memory_usage_kb')->nullable()->after('execution_time_ms');
            }
            if (!Schema::hasColumn('practice_attempts', 'feedback')) {
                $table->text('feedback')->nullable()->after('memory_usage_kb');
            }
            if (!Schema::hasColumn('practice_attempts', 'status')) {
                $table->string('status')->default('started')->after('feedback');
            }
            if (!Schema::hasColumn('practice_attempts', 'struggle_points')) {
                $table->json('struggle_points')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('practice_attempts')) { return; }
        Schema::table('practice_attempts', function (Blueprint $table) {
            foreach (['compiler_errors','runtime_errors','test_case_results','execution_time_ms','memory_usage_kb','feedback','status','struggle_points'] as $col) {
                if (Schema::hasColumn('practice_attempts', $col)) { $table->dropColumn($col); }
            }
        });
    }
};


