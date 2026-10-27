<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uacs', function (Blueprint $table): void {
            $table->string('classification', 255)->nullable()->change();
            $table->string('sub_class', 255)->nullable()->change();
            $table->string('group', 255)->nullable()->change();
            $table->string('object_code', 255)->nullable()->change();
            $table->string('sub_object_code', 255)->nullable()->change();
            $table->string('uacs', 20)->nullable()->change();
            $table->string('status', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Intentionally left nullable: imported hierarchy rows may not have
        // values for every spreadsheet column.
    }
};
