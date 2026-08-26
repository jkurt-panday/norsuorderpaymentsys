<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add composite indexes to graduate_ledgers for faster filtering,
     * SOA generation, and balance computation queries.
     *
     * Indexes added:
     *  - (student_id, academic_term_id)           → SOA/PDF per student per term
     *  - (student_id, academic_term_id, entry_type) → net balance computation per student per term
     *  - (academic_term_id, course_id)             → filter by semester + course
     */
    public function up(): void
    {
        Schema::table('graduate_ledgers', function (Blueprint $table) {
            // Used when generating SOA or loading all transactions for one student in one term
            $table->index(
                ['student_id', 'academic_term_id'],
                'idx_grad_ledger_student_term'
            );

            // Used when computing net balance per student per term (AR vs Payment vs Adjustment)
            $table->index(
                ['student_id', 'academic_term_id', 'entry_type'],
                'idx_grad_ledger_student_term_type'
            );

            // Used when filtering ledger table by semester + course dropdown
            $table->index(
                ['academic_term_id', 'course_id'],
                'idx_grad_ledger_term_course'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('graduate_ledgers', function (Blueprint $table) {
            $table->dropIndex('idx_grad_ledger_student_term');
            $table->dropIndex('idx_grad_ledger_student_term_type');
            $table->dropIndex('idx_grad_ledger_term_course');
        });
    }
};
