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
        Schema::create('practice_resources', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('url');
            $table->string('type')->default('article'); // article, video, documentation, course
            $table->string('source')->nullable(); // platform or website
            $table->boolean('is_premium')->default(false);
            $table->string('difficulty_level')->default('intermediate'); // beginner, intermediate, advanced, all
            $table->integer('estimated_time_minutes')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->boolean('is_official')->default(false);
            $table->float('rating')->default(0);
            $table->integer('views')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('practice_resources');
    }
}; 