<?php

namespace Tests\Feature;

use App\Models\GraduateLedger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GraduateLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_new_ledger_transaction(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/graduate-ledger', [
            'student_name' => 'Juan Dela Cruz',
            'course' => 'MS-MATH',
            'school_year' => '2025-2026',
            'semester_short' => '1st Sem.',
            'semester' => 'First Semester',
            'units' => 9,
            'transaction_date' => '2026-07-22',
            'reference_or_jev_number' => 'OR-001',
            'particulars' => 'Tuition',
            'tuition_per_unit_or_misc' => '150.00',
            'ar_payment' => 'AR',
            'amount' => '1350.00',
            'remarks' => 'New transaction',
            'input_by' => 'Admin',
        ]);

        $response->assertRedirect('/graduate-ledger');

        $this->assertDatabaseHas('graduate_ledgers', [
            'student_name' => 'Juan Dela Cruz',
            'course' => 'MS-MATH',
            'reference_or_jev_number' => 'OR-001',
            'amount' => '1350.00',
        ]);
    }

    public function test_index_can_filter_records_by_year_and_month(): void
    {
        $user = User::factory()->create();

        GraduateLedger::create([
            'student_name' => 'Filtered Student',
            'course' => 'MS-MATH',
            'school_year' => '2024-2025',
            'semester_short' => '1st Sem.',
            'transaction_date' => '2024-07-10',
            'reference_or_jev_number' => 'OR-100',
            'particulars' => 'Tuition',
            'tuition_per_unit_or_misc' => '100.00',
            'ar_payment' => 'AR',
            'amount' => '100.00',
            'remarks' => 'Filtered',
            'input_by' => 'Admin',
        ]);

        GraduateLedger::create([
            'student_name' => 'Other Student',
            'course' => 'MS-ENG',
            'school_year' => '2025-2026',
            'semester_short' => '2nd Sem.',
            'transaction_date' => '2025-08-12',
            'reference_or_jev_number' => 'OR-200',
            'particulars' => 'Tuition',
            'tuition_per_unit_or_misc' => '100.00',
            'ar_payment' => 'AR',
            'amount' => '100.00',
            'remarks' => 'Other',
            'input_by' => 'Admin',
        ]);

        $response = $this->actingAs($user)->get('/graduate-ledger?year=2024&month=7');

        $response->assertOk();
        $response->assertSee('Filtered Student');
        $response->assertDontSee('Other Student');
    }
}
