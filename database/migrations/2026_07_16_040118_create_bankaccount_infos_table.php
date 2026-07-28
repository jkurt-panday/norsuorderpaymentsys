<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bankaccount_infos', function (Blueprint $table) {
            $table->id();
            $table->string('account_name', 255);
            $table->string('bank_name', 100);
            $table->string('account_num', 50)->unique();
            $table->timestamps();

            $table->index('bank_name');
            $table->index('account_num');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bankaccount_infos');
    }
};
