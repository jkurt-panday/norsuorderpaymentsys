<?php

namespace Database\Seeders;

use App\Models\AcademicTerm;
use App\Models\BankAccountInfo;
use App\Models\Course;
use App\Models\GraduateLedger;
use App\Models\Membership;
use App\Models\PaymentDetailOption;
use App\Models\Student;
use App\Models\UACS;
use App\Models\User;
use App\Models\YearSequence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestGraduateStudentsSeeder extends Seeder
{
    /**
     * Seed users, terms, courses, students and ledger records with previous balances.
     */
    public function run(): void
    {
        // 1. Seed standard users for testing with password: "password"
        $admin = User::updateOrCreate(
            ['email' => 'admin@norsu.edu.ph'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        $staff = User::updateOrCreate(
            ['email' => 'staff@norsu.edu.ph'],
            [
                'name' => 'Lead Staff',
                'password' => Hash::make('password'),
                'role' => 'staff',
                'email_verified_at' => now(),
            ]
        );

        $cashier = User::updateOrCreate(
            ['email' => 'cashier@norsu.edu.ph'],
            [
                'name' => 'Main Cashier',
                'password' => Hash::make('password'),
                'role' => 'cashier',
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('Users seeded: admin@norsu.edu.ph, staff@norsu.edu.ph, cashier@norsu.edu.ph (password: password)');

        // 2. Memberships
        $studentMembership = Membership::updateOrCreate(
            ['member_code' => 'STUDENT'],
            ['member_desc' => 'Graduate Student']
        );
        Membership::updateOrCreate(
            ['member_code' => 'NAPU'],
            ['member_desc' => 'NORSU Administrative Personnel Union']
        );
        Membership::updateOrCreate(
            ['member_code' => 'NORSUFFA'],
            ['member_desc' => 'Negros Oriental State University Federated Faculty Association']
        );
        Membership::updateOrCreate(
            ['member_code' => 'N/A'],
            ['member_desc' => 'General Public / Other']
        );

        // 3. Payment Detail Options
        $tuitionOption = PaymentDetailOption::updateOrCreate(
            ['payment_desc' => 'Tuition Fee Payment']
        );
        PaymentDetailOption::updateOrCreate(
            ['payment_desc' => 'Comprehensive Exam - Masters']
        );
        PaymentDetailOption::updateOrCreate(
            ['payment_desc' => 'Comprehensive Exam - Doctorate']
        );

        // 4. Bank Account & UACS
        BankAccountInfo::updateOrCreate(
            ['account_num' => '0740-012637-032'],
            [
                'account_name' => 'NORSU Special Trust Fund 164 - Tuition',
                'fund_cluster' => 'T-164',
                'bank_name' => 'DBP Dumaguete',
            ]
        );

        UACS::updateOrCreate(
            ['object_code' => '4020101000'],
            ['account_title' => 'Tuition Fees']
        );

        // 5. Academic Terms (Previous Term + Current Term)
        $previousTerm = AcademicTerm::updateOrCreate(
            [
                'school_year' => '2024-2025',
                'semester' => 'Second Semester',
            ]
        );

        $currentTerm = AcademicTerm::updateOrCreate(
            [
                'school_year' => '2025-2026',
                'semester' => 'First Semester',
            ]
        );

        AcademicTerm::updateOrCreate(
            [
                'school_year' => '2025-2026',
                'semester' => 'Second Semester',
            ]
        );

        // 6. Graduate School Courses
        $msit = Course::updateOrCreate(
            ['course_code' => 'MSIT'],
            [
                'course_desc' => 'Master of Science in Information Technology',
                'course_college' => 'Graduate School',
            ]
        );

        $mba = Course::updateOrCreate(
            ['course_code' => 'MBA'],
            [
                'course_desc' => 'Master in Business Administration',
                'course_college' => 'Graduate School',
            ]
        );

        $maMath = Course::updateOrCreate(
            ['course_code' => 'MA Mathematics'],
            [
                'course_desc' => 'Master of Arts in Mathematics',
                'course_college' => 'Graduate School',
            ]
        );

        // 7. Year Sequence
        YearSequence::updateOrCreate(
            ['year' => 2026],
            [
                'month' => 9,
                'op_number' => 100,
                'assessment_number' => 0,
            ]
        );

        // 8. Seed Student with Balance from Previous Semester
        // Student: Alex Testerson (202500001)
        // Previous semester (2024-2025 2nd Sem): AR 15,000 - Payment 5,000 = 10,000 balance
        // Current semester (2025-2026 1st Sem): AR 12,000
        // Total Outstanding Balance: 22,000
        $alex = Student::updateOrCreate(
            ['student_number' => '202500001'],
            [
                'first_name' => 'Alex',
                'middle_name' => 'M',
                'last_name' => 'Testerson',
                'email' => 'alex.testerson@example.com',
                'contact_num' => '09123456789',
            ]
        );

        // Clean existing ledger rows for Alex to ensure repeatable seeding
        GraduateLedger::where('student_id', $alex->id)->delete();

        // Previous Term Ledger Rows
        GraduateLedger::create([
            'student_id' => $alex->id,
            'academic_term_id' => $previousTerm->id,
            'course_id' => $msit->id,
            'units' => 9,
            'rate' => 500.00,
            'entry_type' => 'ar',
            'amount' => 15000.00,
            'transaction_date' => '2025-01-15',
            'reference_number' => 'AR-2024-2ND-001',
            'particulars' => 'Tuition & Assessment (2024-2025 2nd Sem)',
            'remarks' => 'Assessment Charge',
            'status' => 'posted',
            'input_by' => $staff->id,
        ]);

        GraduateLedger::create([
            'student_id' => $alex->id,
            'academic_term_id' => $previousTerm->id,
            'course_id' => $msit->id,
            'units' => null,
            'rate' => 0.00,
            'entry_type' => 'payment',
            'amount' => 5000.00,
            'transaction_date' => '2025-02-10',
            'reference_number' => 'OR-2024-PREV-PAY1',
            'particulars' => 'Partial Tuition Payment',
            'remarks' => 'Paid at Cashier',
            'status' => 'posted',
            'input_by' => $cashier->id,
        ]);

        // Current Term Ledger Rows (New Assessment)
        GraduateLedger::create([
            'student_id' => $alex->id,
            'academic_term_id' => $currentTerm->id,
            'course_id' => $msit->id,
            'units' => 9,
            'rate' => 500.00,
            'entry_type' => 'ar',
            'amount' => 12000.00,
            'transaction_date' => '2025-08-20',
            'reference_number' => 'AR-2025-1ST-001',
            'particulars' => 'Tuition & Assessment (2025-2026 1st Sem)',
            'remarks' => 'Current Term Assessment',
            'status' => 'posted',
            'input_by' => $staff->id,
        ]);

        // 9. Seed a second student for comparison (Jordan Sample, 202500002)
        $jordan = Student::updateOrCreate(
            ['student_number' => '202500002'],
            [
                'first_name' => 'Jordan',
                'middle_name' => 'P',
                'last_name' => 'Sample',
                'email' => 'jordan.sample@example.com',
                'contact_num' => '09987654321',
            ]
        );

        GraduateLedger::where('student_id', $jordan->id)->delete();

        GraduateLedger::create([
            'student_id' => $jordan->id,
            'academic_term_id' => $currentTerm->id,
            'course_id' => $mba->id,
            'units' => 6,
            'rate' => 500.00,
            'entry_type' => 'ar',
            'amount' => 8000.00,
            'transaction_date' => '2025-08-22',
            'reference_number' => 'AR-2025-1ST-002',
            'particulars' => 'Tuition Assessment (2025-2026 1st Sem)',
            'remarks' => 'New Assessment',
            'status' => 'posted',
            'input_by' => $staff->id,
        ]);

        $this->command->info('Seeded Graduate Students with balances:');
        $this->command->info(' - Alex Testerson (202500001): ₱10,000 (prev term) + ₱12,000 (current term) = ₱22,000 total balance');
        $this->command->info(' - Jordan Sample (202500002): ₱8,000 total balance');
    }
}
