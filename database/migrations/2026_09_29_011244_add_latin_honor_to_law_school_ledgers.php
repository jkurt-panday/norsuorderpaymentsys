<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('law_school_ledgers', function (Blueprint $table) {
            $table->string('latin_honor', 20)->nullable()->after('status');
            $table->decimal('discount_amount', 12, 2)->default(0)->after('latin_honor');
        });
    }

    public function down(): void
    {
        Schema::table('law_school_ledgers', function (Blueprint $table) {
            $table->dropColumn(['latin_honor', 'discount_amount']);
        });
    }
};
