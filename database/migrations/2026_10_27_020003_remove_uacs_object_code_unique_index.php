<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uacs', function (Blueprint $table): void {
            $table->dropUnique('uacs_object_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('uacs', function (Blueprint $table): void {
            $table->unique('object_code');
        });
    }
};
