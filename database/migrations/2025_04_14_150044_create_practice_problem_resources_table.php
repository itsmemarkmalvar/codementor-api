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
        Schema::create('practice_problem_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_problem_id')->constrained()->onDelete('cascade');
            $table->foreignId('practice_resource_id')->constrained()->onDelete('cascade');
            $table->float('relevance_score')->default(1.0);
            $table->timestamps();
            
            // Ensure a resource can only be linked to a problem once
            // Use a shorter name for the unique index
            $table->unique(['practice_problem_id', 'practice_resource_id'], 'ppr_unique_problem_resource');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('practice_problem_resources');
    }
};
