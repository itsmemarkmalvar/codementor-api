<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cleanup duplicate learning_topics titles, keep lowest ID
        if (Schema::hasTable('learning_topics')) {
            $dupes = DB::table('learning_topics')
                ->select('title', DB::raw('COUNT(*) as c'))
                ->groupBy('title')
                ->having('c', '>', 1)
                ->pluck('title');

            foreach ($dupes as $title) {
                $rows = DB::table('learning_topics')->where('title', $title)->orderBy('id')->get();
                $keepId = $rows->first()->id ?? null;
                if ($keepId) {
                    DB::table('learning_topics')
                        ->where('title', $title)
                        ->where('id', '!=', $keepId)
                        ->delete();
                }
            }
        }

        // Cleanup duplicate lesson_plans per (topic_id, title), keep lowest ID
        if (Schema::hasTable('lesson_plans')) {
            $dupePlans = DB::table('lesson_plans')
                ->select('topic_id', 'title', DB::raw('COUNT(*) as c'))
                ->groupBy('topic_id', 'title')
                ->having('c', '>', 1)
                ->get();

            foreach ($dupePlans as $d) {
                $rows = DB::table('lesson_plans')
                    ->where('topic_id', $d->topic_id)
                    ->where('title', $d->title)
                    ->orderBy('id')
                    ->get();
                $keepId = $rows->first()->id ?? null;
                if ($keepId) {
                    DB::table('lesson_plans')
                        ->where('topic_id', $d->topic_id)
                        ->where('title', $d->title)
                        ->where('id', '!=', $keepId)
                        ->delete();
                }
            }
        }

        Schema::table('learning_topics', function (Blueprint $table) {
            if (!Schema::hasColumn('learning_topics', 'title')) { return; }
            // Add unique index if not exists
            try {
                $table->unique('title');
            } catch (\Exception $e) {
                // Index might already exist, ignore error
            }
        });

        Schema::table('lesson_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('lesson_plans', 'title')) { return; }
            if (!Schema::hasColumn('lesson_plans', 'topic_id')) { return; }
            try {
                $table->unique(['topic_id', 'title']);
            } catch (\Exception $e) {
                // Index might already exist, ignore error
            }
        });
    }

    public function down(): void
    {
        Schema::table('learning_topics', function (Blueprint $table) {
            $table->dropUnique('learning_topics_title_unique');
        });
        Schema::table('lesson_plans', function (Blueprint $table) {
            $table->dropUnique('lesson_plans_topic_id_title_unique');
        });
    }
};


