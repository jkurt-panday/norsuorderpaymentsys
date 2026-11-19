<?php

namespace Tests\Feature\Api;

use App\Models\AcademicTerm;
use App\Models\BankAccountInfo;
use App\Models\Course;
use App\Models\FormInput;
use App\Models\Membership;
use App\Models\PaymentDetailOption;
use App\Models\StaffInput;
use App\Models\Student;
use App\Models\UACS;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffRequestTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function staffUser(): User
    {
        return User::factory()->create(['role' => 'staff']);
    }

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function cashierUser(): User
    {
        return User::factory()->create(['role' => 'cashier']);
    }

    /** Build a minimal FormInput (with membership + payment option). */
    private function makeFormInput(array $attrs = []): FormInput
    {
        $suffix = str()->uuid()->toString();
        $membership = Membership::create([
            'member_code' => 'M-'.substr($suffix, 0, 12),
            'member_desc' => 'Test membership '.$suffix,
        ]);
        $paymentOption = PaymentDetailOption::create([
            'payment_desc' => 'Test payment '.$suffix,
        ]);

        return FormInput::create(array_merge([
            'reference_number' => 'REF-'.substr($suffix, 0, 16),
            'email' => 'test-'.substr($suffix, 0, 8).'@example.com',
            'contact_num' => '09123456789',
            'firstname_or_office' => 'Test',
            'lastname_or_agency' => 'Payor',
            'office_or_college' => 'Test Office',
            'position_or_designation' => 'Student',
            'address' => 'Test Address',
            'amount' => 100,
            'request_type' => 'New Request',
            'membership_id' => $membership->id,
            'payment_detail_option_id' => $paymentOption->id,
        ], $attrs));
    }

    /** Build a minimal processed StaffInput for a FormInput. */
    private function processFormInput(FormInput $formInput, array $attrs = []): StaffInput
    {
        $bank = $this->makeBankAccount();
        $uacs = $this->makeUacs();

        return StaffInput::create(array_merge([
            'form_input_id' => $formInput->id,
            'fundcluster_id' => $bank->id,
            'uacs_id' => $uacs->id,
            'status' => 'processed',
            'ref_date' => today()->toDateString(),
        ], $attrs));
    }

    private function makeBankAccount(): BankAccountInfo
    {
        $suffix = substr(str()->uuid()->toString(), 0, 12);

        return BankAccountInfo::create([
            'account_name' => 'Test Account',
            'fund_cluster' => 'FC-'.$suffix,
            'bank_name' => 'Test Bank',
            'account_num' => 'ACCT-'.$suffix,
        ]);
    }

    private function makeUacs(): UACS
    {
        $suffix = substr(str()->uuid()->toString(), 0, 8);

        return UACS::create([
            'uacs_object_code' => 'UACS-'.$suffix,
            'account_title' => 'Test Account '.$suffix,
        ]);
    }

    private function makeCourse(string $college): Course
    {
        $suffix = substr(str()->uuid()->toString(), 0, 8);

        return Course::create([
            'course_code' => 'COURSE-'.$suffix,
            'course_desc' => 'Test Course '.$suffix,
            'course_college' => $college,
        ]);
    }

    // ── Auth / role guards ────────────────────────────────────────────────────

    public function test_unauthenticated_cannot_access_index(): void
    {
        $this->getJson('/api/v1/staff/requests')->assertUnauthorized();
    }

    public function test_cashier_cannot_access_index(): void
    {
        $this->actingAs($this->cashierUser())
            ->getJson('/api/v1/staff/requests')
            ->assertForbidden();
    }

    public function test_admin_can_access_index(): void
    {
        $this->actingAs($this->adminUser())
            ->getJson('/api/v1/staff/requests')
            ->assertOk();
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_json(): void
    {
        $this->makeFormInput();
        $this->makeFormInput();

        $this->actingAs($this->staffUser())
            ->getJson('/api/v1/staff/requests')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links']);
    }

    public function test_index_filters_by_status_unprocessed(): void
    {
        $unprocessed = $this->makeFormInput();
        $processed = $this->makeFormInput();
        $this->processFormInput($processed);

        $response = $this->actingAs($this->staffUser())
            ->getJson('/api/v1/staff/requests?status=unprocessed')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($unprocessed->id));
        $this->assertFalse($ids->contains($processed->id));
    }

    public function test_index_filters_by_search(): void
    {
        $target = $this->makeFormInput(['firstname_or_office' => 'UniqueSearchName']);
        $other = $this->makeFormInput(['firstname_or_office' => 'SomebodyElse']);

        $response = $this->actingAs($this->staffUser())
            ->getJson('/api/v1/staff/requests?search=uniquesearchname')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($target->id));
        $this->assertFalse($ids->contains($other->id));
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_form_input_with_relations(): void
    {
        $formInput = $this->makeFormInput();

        $this->actingAs($this->staffUser())
            ->getJson("/api/v1/staff/requests/{$formInput->id}")
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'bankAccounts',
                'uacsList',
                'paymentOptions',
                'academicTerms',
            ]);
    }

    public function test_show_returns_404_for_missing_form_input(): void
    {
        $this->actingAs($this->staffUser())
            ->getJson('/api/v1/staff/requests/999999')
            ->assertNotFound();
    }

    // ── Store (process) ───────────────────────────────────────────────────────

    public function test_store_creates_staff_input(): void
    {
        $formInput = $this->makeFormInput();
        $bank = $this->makeBankAccount();
        $uacs = $this->makeUacs();

        $this->actingAs($this->staffUser())
            ->postJson('/api/v1/staff/requests/process', [
                'form_input_id' => $formInput->id,
                'fundcluster_id' => $bank->id,
                'uacs_id' => $uacs->id,
                'status' => 'processed',
                'ref_date' => today()->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonStructure(['message', 'data']);

        $this->assertDatabaseHas('staff_inputs', ['form_input_id' => $formInput->id, 'status' => 'processed']);
    }

    public function test_store_blocks_duplicate_processing(): void
    {
        $formInput = $this->makeFormInput();
        $this->processFormInput($formInput);
        $bank = $this->makeBankAccount();
        $uacs = $this->makeUacs();

        $this->actingAs($this->staffUser())
            ->postJson('/api/v1/staff/requests/process', [
                'form_input_id' => $formInput->id,
                'fundcluster_id' => $bank->id,
                'uacs_id' => $uacs->id,
                'status' => 'processed',
                'ref_date' => today()->toDateString(),
            ])
            ->assertStatus(422);
    }

    public function test_route_bound_process_uses_form_input_from_url(): void
    {
        $formInput = $this->makeFormInput();
        $bank = $this->makeBankAccount();
        $uacs = $this->makeUacs();

        $this->actingAs($this->staffUser())
            ->postJson("/api/v1/staff/requests/{$formInput->id}/process", [
                'fundcluster_id' => $bank->id,
                'uacs_id' => $uacs->id,
                'status' => 'processed',
                'ref_date' => today()->toDateString(),
            ])
            ->assertCreated();

        $this->assertDatabaseHas('staff_inputs', ['form_input_id' => $formInput->id]);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->staffUser())
            ->postJson('/api/v1/staff/requests/process', [])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_update_changes_status_on_staff_input(): void
    {
        $formInput = $this->makeFormInput();
        $staffInput = $this->processFormInput($formInput);
        $bank = $this->makeBankAccount();
        $uacs = $this->makeUacs();

        $this->actingAs($this->staffUser())
            ->putJson("/api/v1/staff/requests/{$staffInput->id}", [
                'fundcluster_id' => $bank->id,
                'uacs_id' => $uacs->id,
                'status' => 'cancelled',
                'ref_date' => today()->toDateString(),
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Processing updated successfully.');

        $this->assertDatabaseHas('staff_inputs', ['id' => $staffInput->id, 'status' => 'cancelled']);
    }

    // ── Update details ────────────────────────────────────────────────────────

    public function test_update_details_updates_form_input_fields(): void
    {
        $formInput = $this->makeFormInput();
        $option = PaymentDetailOption::create(['payment_desc' => 'Updated payment option']);

        $this->actingAs($this->staffUser())
            ->putJson("/api/v1/staff/requests/{$formInput->id}/details", [
                'firstname_or_office' => 'NewFirst',
                'lastname_or_agency' => 'NewLast',
                'amount' => 500,
                'payment_detail_option_id' => $option->id,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Request details updated successfully.');

        $this->assertDatabaseHas('form_inputs', [
            'id' => $formInput->id,
            'firstname_or_office' => 'NewFirst',
        ]);
    }

    // ── Student search ────────────────────────────────────────────────────────

    public function test_student_search_returns_results(): void
    {
        Student::factory()->create(['first_name' => 'Searchable', 'last_name' => 'Student']);

        $this->actingAs($this->staffUser())
            ->getJson('/api/v1/staff/requests/student-search?q=Searchable')
            ->assertOk()
            ->assertJsonStructure(['students'])
            ->assertJsonCount(1, 'students');
    }

    public function test_student_search_returns_at_most_20(): void
    {
        Student::factory()->count(25)->create(['first_name' => 'Common']);

        $response = $this->actingAs($this->staffUser())
            ->getJson('/api/v1/staff/requests/student-search?q=Common')
            ->assertOk();

        $this->assertLessThanOrEqual(20, count($response->json('students')));
    }

    public function test_student_search_empty_query_returns_up_to_20_students(): void
    {
        Student::factory()->count(3)->create();

        $this->actingAs($this->staffUser())
            ->getJson('/api/v1/staff/requests/student-search')
            ->assertOk()
            ->assertJsonCount(3, 'students');
    }

    // ── Link student ──────────────────────────────────────────────────────────

    public function test_link_student_requires_ledger_course(): void
    {
        // A FormInput with no course (or a non-ledger course) should 422.
        $formInput = $this->makeFormInput(['course_id' => null]);
        $student = Student::factory()->create();

        $this->actingAs($this->staffUser())
            ->putJson("/api/v1/staff/requests/{$formInput->id}/student", [
                'student_id' => $student->id,
            ])
            ->assertStatus(422);
    }

    public function test_link_student_succeeds_for_ledger_course(): void
    {
        $course = $this->makeCourse('Graduate School');
        $formInput = $this->makeFormInput(['course_id' => $course->id]);
        $student = Student::factory()->create();

        $this->actingAs($this->staffUser())
            ->putJson("/api/v1/staff/requests/{$formInput->id}/student", [
                'student_id' => $student->id,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Student matched to this Order of Payment.');

        $this->assertDatabaseHas('form_inputs', ['id' => $formInput->id, 'student_num' => $student->id]);
    }

    // ── Assign academic term ──────────────────────────────────────────────────

    public function test_assign_academic_term_succeeds_for_ledger_course(): void
    {
        $course = $this->makeCourse('School of Law');
        $formInput = $this->makeFormInput(['course_id' => $course->id]);
        $academicTerm = AcademicTerm::create([
            'school_year' => '2026-2027',
            'semester' => 'First Semester',
        ]);

        $this->actingAs($this->staffUser())
            ->putJson("/api/v1/staff/requests/{$formInput->id}/academic-term", [
                'academic_term' => $academicTerm->id,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Academic term assigned to this Order of Payment.');

        $this->assertDatabaseHas('form_inputs', ['id' => $formInput->id, 'academic_term' => $academicTerm->id]);
    }

    // ── Bulk email ────────────────────────────────────────────────────────────

    public function test_bulk_email_skips_unprocessed_requests(): void
    {
        $unprocessed = $this->makeFormInput(['email' => 'a@example.com']);
        $processed = $this->makeFormInput(['email' => 'b@example.com']);
        $this->processFormInput($processed);

        $response = $this->actingAs($this->staffUser())
            ->postJson('/api/v1/staff/requests/bulk-email-op', [
                'form_input_ids' => [$unprocessed->id, $processed->id],
            ])
            ->assertOk()
            ->assertJsonStructure(['message', 'form_input_ids', 'total']);

        // Only the processed one is eligible.
        $this->assertSame(1, $response->json('total'));
    }

    public function test_bulk_email_validates_required_ids(): void
    {
        $this->actingAs($this->staffUser())
            ->postJson('/api/v1/staff/requests/bulk-email-op', [])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    // ── Email recipients ──────────────────────────────────────────────────────

    public function test_email_recipients_returns_all_form_inputs(): void
    {
        $this->makeFormInput();
        $this->makeFormInput();

        $this->actingAs($this->staffUser())
            ->getJson('/api/v1/staff/requests/email-recipients')
            ->assertOk()
            ->assertJsonStructure(['data', 'queue_stalled'])
            ->assertJsonCount(2, 'data');
    }
}
