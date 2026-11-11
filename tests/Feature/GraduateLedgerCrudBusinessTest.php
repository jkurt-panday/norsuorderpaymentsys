<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\GraduateLedger;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GraduateLedgerCrudBusinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_graduate_ledger_index_loads_for_staff(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get(route('graduate-ledger.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('graduate-ledger/Index'));
    }

    public function test_guest_and_cashier_cannot_modify_ledger_entries(): void
    {
        $record = $this->record();
        $payload = $this->payload($record);

        $this->post(route('graduate-ledger.store'), $payload)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->cashier()->create())
            ->post(route('graduate-ledger.store'), $payload)->assertRedirect();
        $this->assertDatabaseCount('graduate_ledgers', 1);
    }

    public function test_required_fields_and_business_values_are_validated(): void
    {
        $staff = User::factory()->staff()->create();
        $record = $this->record();
        $payload = $this->payload($record);

        foreach (['student_id', 'course_id', 'entry_type', 'transaction_date'] as $field) {
            $invalid = $payload;
            unset($invalid[$field]);
            $this->actingAs($staff)->post(route('graduate-ledger.store'), $invalid)
                ->assertSessionHasErrors($field);
        }

        $this->actingAs($staff)->post(route('graduate-ledger.store'), array_merge($payload, [
            'entry_type' => 'invalid', 'amount' => -1, 'transaction_date' => 'not-a-date',
        ]))->assertSessionHasErrors(['entry_type', 'amount', 'transaction_date']);
    }

    public function test_ar_payment_and_adjustment_entries_are_created(): void
    {
        $staff = User::factory()->staff()->create();
        $record = $this->record();

        foreach (['ar', 'payment', 'adjustment'] as $type) {
            $payload = $this->payload($record, [
                'entry_type' => $type,
                'reference_number' => strtoupper($type).'-001',
                'amount' => $type === 'ar' ? null : '50.00',
            ]);

            $this->actingAs($staff)->post(route('graduate-ledger.store'), $payload)
                ->assertRedirect();
        }

        $this->assertDatabaseHas('graduate_ledgers', ['entry_type' => 'ar']);
        $this->assertDatabaseHas('graduate_ledgers', ['entry_type' => 'payment', 'amount' => '50.00']);
        $this->assertDatabaseHas('graduate_ledgers', ['entry_type' => 'adjustment', 'amount' => '50.00']);
    }

    public function test_ar_amount_is_calculated_from_units_and_rate(): void
    {
        $staff = User::factory()->staff()->create();
        $record = $this->record();

        $this->actingAs($staff)->post(route('graduate-ledger.store'), $this->payload($record, [
            'entry_type' => 'ar', 'units' => 9, 'rate' => '150.00', 'amount' => null,
        ]))->assertRedirect();

        $this->assertDatabaseHas('graduate_ledgers', [
            'entry_type' => 'ar', 'units' => 9, 'rate' => '150.00', 'amount' => '1350.00',
        ]);
    }

    public function test_zero_and_negative_amounts_are_rejected_for_payments_and_adjustments(): void
    {
        $staff = User::factory()->staff()->create();
        $record = $this->record();

        foreach ([0, -1] as $amount) {
            $this->actingAs($staff)->post(route('graduate-ledger.store'), $this->payload($record, [
                'entry_type' => 'payment', 'amount' => $amount,
            ]))->assertSessionHasErrors('amount');
        }
    }

    public function test_membership_discount_creates_adjustment_for_napu_and_norsuffa(): void
    {
        $staff = User::factory()->staff()->create();
        $record = $this->record();

        foreach (['NAPU', 'NORSUFFA'] as $membership) {
            $this->actingAs($staff)->post(route('graduate-ledger.store'), $this->payload($record, [
                'entry_type' => 'ar', 'amount' => '1000.00', 'membership' => $membership,
                'discount_amount' => '1000.00', 'reference_number' => $membership.'-AR',
            ]))->assertRedirect();
        }

        $this->assertDatabaseHas('graduate_ledgers', ['membership' => 'NAPU', 'entry_type' => 'adjustment', 'amount' => '1000.00']);
        $this->assertDatabaseHas('graduate_ledgers', ['membership' => 'NORSUFFA', 'entry_type' => 'adjustment', 'amount' => '1000.00']);
    }

    public function test_ledger_entry_can_be_edited_and_deleted(): void
    {
        $staff = User::factory()->staff()->create();
        $record = $this->record();

        $this->actingAs($staff)->put(route('graduate-ledger.update', $record->id), $this->payload($record, [
            'entry_type' => 'payment', 'amount' => '75.00', 'reference_number' => 'UPDATED',
        ]))->assertRedirect();

        $this->assertDatabaseHas('graduate_ledgers', ['id' => $record->id, 'reference_number' => 'UPDATED', 'amount' => '75.00']);
        $this->actingAs($staff)->delete(route('graduate-ledger.destroy', $record->id))->assertRedirect();
        $this->assertDatabaseMissing('graduate_ledgers', ['id' => $record->id]);
    }

    public function test_balance_and_outstanding_filters_are_correct(): void
    {
        $staff = User::factory()->staff()->create();
        $record = $this->record();
        $student = $record->student;
        $record->delete();
        GraduateLedger::create(array_merge($this->baseRecord($record), ['student_id' => $student->id, 'entry_type' => 'ar', 'amount' => 1000, 'reference_number' => 'BAL-AR']));
        GraduateLedger::create(array_merge($this->baseRecord($record), ['student_id' => $student->id, 'entry_type' => 'payment', 'amount' => 400, 'reference_number' => 'BAL-PAY']));

        $this->actingAs($staff)->getJson(route('graduate-ledger.students.balance', $student))
            ->assertOk()->assertJsonPath('summary.totalCharges', 1000)->assertJsonPath('summary.totalPayments', 400)->assertJsonPath('summary.outstandingBalance', 600);

        $this->actingAs($staff)->get(route('graduate-ledger.index', ['balance_status' => 'with_balance']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->has('records.data'));
    }

    public function test_ledger_ordering_is_deterministic(): void
    {
        $staff = User::factory()->staff()->create();
        $record = $this->record();
        $first = GraduateLedger::create(array_merge($this->baseRecord($record), ['reference_number' => 'ORDER-1', 'transaction_date' => '2026-01-01']));
        $second = GraduateLedger::create(array_merge($this->baseRecord($record), ['reference_number' => 'ORDER-2', 'transaction_date' => '2026-01-01']));

        $this->actingAs($staff)->get(route('graduate-ledger.students.balance', $record->student))
            ->assertJsonPath('transactions.0.id', $second->id)
            ->assertJsonPath('transactions.1.id', $first->id);
    }

    private function record(): GraduateLedger
    {
        $student = Student::create(['student_number' => '202600001', 'first_name' => 'Maria', 'last_name' => 'Reyes']);
        $course = Course::create(['course_code' => 'MBA-TEST', 'course_desc' => 'MBA', 'course_college' => 'Graduate School']);
        $term = AcademicTerm::create(['school_year' => '2025-2026', 'semester' => 'First Semester']);

        return GraduateLedger::create(array_merge([
            'student_id' => $student->id, 'course_id' => $course->id, 'academic_term_id' => $term->id,
            'entry_type' => 'ar', 'units' => 3, 'transaction_date' => '2026-01-01',
            'reference_number' => 'BASE', 'particulars' => 'Tuition', 'rate' => '100.00', 'amount' => '300.00', 'status' => 'posted',
        ], []));
    }

    private function payload(GraduateLedger $record, array $overrides = []): array
    {
        return array_merge([
            'student_id' => $record->student_id, 'course_id' => $record->course_id, 'academic_term_id' => $record->academic_term_id,
            'entry_type' => 'ar', 'units' => 3, 'transaction_date' => '2026-01-01', 'reference_number' => 'NEW-'.uniqid(),
            'particulars' => 'Tuition', 'rate' => '100.00', 'amount' => '300.00', 'status' => 'posted',
        ], $overrides);
    }

    private function baseRecord(GraduateLedger $record): array
    {
        return [
            'student_id' => $record->student_id,
            'course_id' => $record->course_id, 'academic_term_id' => $record->academic_term_id,
            'entry_type' => 'ar',
            'amount' => '100.00',
            'transaction_date' => '2026-01-01', 'particulars' => 'Tuition', 'rate' => '0.00', 'status' => 'posted',
        ];
    }
}
