<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\LawStudent;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get distinct students from law_school_ledgers
        $students = DB::table('law_school_ledgers')
            ->select('last_name', 'first_name', 'middle_initial')
            ->whereNotNull('last_name')
            ->where('last_name', '!=', '')
            ->distinct()
            ->get();

        foreach ($students as $student) {
            $rawName = trim("{$student->last_name}, {$student->first_name}" . ($student->middle_initial ? " {$student->middle_initial}" : ''));

            LawStudent::firstOrCreate(
                [
                    'last_name' => $student->last_name,
                    'first_name' => $student->first_name,
                ],
                [
                    'middle_name' => $student->middle_initial,
                    'raw_name_from_csv' => $rawName,
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is not reversible - data would be lost
    }
};
