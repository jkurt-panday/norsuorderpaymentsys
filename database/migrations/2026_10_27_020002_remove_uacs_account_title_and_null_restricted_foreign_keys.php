<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        $this->changeForeignKey('graduate_ledgers', 'student_id', 'students');
        $this->changeForeignKey('graduate_ledgers', 'academic_term_id', 'academic_terms');
        $this->changeForeignKey('graduate_ledgers', 'course_id', 'courses');

        $this->changeForeignKey('law_school_ledgers', 'student_id', 'students');
        $this->changeForeignKey('law_school_ledgers', 'academic_term_id', 'academic_terms');
        $this->changeForeignKey('law_school_ledgers', 'course_id', 'courses');
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

        if (DB::connection()->getDriverName() !== 'sqlite') {
            Schema::table($tableName, function (Blueprint $table) use ($column, $constraintName, $tableName): void {
                if ($constraintName !== null) {
                    $table->dropForeign($constraintName);
                } else {
                    // Check if foreign key exists before dropping
                    $foreignKeyExists = DB::select(
                        "SELECT COUNT(*) as count FROM information_schema.TABLE_CONSTRAINTS 
                         WHERE CONSTRAINT_SCHEMA = DATABASE() 
                         AND TABLE_NAME = ? 
                         AND CONSTRAINT_NAME = ? 
                         AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
                        [$tableName, $tableName . '_' . $column . '_foreign']
                    );

                    if ($foreignKeyExists[0]->count > 0) {
                        $table->dropForeign([$column]);
                    }
                }
            });
        }

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
