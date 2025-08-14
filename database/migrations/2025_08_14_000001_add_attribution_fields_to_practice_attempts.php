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
        if (!Schema::hasTable('practice_attempts')) {
            return;
        }

        Schema::table('practice_attempts', function (Blueprint $table) {
            if (!Schema::hasColumn('practice_attempts', 'attribution_chat_message_id')) {
                $table->unsignedBigInteger('attribution_chat_message_id')->nullable()->after('status');
            }
            if (!Schema::hasColumn('practice_attempts', 'attribution_model')) {
                $table->string('attribution_model', 20)->nullable()->after('attribution_chat_message_id');
            }
            if (!Schema::hasColumn('practice_attempts', 'attribution_confidence')) {
                $table->enum('attribution_confidence', ['explicit', 'session', 'temporal'])->nullable()->after('attribution_model');
            }
            if (!Schema::hasColumn('practice_attempts', 'attribution_delay_sec')) {
                $table->integer('attribution_delay_sec')->nullable()->after('attribution_confidence');
            }
        });

        // Add FK and indexes in a separate Schema::table to avoid platform quirks
        Schema::table('practice_attempts', function (Blueprint $table) {
            try {
                $table->foreign('attribution_chat_message_id')
                    ->references('id')->on('chat_messages')
                    ->onDelete('set null');
            } catch (\Throwable $e) {
                // ignore if already exists
            }
            $table->index(['attribution_chat_message_id'], 'pa_attr_msg_idx');
            $table->index(['user_id', 'created_at'], 'pa_user_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('practice_attempts')) {
            return;
        }
        Schema::table('practice_attempts', function (Blueprint $table) {
            try { $table->dropForeign(['attribution_chat_message_id']); } catch (\Throwable $e) {}
            try { $table->dropIndex('pa_attr_msg_idx'); } catch (\Throwable $e) {}
            // user_created index exists from creation; leave it
            if (Schema::hasColumn('practice_attempts', 'attribution_delay_sec')) {
                $table->dropColumn('attribution_delay_sec');
            }
            if (Schema::hasColumn('practice_attempts', 'attribution_confidence')) {
                $table->dropColumn('attribution_confidence');
            }
            if (Schema::hasColumn('practice_attempts', 'attribution_model')) {
                $table->dropColumn('attribution_model');
            }
            if (Schema::hasColumn('practice_attempts', 'attribution_chat_message_id')) {
                $table->dropColumn('attribution_chat_message_id');
            }
        });
    }
};



