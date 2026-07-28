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
        Schema::create('graduate_ledgers', function (Blueprint $table) {
            $table->id();

            // Student & Course Information
            $table->string('student_name')->nullable()->index(); // First column in CSV (e.g., ABABON, LAHLAINE A.)
            $table->string('course')->nullable();               // e.g., MAMT, MS-MATH

            // Academic Period
            $table->string('school_year', 20)->nullable();      // e.g., 2015-2016
            $table->string('semester_short', 20)->nullable();   // e.g., 1st Sem., 2nd Sem.
            $table->string('semester', 50)->nullable();         // e.g., First Semester, Second Semester

            // Transaction Details
            $table->integer('units')->nullable();               // e.g., 9
            $table->date('transaction_date')->nullable();       // e.g., 2016-11-11
            $table->string('reference_or_jev_number')->nullable(); // Reference JEV / O.R. NUMBER
            $table->string('particulars')->nullable();          // e.g., Tuition
            $table->decimal('tuition_per_unit_or_misc', 10, 2)->default(0.00);
            $table->string('ar_payment')->nullable();           // e.g., AR / Payment
            $table->decimal('amount', 10, 2)->default(0.00);    // e.g., 0.00

            // Audit & Status
            $table->string('remarks')->nullable();              // e.g., Settled
            $table->string('input_by')->nullable();             // Encoder ID / Initials (e.g., MBC)

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('graduate_ledgers');
    }
};
