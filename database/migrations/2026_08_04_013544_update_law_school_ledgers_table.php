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
            // Drop indexes on columns being dropped (SQLite can't drop an indexed column)
            $table->dropIndex(['student_id']);
            $table->dropIndex(['student_name']);

            // Drop old columns
            $table->dropColumn([
                'student_id',
                'student_name',
                'program',
                'year_level',
                'academic_year',
                'semester',
                'units',
                'transaction_date',
                'due_date',
                'reference_or_jev_number',
                'particulars',
                'tuition_per_unit_or_misc',
                'transaction_type',
                'amount',
                'remaining_balance',
                'status',
                'remarks',
                'input_by',
            ]);

            // Add new columns
            $table->string('last_name')->nullable()->index();
            $table->string('first_name')->nullable()->index();
            $table->string('middle_initial', 1)->nullable();
            $table->string('course')->nullable();
            $table->string('school_year', 20)->nullable();
            $table->string('semester_or_summer', 50)->nullable();
            $table->decimal('units', 10, 2)->nullable();
            $table->date('transaction_date')->nullable();
            $table->string('reference_jev_or_number')->nullable();
            $table->string('particulars')->nullable();
            $table->decimal('tuition_per_unit_or_fee_per_semester', 10, 2)->nullable();
            $table->string('ar_or_payment')->nullable();
            $table->decimal('amount', 10, 2)->default(0.00);
            $table->string('remarks')->nullable();
            $table->string('status')->nullable();
            $table->string('input_by')->nullable();
        });
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
