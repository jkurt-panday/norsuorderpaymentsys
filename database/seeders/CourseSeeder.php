<?php

namespace Database\Seeders;

use App\Models\Courses;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $courses = [
            // ── Doctor of Philosophy (Ph.D.) ─────────────────────────────
            ['code' => 'PHD-EM', 'desc' => 'Ph.D. in Educational Management'],
            ['code' => 'PHD-MATHED', 'desc' => 'Ph.D. in Mathematics Education'],
            ['code' => 'PHD-AL', 'desc' => 'Ph.D. in Applied Linguistics'],

            // ── Doctor of Education (Ed.D.) ──────────────────────────────
            ['code' => 'EDD-EM', 'desc' => 'Ed.D. in Educational Management'],
            ['code' => 'EDD-INSTR', 'desc' => 'Ed.D. in Instruction'],
            ['code' => 'EDD-SCIED', 'desc' => 'Ed.D. in Science Education'],
            ['code' => 'EDD-FIL', 'desc' => 'Ed.D. in Filipino'],
            ['code' => 'EDD-TM', 'desc' => 'Ed.D. in Technology Management'],

            // ── Doctor of Management (DM) ────────────────────────────────
            ['code' => 'DM-HRM', 'desc' => 'Doctor of Management in Human Resource Management'],
            ['code' => 'DM-PA', 'desc' => 'Doctor of Management in Public Administration'],

            // ── Master in Business Administration ────────────────────────
            ['code' => 'MBA', 'desc' => 'Master in Business Administration'],

            // ── Master in Public Health ───────────────────────────────────
            ['code' => 'MPH', 'desc' => 'Master in Public Health'],

            // ── Master of Arts ────────────────────────────────────────────
            ['code' => 'MAST', 'desc' => 'Master of Arts in Science Teaching'],
            ['code' => 'MAENG', 'desc' => 'Master of Arts in English'],
            ['code' => 'MAFIL', 'desc' => 'Master of Arts in Filipino'],
            ['code' => 'MAHIST', 'desc' => 'Master of Arts in History'],
            ['code' => 'MAPSYCH', 'desc' => 'Master of Arts in Psychology'],
            ['code' => 'MAMT', 'desc' => 'Master of Arts in Mathematics Teaching'],
            ['code' => 'MAECE', 'desc' => 'Master of Arts in Early Childhood Education'],
            ['code' => 'MAEM', 'desc' => 'Master of Arts in Educational Management'],
            ['code' => 'MAPE', 'desc' => 'Master of Arts in Physical Education'],
            ['code' => 'MAVE', 'desc' => 'Master of Arts in Vocational Education'],
            ['code' => 'MASPED', 'desc' => 'Master of Arts in Special Education'],

            // ── Master of Science ─────────────────────────────────────────
            ['code' => 'MSAG', 'desc' => 'Master of Science in Agriculture'],
            ['code' => 'MSAGRON', 'desc' => 'Master of Science in Agronomy'],
            ['code' => 'MSANISCI', 'desc' => 'Master of Science in Animal Science'],
            ['code' => 'MSIT', 'desc' => 'Master of Science in Information Technology'],

            // ── Master of Technological Education (MTE) ──────────────────
            ['code' => 'MTE-AUTO', 'desc' => 'Master of Technological Education in Automotive Technology'],
            ['code' => 'MTE-CIVIL', 'desc' => 'Master of Technological Education in Civil Technology'],
            ['code' => 'MTE-IG', 'desc' => 'Master of Technological Education in Industrial Graphics'],
            ['code' => 'MTE-ELEC', 'desc' => 'Master of Technological Education in Electrical Technology'],
            ['code' => 'MTE-ELEX', 'desc' => 'Master of Technological Education in Electronics Technology'],
            ['code' => 'MTE-MECH', 'desc' => 'Master of Technological Education in Mechanical Technology'],

            // ── Master of Public Management ──────────────────────────────
            ['code' => 'MPM-HRM', 'desc' => 'Master of Public Management in Human Resource Management'],
            ['code' => 'MPM-LGA', 'desc' => 'Master of Public Management in Local Government'],
        ];

        foreach ($courses as $course) {
            Courses::query()->updateOrCreate(
                ['course_code' => strtoupper($course['code'])],
                [
                    'course_desc' => $course['desc'],
                    'course_college' => 'Graduate School',
                ]
            );
        }

        // ── School of Law ─────────────────────────────────────────────────
        Courses::query()->updateOrCreate(
            ['course_code' => 'JD'],
            [
                'course_desc' => 'Juris Doctor',
                'course_college' => 'School of Law',
            ]
        );
    }
}
