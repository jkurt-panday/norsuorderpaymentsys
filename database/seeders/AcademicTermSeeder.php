<?php

namespace Database\Seeders;

use App\Models\AcademicTerm;
use Illuminate\Database\Seeder;

class AcademicTermSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $startYear = 2000;
        $endYear = (int) now()->year; // seeds up to the current year's school year

        $semesters = [
            'First Semester',
            'Second Semester',
            'Summer',
        ];

        for ($year = $startYear; $year <= $endYear; $year++) {
            $schoolYear = "{$year}-".($year + 1);

            foreach ($semesters as $semester) {
                AcademicTerm::query()->updateOrCreate([
                    'school_year' => $schoolYear,
                    'semester' => $semester,
                ]);
            }
        }
    }
}
