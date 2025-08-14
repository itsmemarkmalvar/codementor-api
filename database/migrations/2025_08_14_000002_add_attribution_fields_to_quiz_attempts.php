<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('quiz_attempts')) { return; }

        Schema::table('quiz_attempts', function (Blueprint $table) {
            if (!Schema::hasColumn('quiz_attempts', 'attribution_chat_message_id')) {
                $table->unsignedBigInteger('attribution_chat_message_id')->nullable()->after('completed_at');
            }
            if (!Schema::hasColumn('quiz_attempts', 'attribution_model')) {
                $table->string('attribution_model', 20)->nullable()->after('attribution_chat_message_id');
            }
            if (!Schema::hasColumn('quiz_attempts', 'attribution_confidence')) {
                $table->enum('attribution_confidence', ['explicit', 'session', 'temporal'])->nullable()->after('attribution_model');
            }
            if (!Schema::hasColumn('quiz_attempts', 'attribution_delay_sec')) {
                $table->integer('attribution_delay_sec')->nullable()->after('attribution_confidence');
            }
        });

        Schema::table('quiz_attempts', function (Blueprint $table) {
            try {
                $table->foreign('attribution_chat_message_id')
                    ->references('id')->on('chat_messages')
                    ->onDelete('set null');
            } catch (\Throwable $e) {}
            $table->index(['attribution_chat_message_id'], 'qa_attr_msg_idx');
            $table->index(['user_id', 'created_at'], 'qa_user_created_idx');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('quiz_attempts')) { return; }
        Schema::table('quiz_attempts', function (Blueprint $table) {
            try { $table->dropForeign(['attribution_chat_message_id']); } catch (\Throwable $e) {}
            try { $table->dropIndex('qa_attr_msg_idx'); } catch (\Throwable $e) {}
            if (Schema::hasColumn('quiz_attempts', 'attribution_delay_sec')) { $table->dropColumn('attribution_delay_sec'); }
            if (Schema::hasColumn('quiz_attempts', 'attribution_confidence')) { $table->dropColumn('attribution_confidence'); }
            if (Schema::hasColumn('quiz_attempts', 'attribution_model')) { $table->dropColumn('attribution_model'); }
            if (Schema::hasColumn('quiz_attempts', 'attribution_chat_message_id')) { $table->dropColumn('attribution_chat_message_id'); }
        });
    }
};



