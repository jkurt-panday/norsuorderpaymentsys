<?php

namespace Tests\Feature;

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
}
