<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bankaccount_info', function (Blueprint $table) {
            $table->id();
            $table->string('account_name');
            $table->string('bank_name');
            $table->string('account_num', 50);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bankaccount_info');
    }
};
