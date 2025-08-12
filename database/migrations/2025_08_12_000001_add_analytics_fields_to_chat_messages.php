<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('chat_messages')) {
            return; // table created by earlier migration in this project
        }

        Schema::table('chat_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('chat_messages', 'model')) {
                $table->string('model', 20)->nullable()->after('preferences');
            }
            if (!Schema::hasColumn('chat_messages', 'response_time_ms')) {
                $table->integer('response_time_ms')->nullable()->after('model');
            }
            if (!Schema::hasColumn('chat_messages', 'is_fallback')) {
                $table->boolean('is_fallback')->default(false)->after('response_time_ms');
            }
            if (!Schema::hasColumn('chat_messages', 'user_rating')) {
                $table->tinyInteger('user_rating')->nullable()->after('is_fallback');
            }
        });

        // Helpful indexes
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'cm_user_created_idx');
            $table->index(['model', 'created_at'], 'cm_model_created_idx');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('chat_messages')) {
            return;
        }
        Schema::table('chat_messages', function (Blueprint $table) {
            if (Schema::hasColumn('chat_messages', 'user_rating')) {
                $table->dropColumn('user_rating');
            }
            if (Schema::hasColumn('chat_messages', 'is_fallback')) {
                $table->dropColumn('is_fallback');
            }
            if (Schema::hasColumn('chat_messages', 'response_time_ms')) {
                $table->dropColumn('response_time_ms');
            }
            if (Schema::hasColumn('chat_messages', 'model')) {
                $table->dropColumn('model');
            }
            // Drop indexes if they exist
            try { $table->dropIndex('cm_user_created_idx'); } catch (\Throwable $e) {}
            try { $table->dropIndex('cm_model_created_idx'); } catch (\Throwable $e) {}
        });
    }
};


