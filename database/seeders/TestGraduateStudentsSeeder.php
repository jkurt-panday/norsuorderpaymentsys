<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\GraduateLedger;
use App\Models\Student;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestGraduateStudentsSeeder extends Seeder
{
    /**
     * Seed five graduate students for local reminder testing.
     */
    public function run(): void
    {
        $students = [
            [
                'student_number' => '202500001',
                'email' => 'rohancalape@gmail.com',
                'last_name' => 'Testerson',
                'first_name' => 'Alex',
                'middle_name' => 'M',
                'raw_name_from_csv' => 'TESTERSON, ALEX M',
            ],
            [
                'student_number' => '202500002',
                'email' => 'bzinessrohan@gmail.com',
                'last_name' => 'Sample',
                'first_name' => 'Jordan',
                'middle_name' => 'P',
                'raw_name_from_csv' => 'SAMPLE, JORDAN P',
            ],
            [
                'student_number' => '202500003',
                'email' => 'programacc@gmail.com',
                'last_name' => 'Demo',
                'first_name' => 'Taylor',
                'middle_name' => null,
                'raw_name_from_csv' => 'DEMO, TAYLOR',
            ],
            [
                'student_number' => '202500004',
                'email' => 'atlasplaysgamesnow@gmail.com',
                'last_name' => 'Example',
                'first_name' => 'Morgan',
                'middle_name' => 'R',
                'raw_name_from_csv' => 'EXAMPLE, MORGAN R',
            ],
            [
                'student_number' => '202500005',
                'email' => 'rohanisvibecoding@gmail.com',
                'last_name' => 'Practice',
                'first_name' => 'Casey',
                'middle_name' => 'L',
                'raw_name_from_csv' => 'PRACTICE, CASEY L',
            ],
        ];

        foreach ($students as $student) {
            Student::updateOrCreate(
                ['student_number' => $student['student_number']],
                $student,
            );
        }

        $term = [
            'school_year' => '2025-2026',
            'semester' => 'First Semester',
            'sort_order' => 1,
        ];

        DB::table('graduate_academic_term')->updateOrInsert(
            [
                'school_year' => $term['school_year'],
                'semester' => $term['semester'],
            ],
            [
                'semester' => $term['semester'],
                'sort_order' => $term['sort_order'],
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        $termId = DB::table('graduate_academic_term')
            ->where('school_year', $term['school_year'])
            ->where('semester', $term['semester'])
            ->value('id');

        $course = Course::updateOrCreate(
            ['code' => 'TEST-MBA'],
            ['title' => 'Test Master of Business Administration'],
        );

        $balances = [
            ['student_number' => '202500001', 'ar' => 20000.00, 'payment' => 5000.00],
            ['student_number' => '202500002', 'ar' => 17500.00, 'payment' => 5000.00],
            ['student_number' => '202500003', 'ar' => 15000.00, 'payment' => 5000.00],
            ['student_number' => '202500004', 'ar' => 12500.00, 'payment' => 5000.00],
            ['student_number' => '202500005', 'ar' => 10000.00, 'payment' => 5000.00],
        ];

        foreach ($balances as $balance) {
            $studentNumber = $balance['student_number'];
            $student = Student::where('student_number', $studentNumber)->firstOrFail();

            GraduateLedger::where('student_id', $student->id)
                ->where('academic_term_id', $termId)
                ->where('reference_or_jev_number', 'TEST-BAL-'.$studentNumber)
                ->delete();

            GraduateLedger::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'academic_term_id' => $termId,
                    'reference_or_jev_number' => 'TEST-AR-'.$studentNumber,
                ],
                [
                    'course_id' => $course->id,
                    'entry_type' => 'ar',
                    'particulars' => 'Test assessment charge',
                    'amount' => $balance['ar'],
                    'transaction_date' => now()->toDateString(),
                    'remarks' => 'Local reminder testing - AR',
                    'input_by' => 'TEST',
                ],
            );

            GraduateLedger::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'academic_term_id' => $termId,
                    'reference_or_jev_number' => 'TEST-PAY-'.$studentNumber,
                ],
                [
                    'course_id' => $course->id,
                    'entry_type' => 'payment',
                    'particulars' => 'Test partial payment',
                    'amount' => $balance['payment'],
                    'transaction_date' => now()->toDateString(),
                    'remarks' => 'Local reminder testing - payment',
                    'input_by' => 'TEST',
                ],
            );
        }
    }
}
