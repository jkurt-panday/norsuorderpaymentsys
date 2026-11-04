<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('graduate_ledgers', function (Blueprint $table): void {
            $table->string('particulars')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('graduate_ledgers', function (Blueprint $table): void {
            $table->string('particulars')->nullable(false)->change();
        });
    }
};
