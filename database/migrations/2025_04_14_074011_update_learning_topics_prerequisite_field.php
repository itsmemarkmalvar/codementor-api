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
        Schema::table('learning_topics', function (Blueprint $table) {
            // Change the prerequisites field to text type to allow longer strings
            $table->text('prerequisites')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('learning_topics', function (Blueprint $table) {
            // Change it back to original type
            $table->string('prerequisites')->nullable()->change();
        });
    }
};
