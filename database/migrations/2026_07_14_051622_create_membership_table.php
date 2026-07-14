<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership', function (Blueprint $table) {
            $table->id();
            $table->string('member_code', 50)->unique();
            $table->string('member_desc');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership');
    }
};