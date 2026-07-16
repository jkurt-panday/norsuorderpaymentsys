<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->string('member_code', 50)->unique();
            $table->string('member_desc', 255);
            $table->timestamps();
            
            $table->index('member_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};