<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uacs', function (Blueprint $table): void {
            if (Schema::hasColumn('uacs', 'account_title')) {
                $table->dropColumn('account_title');
            }
        });

        $this->changeForeignKey('form_inputs', 'membership_id', 'memberships');
        $this->changeForeignKey('form_inputs', 'payment_detail_option_id', 'payment_detail_options');
        $this->changeForeignKey('staff_inputs', 'fundcluster_id', 'bankaccount_infos');
        $this->changeForeignKey('staff_inputs', 'uacs_id', 'uacs');

        $this->changeForeignKey('graduate_ledgers', 'student_id', 'students', 'new_graduate_ledgers_student_id_foreign');
        $this->changeForeignKey('graduate_ledgers', 'academic_term_id', 'academic_terms', 'new_graduate_ledgers_academic_term_id_foreign');
        $this->changeForeignKey('graduate_ledgers', 'course_id', 'courses', 'new_graduate_ledgers_course_id_foreign');

        $this->changeForeignKey('law_school_ledgers', 'student_id', 'students', 'new_law_school_ledgers_student_id_foreign');
        $this->changeForeignKey('law_school_ledgers', 'academic_term_id', 'academic_terms', 'new_law_school_ledgers_academic_term_id_foreign');
        $this->changeForeignKey('law_school_ledgers', 'course_id', 'courses', 'new_law_school_ledgers_course_id_foreign');
    }

    private function changeForeignKey(
        string $tableName,
        string $column,
        string $referencedTable,
        ?string $constraintName = null,
    ): void {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, $column)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($column, $constraintName): void {
            // Passing an array makes Laravel derive the conventional foreign-key
            // name (for example, form_inputs_membership_id_foreign). Passing a
            // bare column string makes MySQL try to drop a key named only after
            // the column, which fails on existing schemas.
            if ($constraintName !== null) {
                $table->dropForeign($constraintName);
            } else {
                $table->dropForeign([$column]);
            }
        });

        Schema::table($tableName, function (Blueprint $table) use ($column, $referencedTable, $constraintName): void {
            $table->unsignedBigInteger($column)->nullable()->change();
            $foreign = $table->foreign($column, $constraintName);
            $foreign->references('id')->on($referencedTable)->nullOnDelete();
        });
    }

    public function down(): void
    {
        // The old account_title field and RESTRICT behavior are intentionally
        // not restored because imported hierarchy rows depend on nullable data.
    }
};
