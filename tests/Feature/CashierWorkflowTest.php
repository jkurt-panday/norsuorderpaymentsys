<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\ActivityLog;
use App\Models\BankAccountInfo;
use App\Models\Course;
use App\Models\FormInput;
use App\Models\GraduateLedger;
use App\Models\Membership;
use App\Models\PaymentDetailOption;
use App\Models\StaffInput;
use App\Models\Student;
use App\Models\UACS;
use App\Models\User;
use App\Services\CashierLedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CashierWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_can_view_assigned_requests_and_open_one(): void
    {
        $cashier = User::factory()->cashier()->create();
        $staffInput = $this->staffInput();

        $this->actingAs($cashier)->get(route('cashier.requests.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('cashier/requests/Index')
                ->has('requests.data', 1));

        $this->actingAs($cashier)->get(route('cashier.requests.show', $staffInput))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('cashier/requests/Show')
                ->where('request.id', $staffInput->id));
    }

    public function test_non_cashier_cannot_access_cashier_routes(): void
    {
        foreach (['admin', 'staff'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('cashier.requests.index'))
                ->assertRedirect();
        }

        $this->get(route('cashier.requests.index'))->assertRedirect();
    }

    public function test_cashier_can_mark_a_processed_request_paid_and_persist_or_details(): void
    {
        $cashier = User::factory()->cashier()->create();
        $staffInput = $this->staffInput();

        $this->actingAs($cashier)->put(route('cashier.requests.payment.update', $staffInput), [
            'status' => 'paid',
            'or_no' => '2026001',
            'or_date' => '2026-11-01',
        ])->assertRedirect();

        $staffInput->refresh();
        $this->assertSame('paid', $staffInput->status);
        $this->assertSame('2026001', $staffInput->or_no);
        $this->assertSame('2026-11-01', $staffInput->or_date->format('Y-m-d'));
    }

    public function test_or_number_and_date_are_required_and_validated_when_paid(): void
    {
        $cashier = User::factory()->cashier()->create();
        $staffInput = $this->staffInput();

        $this->actingAs($cashier)->put(route('cashier.requests.payment.update', $staffInput), [
            'status' => 'paid',
        ])->assertSessionHasErrors(['or_no', 'or_date']);

        $this->actingAs($cashier)->put(route('cashier.requests.payment.update', $staffInput), [
            'status' => 'paid',
            'or_no' => 'BAD<script>',
            'or_date' => 'not-a-date',
        ])->assertSessionHasErrors(['or_no', 'or_date']);
    }

    public function test_invalid_status_transition_from_pending_is_rejected(): void
    {
        $cashier = User::factory()->cashier()->create();
        $staffInput = $this->staffInput(['status' => 'pending']);

        $this->actingAs($cashier)->put(route('cashier.requests.payment.update', $staffInput), [
            'status' => 'paid',
            'or_no' => '2026002',
            'or_date' => '2026-11-01',
        ])->assertSessionHasErrors('status');

        $this->assertSame('pending', $staffInput->fresh()->status);
    }

    public function test_already_paid_request_is_corrected_without_a_duplicate_ledger_payment(): void
    {
        $cashier = User::factory()->cashier()->create();
        $staffInput = $this->staffInput(['status' => 'paid', 'or_no' => 'OR-OLD', 'or_date' => '2026-10-01']);
        $this->ledgerPayment($staffInput, 'OR-OLD', 500);

        $this->actingAs($cashier)->put(route('cashier.requests.payment.update', $staffInput), [
            'status' => 'paid',
            'or_no' => '2026003',
            'or_date' => '2026-11-01',
        ])->assertRedirect();

        $this->assertSame(1, GraduateLedger::where('remarks', 'OP:'.$staffInput->form_input_id)->count());
        $this->assertDatabaseHas('graduate_ledgers', [
            'remarks' => 'OP:'.$staffInput->form_input_id,
            'reference_number' => '2026003',
        ]);
    }

    public function test_duplicate_payment_submission_does_not_double_post(): void
    {
        $cashier = User::factory()->cashier()->create();
        $staffInput = $this->staffInput();
        $payload = ['status' => 'paid', 'or_no' => '2026004', 'or_date' => '2026-11-01'];

        $this->actingAs($cashier)->put(route('cashier.requests.payment.update', $staffInput), $payload)->assertRedirect();
        $this->actingAs($cashier)->put(route('cashier.requests.payment.update', $staffInput), $payload)->assertRedirect();

        $this->assertSame(1, GraduateLedger::where('remarks', 'OP:'.$staffInput->form_input_id)->count());
    }

    public function test_failed_ledger_posting_does_not_silently_mark_request_paid(): void
    {
        $cashier = User::factory()->cashier()->create();
        $staffInput = $this->staffInput();
        GraduateLedger::query()->delete();
        $this->mock(CashierLedgerPostingService::class)
            ->shouldReceive('postPayment')->once()->andThrow(new \RuntimeException('ledger unavailable'));

        $this->actingAs($cashier)->put(route('cashier.requests.payment.update', $staffInput), [
            'status' => 'paid',
            'or_no' => '2026005',
            'or_date' => '2026-11-01',
        ])->assertRedirect()->assertSessionHasErrors('or_no');

        $this->assertSame('processed', $staffInput->fresh()->status);
        $this->assertNull($staffInput->fresh()->or_no);
        $this->assertDatabaseCount('graduate_ledgers', 0);
    }

    public function test_payment_posting_is_called_exactly_once_for_a_new_payment(): void
    {
        $cashier = User::factory()->cashier()->create();
        $staffInput = $this->staffInput();
        $mock = $this->mock(CashierLedgerPostingService::class);
        $mock->shouldReceive('postPayment')->once()->andReturn([
            'posted' => true, 'reason' => null, 'ledger' => 'graduate',
        ]);

        $this->actingAs($cashier)->put(route('cashier.requests.payment.update', $staffInput), [
            'status' => 'paid', 'or_no' => '2026006', 'or_date' => '2026-11-01',
        ])->assertRedirect();
    }

    public function test_unauthorized_status_changes_are_rejected(): void
    {
        $staffInput = $this->staffInput();

        $this->actingAs(User::factory()->staff()->create())
            ->put(route('cashier.requests.payment.update', $staffInput), [
                'status' => 'paid', 'or_no' => '2026007', 'or_date' => '2026-11-01',
            ])->assertRedirect();

        $this->assertSame('processed', $staffInput->fresh()->status);
    }

    public function test_cashier_action_is_recorded_in_activity_logs(): void
    {
        $cashier = User::factory()->cashier()->create();
        $staffInput = $this->staffInput();

        $this->actingAs($cashier)->put(route('cashier.requests.payment.update', $staffInput), [
            'status' => 'paid', 'or_no' => '2026008', 'or_date' => '2026-11-01',
        ])->assertRedirect();

        $this->assertDatabaseHas('activity_log', [
            'actor_id' => $cashier->id,
            'subject_type' => StaffInput::class,
            'subject_id' => $staffInput->id,
            'action' => 'updated',
        ]);
    }

    private function staffInput(array $overrides = []): StaffInput
    {
        $form = $this->formInput();
        return StaffInput::create(array_merge([
            'form_input_id' => $form->id,
            'fundcluster_id' => $this->bank()->id,
            'ref_date' => '2026-10-01',
            'uacs_id' => $this->uacs()->id,
            'status' => 'processed',
        ], $overrides));
    }

    private function formInput(): FormInput
    {
        $membership = Membership::firstOrCreate(['member_code' => 'CASHIER-TEST'], ['member_desc' => 'Student']);
        $option = PaymentDetailOption::firstOrCreate(['payment_desc' => 'Cashier Test Tuition']);
        $course = Course::firstOrCreate(['course_code' => 'CASHIER-MBA'], [
            'course_desc' => 'Cashier MBA', 'course_college' => 'Graduate School',
        ]);
        $term = AcademicTerm::firstOrCreate(['school_year' => '2026-2027', 'semester' => 'First Semester']);
        $student = Student::firstOrCreate(['student_number' => '202611001'], [
            'first_name' => 'Cashier', 'last_name' => 'Student',
        ]);

        $form = FormInput::create([
            'reference_number' => 'OP-CASHIER-'.uniqid(), 'email' => 'cashier.student@example.com',
            'contact_num' => '09123456789', 'firstname_or_office' => 'Cashier',
            'lastname_or_agency' => 'Student', 'office_or_college' => 'Graduate School',
            'position_or_designation' => 'Student', 'address' => 'Dumaguete', 'amount' => 500,
            'request_type' => 'New Request', 'membership_id' => $membership->id,
            'payment_detail_option_id' => $option->id, 'course_id' => $course->id,
            'academic_term' => $term->id, 'student_num' => $student->id,
        ]);

        GraduateLedger::create([
            'student_id' => $student->id, 'course_id' => $course->id, 'academic_term_id' => $term->id,
            'entry_type' => 'ar', 'transaction_date' => '2026-10-01', 'reference_number' => 'AR-CASHIER',
            'particulars' => 'Tuition', 'amount' => 1000, 'status' => 'posted',
        ]);

        return $form;
    }

    private function bank(): BankAccountInfo
    {
        return BankAccountInfo::firstOrCreate(['account_num' => '987654321'], [
            'account_name' => 'Main', 'bank_name' => 'Landbank', 'fund_cluster' => '01',
        ]);
    }

    private function uacs(): UACS
    {
        return UACS::firstOrCreate(['norsu_object_code' => '987654321012', 'norsu_sub_object_code' => '01'], [
            'uacs_object_code' => '9876543210', 'uacs_sub_object_code' => '01',
            'rca_code' => '98765432', 'account_title' => 'Tuition Fees',
        ]);
    }

    private function ledgerPayment(StaffInput $staffInput, string $orNo, float $amount): GraduateLedger
    {
        return GraduateLedger::create([
            'student_id' => $staffInput->formInput->student_num,
            'course_id' => $staffInput->formInput->course_id,
            'academic_term_id' => $staffInput->formInput->academic_term,
            'entry_type' => 'payment', 'transaction_date' => '2026-10-01',
            'reference_number' => $orNo, 'particulars' => 'Payment', 'amount' => $amount,
            'remarks' => 'OP:'.$staffInput->form_input_id, 'status' => 'posted',
        ]);
    }
}
