<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('law_school_ledgers', function (Blueprint $table) {
            $table->string('middle_name')->nullable()->after('middle_initial');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('law_school_ledgers', function (Blueprint $table) {
            $table->dropColumn('middle_name');
        });
    }
};
