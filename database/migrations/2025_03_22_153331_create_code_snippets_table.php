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
        Schema::create('code_snippets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('session_id')->nullable();
            $table->string('title')->nullable();
            $table->text('code');
            $table->string('language')->default('java');
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'saved', 'executed'])->default('draft');
            $table->text('execution_result')->nullable(); // Store the result of code execution
            $table->json('ai_feedback')->nullable(); // Store AI feedback on the code
            $table->boolean('is_favorite')->default(false);
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('session_id')->references('id')->on('learning_sessions')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('code_snippets');
    }
};
