<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
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
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OpStudentMatchingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_staff_can_search_all_students_by_number_or_name(): void
    {
        $staff = User::factory()->staff()->create();
        $student = Student::create([
            'student_number' => '202600321',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
        ]);

        $this->actingAs($staff)
            ->getJson('/staff/requests/student-search?q=600321')
            ->assertSuccessful()
            ->assertJsonPath('students.0.id', $student->id)
            ->assertJsonPath('students.0.student_number', '202600321');

        $this->actingAs($staff)
            ->getJson('/staff/requests/student-search?q=Santos')
            ->assertSuccessful()
            ->assertJsonPath('students.0.id', $student->id);
    }

    public function test_admin_can_search_students_from_the_staff_processing_page(): void
    {
        $admin = User::factory()->admin()->create();
        $student = Student::create([
            'student_number' => '202600325',
            'first_name' => 'Admin',
            'last_name' => 'Searchable',
        ]);

        $this->actingAs($admin)
            ->getJson('/staff/requests/student-search?q=600325')
            ->assertSuccessful()
            ->assertJsonPath('students.0.id', $student->id);
    }

    public function test_staff_can_persistently_link_an_existing_student(): void
    {
        $staff = User::factory()->staff()->create();
        $student = Student::create([
            'student_number' => '202600322',
            'first_name' => 'Pedro',
            'last_name' => 'Reyes',
        ]);
        $formInput = $this->makeLedgerFormInput();

        $this->actingAs($staff)
            ->put("/staff/requests/{$formInput->id}/student", [
                'student_id' => $student->id,
            ])
            ->assertRedirect();

        $this->assertSame($student->id, $formInput->fresh()->student_num);
    }

    public function test_linking_fills_missing_student_number_and_email_from_the_op(): void
    {
        $staff = User::factory()->staff()->create();
        $student = Student::create([
            'student_number' => null,
            'email' => null,
            'first_name' => 'Missing',
            'last_name' => 'Details',
        ]);
        $formInput = $this->makeLedgerFormInput([
            'submitted_student_number' => '2026-00327',
            'email' => 'linked.student@example.com',
        ]);

        $this->actingAs($staff)
            ->put("/staff/requests/{$formInput->id}/student", [
                'student_id' => $student->id,
            ])
            ->assertRedirect();

        $student->refresh();
        $this->assertSame('202600327', $student->student_number);
        $this->assertSame('linked.student@example.com', $student->email);
        $this->assertSame($student->id, $formInput->fresh()->student_num);
    }

    public function test_linking_never_overwrites_existing_student_number_or_email(): void
    {
        $staff = User::factory()->staff()->create();
        $student = Student::create([
            'student_number' => '202600328',
            'email' => 'verified@example.com',
            'first_name' => 'Verified',
            'last_name' => 'Student',
        ]);
        $formInput = $this->makeLedgerFormInput([
            'submitted_student_number' => '202600999',
            'email' => 'different@example.com',
        ]);

        $this->actingAs($staff)
            ->put("/staff/requests/{$formInput->id}/student", [
                'student_id' => $student->id,
            ])
            ->assertRedirect();

        $student->refresh();
        $this->assertSame('202600328', $student->student_number);
        $this->assertSame('verified@example.com', $student->email);
        $this->assertSame($student->id, $formInput->fresh()->student_num);
    }

    public function test_linking_rejects_a_number_owned_by_another_student(): void
    {
        $staff = User::factory()->staff()->create();
        Student::create([
            'student_number' => '2026-00329',
            'first_name' => 'Number',
            'last_name' => 'Owner',
        ]);
        $selectedStudent = Student::create([
            'student_number' => null,
            'first_name' => 'Wrong',
            'last_name' => 'Selection',
        ]);
        $formInput = $this->makeLedgerFormInput([
            'submitted_student_number' => '202600329',
        ]);

        $this->actingAs($staff)
            ->put("/staff/requests/{$formInput->id}/student", [
                'student_id' => $selectedStudent->id,
            ])
            ->assertSessionHasErrors('student_id');

        $this->assertNull($selectedStudent->fresh()->student_number);
        $this->assertNull($formInput->fresh()->student_num);
    }

    public function test_create_and_match_reuses_a_normalized_existing_student_number(): void
    {
        $staff = User::factory()->staff()->create();
        $student = Student::create([
            'student_number' => '2026-00323',
            'first_name' => 'Existing',
            'last_name' => 'Student',
        ]);
        $formInput = $this->makeLedgerFormInput([
            'submitted_student_number' => '202600323',
        ]);

        $this->actingAs($staff)
            ->post("/staff/requests/{$formInput->id}/student", [
                'student_number' => '202600323',
                'first_name' => 'Duplicate',
                'middle_name' => null,
                'last_name' => 'Attempt',
                'email' => 'duplicate@example.com',
                'contact_num' => '09123456789',
            ])
            ->assertRedirect();

        $this->assertSame(1, Student::query()->count());
        $this->assertSame($student->id, $formInput->fresh()->student_num);
    }

    public function test_cashier_does_not_fall_back_to_matching_an_unlinked_student_by_name(): void
    {
        $cashier = User::factory()->cashier()->create();
        $student = Student::create([
            'student_number' => '202600324',
            'first_name' => 'Same',
            'last_name' => 'Name',
        ]);
        $formInput = $this->makeLedgerFormInput([
            'firstname_or_office' => 'Same',
            'lastname_or_agency' => 'Name',
            'student_num' => null,
        ]);
        $course = $formInput->course;
        $term = $formInput->academicTerm;

        GraduateLedger::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'academic_term_id' => $term->id,
            'entry_type' => 'ar',
            'transaction_date' => '2026-09-01',
            'reference_number' => 'AR-MATCH-GUARD',
            'particulars' => 'Tuition',
            'amount' => 1000,
            'status' => 'posted',
        ]);

        $bank = BankAccountInfo::firstOrCreate(
            ['account_num' => '123456789'],
            ['account_name' => 'Main', 'bank_name' => 'Landbank', 'fund_cluster' => '01'],
        );
        $uacs = UACS::firstOrCreate(
            ['object_code' => '4020101000'],
            ['account_title' => 'Tuition Fees'],
        );
        $staffInput = StaffInput::create([
            'form_input_id' => $formInput->id,
            'fundcluster_id' => $bank->id,
            'ref_date' => '2026-09-01',
            'uacs_id' => $uacs->id,
            'status' => 'processed',
        ]);

        $this->actingAs($cashier)
            ->put("/cashier/requests/{$staffInput->id}/payment", [
                'or_no' => '2026-00324',
                'or_date' => '2026-09-02',
            ])
            ->assertRedirect();

        $this->assertFalse(GraduateLedger::query()
            ->where('reference_number', '2026-00324')
            ->exists());
    }

    public function test_different_ops_can_post_with_the_same_or_number(): void
    {
        $cashier = User::factory()->cashier()->create();
        $student = Student::create([
            'student_number' => '202600326',
            'first_name' => 'Shared',
            'last_name' => 'Receipt',
        ]);
        $firstForm = $this->makeLedgerFormInput([
            'student_num' => $student->id,
            'amount' => 500,
        ]);
        $secondForm = $this->makeLedgerFormInput([
            'student_num' => $student->id,
            'amount' => 750,
        ]);
        $bank = BankAccountInfo::firstOrCreate(
            ['account_num' => '123456789'],
            ['account_name' => 'Main', 'bank_name' => 'Landbank', 'fund_cluster' => '01'],
        );
        $uacs = UACS::firstOrCreate(
            ['object_code' => '4020101000'],
            ['account_title' => 'Tuition Fees'],
        );

        $firstPayment = StaffInput::create([
            'form_input_id' => $firstForm->id,
            'fundcluster_id' => $bank->id,
            'ref_date' => '2026-09-01',
            'uacs_id' => $uacs->id,
            'status' => 'paid',
            'or_no' => 'SHARED-OR-001',
            'or_date' => '2026-09-02',
        ]);
        $secondPayment = StaffInput::create([
            'form_input_id' => $secondForm->id,
            'fundcluster_id' => $bank->id,
            'ref_date' => '2026-09-01',
            'uacs_id' => $uacs->id,
            'status' => 'paid',
            'or_no' => 'SHARED-OR-001',
            'or_date' => '2026-09-02',
        ]);

        $this->actingAs($cashier);
        $postingService = app(CashierLedgerPostingService::class);

        $this->assertTrue($postingService->postPayment($firstPayment)['posted']);
        $this->assertTrue($postingService->postPayment($secondPayment)['posted']);
        $this->assertSame(2, GraduateLedger::query()
            ->where('reference_number', 'SHARED-OR-001')
            ->where('entry_type', 'payment')
            ->count());
        $this->assertTrue(GraduateLedger::query()
            ->where('remarks', 'OP:'.$firstForm->id)
            ->exists());
        $this->assertTrue(GraduateLedger::query()
            ->where('remarks', 'OP:'.$secondForm->id)
            ->exists());
    }

    public function test_non_ledger_and_course_less_ops_do_not_require_student_matching(): void
    {
        $postingService = app(CashierLedgerPostingService::class);

        foreach ([null, 'General', 'Undergraduate'] as $index => $college) {
            $courseId = null;

            if ($college !== null) {
                $courseId = Course::firstOrCreate(
                    ['course_code' => 'NO-LEDGER-'.$index],
                    [
                        'course_desc' => $college.' Payment',
                        'course_college' => $college,
                    ],
                )->id;
            }

            $formInput = $this->makeLedgerFormInput([
                'course_id' => $courseId,
                'student_num' => null,
            ]);
            $payment = $this->makePaidStaffInput($formInput, 'NO-LEDGER-'.$index);
            $result = $postingService->postPayment($payment);

            $this->assertFalse($result['posted']);
            $this->assertSame('not_required', $result['reason']);
            $this->assertNull($result['ledger']);
        }

        $this->assertSame(0, GraduateLedger::query()->count());
    }

    public function test_staff_can_assign_an_academic_term_to_a_ledger_op(): void
    {
        $staff = User::factory()->staff()->create();
        $formInput = $this->makeLedgerFormInput(['academic_term' => null]);
        $term = AcademicTerm::firstOrCreate([
            'school_year' => '2027-2028',
            'semester' => 'Second Semester',
        ]);

        $this->actingAs($staff)
            ->put("/staff/requests/{$formInput->id}/academic-term", [
                'academic_term' => $term->id,
            ])
            ->assertRedirect();

        $this->assertSame($term->id, $formInput->fresh()->academic_term);
    }

    public function test_staff_cannot_process_a_ledger_op_without_an_academic_term(): void
    {
        $staff = User::factory()->staff()->create();
        $formInput = $this->makeLedgerFormInput(['academic_term' => null]);
        $bank = BankAccountInfo::firstOrCreate(
            ['account_num' => '123456789'],
            ['account_name' => 'Main', 'bank_name' => 'Landbank', 'fund_cluster' => '01'],
        );
        $uacs = UACS::firstOrCreate(
            ['object_code' => '4020101000'],
            ['account_title' => 'Tuition Fees'],
        );

        $this->actingAs($staff)
            ->post('/staff/requests/process', [
                'form_input_id' => $formInput->id,
                'fundcluster_id' => $bank->id,
                'ref_document_id' => null,
                'ref_date' => '2026-09-22',
                'uacs_id' => $uacs->id,
                'status' => 'processed',
                'purpose' => null,
            ])
            ->assertRedirect()
            ->assertSessionHas('error', fn (string $message): bool => str_contains(
                $message,
                'Select an academic term',
            ));

        $this->assertFalse($formInput->staffInput()->exists());
    }

    /** @param array<string, mixed> $overrides */
    private function makeLedgerFormInput(array $overrides = []): FormInput
    {
        $course = Course::firstOrCreate(
            ['course_code' => 'MATCH-MBA'],
            ['course_desc' => 'Matching Test MBA', 'course_college' => 'Graduate School'],
        );
        $term = AcademicTerm::firstOrCreate([
            'school_year' => '2026-2027',
            'semester' => 'First Semester',
        ]);
        $membership = Membership::firstOrCreate(
            ['member_code' => 'MATCH-STUDENT'],
            ['member_desc' => 'Student'],
        );
        $paymentOption = PaymentDetailOption::firstOrCreate([
            'payment_desc' => 'Matching Test Tuition',
        ]);

        return FormInput::create(array_merge([
            'reference_number' => 'OP-MATCH-'.uniqid(),
            'email' => 'student@example.com',
            'contact_num' => '09123456789',
            'firstname_or_office' => 'Test',
            'middlename_or_project' => null,
            'lastname_or_agency' => 'Student',
            'office_or_college' => 'Graduate School',
            'position_or_designation' => 'Student',
            'address' => 'Dumaguete City',
            'amount' => 500,
            'request_type' => 'New Request',
            'membership_id' => $membership->id,
            'payment_detail_option_id' => $paymentOption->id,
            'student_num' => null,
            'submitted_student_number' => '202600000',
            'course_id' => $course->id,
            'academic_term' => $term->id,
        ], $overrides));
    }

    private function makePaidStaffInput(FormInput $formInput, string $orNo): StaffInput
    {
        $bank = BankAccountInfo::firstOrCreate(
            ['account_num' => '123456789'],
            ['account_name' => 'Main', 'bank_name' => 'Landbank', 'fund_cluster' => '01'],
        );
        $uacs = UACS::firstOrCreate(
            ['object_code' => '4020101000'],
            ['account_title' => 'Tuition Fees'],
        );

        return StaffInput::create([
            'form_input_id' => $formInput->id,
            'fundcluster_id' => $bank->id,
            'ref_date' => '2026-09-01',
            'uacs_id' => $uacs->id,
            'status' => 'paid',
            'or_no' => $orNo,
            'or_date' => '2026-09-02',
        ]);
    }
}
