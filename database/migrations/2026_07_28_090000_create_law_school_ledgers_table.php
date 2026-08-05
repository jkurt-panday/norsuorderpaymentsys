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
        Schema::create('law_school_ledgers', function (Blueprint $table) {
            $table->id();

            // Student & Course Information
            $table->string('student_id')->nullable()->index(); // e.g., 2021-00123
            $table->string('student_name')->nullable()->index(); // e.g., CRUZ, JUAN DELA
            $table->string('program')->nullable(); // e.g., JD, LLM, JSD
            $table->string('year_level')->nullable(); // e.g., 1L, 2L, 3L, 4L

            // Academic Period
            $table->string('academic_year', 20)->nullable(); // e.g., 2024-2025
            $table->string('semester', 50)->nullable(); // e.g., First Semester, Second Semester, Summer

            // Transaction Details
            $table->decimal('units', 10, 2)->nullable(); // e.g., 9.00
            $table->date('transaction_date')->nullable(); // e.g., 2024-08-15
            $table->date('due_date')->nullable(); // e.g., 2024-09-15
            $table->string('reference_or_jev_number')->nullable(); // Reference JEV / O.R. NUMBER
            $table->string('particulars')->nullable(); // e.g., Tuition Fee, Miscellaneous Fee

            // Financial Information
            $table->decimal('tuition_per_unit_or_misc', 10, 2)->default(0.00);
            $table->string('transaction_type')->nullable(); // e.g., Assessment, Payment, Adjustment
            $table->decimal('amount', 10, 2)->default(0.00); // e.g., 15000.00
            $table->decimal('remaining_balance', 10, 2)->default(0.00); // Computed remaining balance

            // Status & Audit
            $table->string('status')->nullable(); // e.g., Paid, Pending, Overdue, Partial Payment
            $table->string('remarks')->nullable(); // Additional notes
            $table->string('input_by')->nullable(); // Encoder ID / Initials

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('law_school_ledgers');
    }
};
