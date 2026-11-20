<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'user_profiles',
        'college_offices',
        'courses',
        'academic_terms',
        'authorized_officials',
        'year_sequences',
        'memberships',
        'assessment_forms',
        'staff_inputs',
        'payment_detail_options',
        'uacs',
        'bankaccount_infos',
        'law_school_ledgers',
        'form_inputs',
        'students',
        'graduate_ledgers',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }
    }
};
