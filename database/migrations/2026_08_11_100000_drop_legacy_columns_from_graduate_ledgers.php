<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop legacy string columns that have been replaced by normalized FK columns.
     * Only safe to run AFTER graduate:backfill has fully populated student_id,
     * course_id, academic_term_id, and entry_type for all rows.
     */
    public function up(): void
    {
        Schema::table('graduate_ledgers', function (Blueprint $table) {
            // Drop the index on the legacy column before dropping it (SQLite can't drop an indexed column)
            $table->dropIndex(['student_name']);

            $table->dropColumn([
                'student_name',   // → student_id FK
                'course',         // → course_id FK
                'school_year',    // → academic_term_id FK
                'semester_short', // → academic_term_id FK
                'semester',       // → academic_term_id FK
                'ar_payment',     // → entry_type enum
            ]);
        });
    }

    /**
     * Restore the legacy columns (data will be lost — restore from backup or re-import CSV).
     */
    public function down(): void
    {
        Schema::table('graduate_ledgers', function (Blueprint $table) {
            $table->string('student_name')->nullable()->after('id');
            $table->string('course')->nullable()->after('student_name');
            $table->string('school_year', 20)->nullable()->after('course');
            $table->string('semester_short', 20)->nullable()->after('school_year');
            $table->string('semester', 50)->nullable()->after('semester_short');
            $table->string('ar_payment')->nullable()->after('particulars');
        });
    }
};
