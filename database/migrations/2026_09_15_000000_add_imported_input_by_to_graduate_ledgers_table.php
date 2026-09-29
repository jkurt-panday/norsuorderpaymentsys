<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('graduate_ledgers', function (Blueprint $table) {
            $table->string('imported_input_by')->nullable()->after('input_by');
        });
    }

    public function down(): void
    {
        Schema::table('graduate_ledgers', function (Blueprint $table) {
            $table->dropColumn('imported_input_by');
        });
    }
};
