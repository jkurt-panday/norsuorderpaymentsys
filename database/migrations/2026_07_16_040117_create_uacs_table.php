<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uacs', function (Blueprint $table) {
            $table->id();
            $table->string('account_title', 255);
            $table->string('object_code', 50)->unique();
            $table->timestamps();
            
            $table->index('object_code');
            $table->index('account_title');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uacs');
    }
};
