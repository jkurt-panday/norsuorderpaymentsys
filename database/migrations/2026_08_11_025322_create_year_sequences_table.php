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
        Schema::create('year_sequences', function (Blueprint $table) {
            $table->id();
            $table->year('year')->unique();
            $table->unsignedTinyInteger('month');
            $table->unsignedInteger('current_number')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('year_sequences');
    }
};
