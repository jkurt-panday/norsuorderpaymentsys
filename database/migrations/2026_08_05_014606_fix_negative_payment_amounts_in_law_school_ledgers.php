<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ensure all Payment records have positive amounts
        DB::table('law_school_ledgers')
            ->where('ar_or_payment', 'Payment')
            ->where('amount', '<', 0)
            ->update(['amount' => DB::raw('ABS(amount)')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('law_school_ledgers', function (Blueprint $table) {
            //
        });
    }
};
