<?php

namespace Database\Seeders;

use App\Models\Student;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Regular students with a student number
        Student::factory()
            ->count(100)
            ->create();

        // A few students without a student number, simulating old-ledger
        // walk-in records that haven't been reconciled yet
        // Student::factory()
        //     ->withoutStudentNumber()
        //     ->count(5)
        //     ->create();
    }
}
