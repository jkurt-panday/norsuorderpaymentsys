<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('law_school_ledgers', function (Blueprint $table) {
            $table->date('transaction_date')->nullable()->change();
        });

        Schema::table('graduate_ledgers', function (Blueprint $table) {
            $table->date('transaction_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('law_school_ledgers', function (Blueprint $table) {
            $table->date('transaction_date')->nullable(false)->change();
        });

        Schema::table('graduate_ledgers', function (Blueprint $table) {
            $table->date('transaction_date')->nullable(false)->change();
        });
    }
};
