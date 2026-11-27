<?php

namespace Tests\Feature\Api;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\GraduateLedger;
use App\Models\LawSchoolLedger;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LedgerAssessmentSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function fixtures(): array
    {
        $student = Student::create(['student_number' => '2026001', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.com']);
        $term = AcademicTerm::create(['school_year' => '2026-2027', 'semester' => 'First Semester']);
        $graduate = Course::create(['course_code' => 'MS', 'course_desc' => 'Master', 'course_college' => 'Graduate School']);
        $law = Course::create(['course_code' => 'JD', 'course_desc' => 'Juris Doctor', 'course_college' => 'School of Law']);

        return compact('student', 'term', 'graduate', 'law');
    }

    public function test_staff_can_create_list_and_view_balances_for_both_ledgers(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $f = $this->fixtures();
        foreach ([['graduate-ledger', $f['graduate']], ['law-ledger', $f['law']]] as [$endpoint, $course]) {
            $response = $this->actingAs($staff)->postJson('/api/v1/'.$endpoint, ['student_id' => $f['student']->id, 'course_id' => $course->id, 'academic_term_id' => $f['term']->id, 'entry_type' => 'ar', 'transaction_date' => '2026-11-13', 'particulars' => 'Assessment', 'rate' => 100, 'amount' => 100, 'status' => 'posted'])->assertCreated();
            $this->actingAs($staff)->getJson('/api/v1/'.$endpoint)->assertOk()->assertJsonStructure(['data', 'meta', 'links']);
            $ledger = $endpoint === 'graduate-ledger' ? GraduateLedger::first() : LawSchoolLedger::first();
            $this->actingAs($staff)->getJson('/api/v1/'.$endpoint.'/students/'.$f['student']->id.'/balance')->assertOk()->assertJsonPath('student.id', $f['student']->id);
            $this->actingAs($staff)->deleteJson('/api/v1/'.$endpoint.'/'.$ledger->id)->assertNoContent();
        }
    }

    public function test_staff_can_register_a_student_with_their_first_transaction_in_both_ledgers(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $f = $this->fixtures();

        foreach ([['graduate-ledger', $f['graduate'], 'NEW-GRAD'], ['law-ledger', $f['law'], 'NEW-LAW']] as [$endpoint, $course, $studentNumber]) {
            $response = $this->actingAs($staff)->postJson('/api/v1/'.$endpoint, [
                'new_student' => [
                    'student_number' => $studentNumber,
                    'email' => strtolower($studentNumber).'@example.com',
                    'last_name' => 'New',
                    'first_name' => ucfirst(strtolower($studentNumber)),
                    'middle_name' => 'Student',
                ],
                'course_id' => $course->id,
                'academic_term_id' => $f['term']->id,
                'entry_type' => 'ar',
                'transaction_date' => '2026-11-20',
                'particulars' => 'First Assessment',
                'rate' => 100,
                'amount' => 100,
            ])->assertCreated();

            $student = Student::where('student_number', $studentNumber)->firstOrFail();
            $response->assertJsonPath('data.student_id', $student->id);
            $table = $endpoint === 'graduate-ledger' ? 'graduate_ledgers' : 'law_school_ledgers';
            $this->assertDatabaseHas($table, ['student_id' => $student->id, 'particulars' => 'First Assessment']);
        }
    }

    public function test_inline_registration_rejects_duplicate_student_number_without_creating_a_transaction(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $f = $this->fixtures();

        $this->actingAs($staff)->postJson('/api/v1/graduate-ledger', [
            'new_student' => [
                'student_number' => $f['student']->student_number,
                'last_name' => 'Duplicate',
                'first_name' => 'Student',
            ],
            'course_id' => $f['graduate']->id,
            'academic_term_id' => $f['term']->id,
            'entry_type' => 'ar',
            'transaction_date' => '2026-11-20',
            'particulars' => 'Must Not Exist',
            'rate' => 100,
            'amount' => 100,
        ])->assertUnprocessable()->assertJsonValidationErrors('new_student.student_number');

        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseMissing('graduate_ledgers', ['particulars' => 'Must Not Exist']);
    }

    public function test_inline_student_creation_rolls_back_when_the_ledger_insert_fails(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $f = $this->fixtures();
        GraduateLedger::creating(fn () => throw new \RuntimeException('Simulated ledger failure'));

        try {
            $this->actingAs($staff)->postJson('/api/v1/graduate-ledger', [
                'new_student' => ['student_number' => 'ROLLBACK-STUDENT', 'last_name' => 'Rollback', 'first_name' => 'Student'],
                'course_id' => $f['graduate']->id,
                'academic_term_id' => $f['term']->id,
                'entry_type' => 'ar',
                'transaction_date' => '2026-11-20',
                'particulars' => 'Rollback Assessment',
                'rate' => 100,
                'amount' => 100,
            ]);
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated ledger failure', $exception->getMessage());
        }

        $this->assertDatabaseMissing('students', ['student_number' => 'ROLLBACK-STUDENT']);
    }

    public function test_law_cohort_creates_mixed_latin_honor_adjustments(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $f = $this->fixtures();
        $magna = Student::create(['student_number' => 'LAW-MAGNA', 'first_name' => 'Magna', 'last_name' => 'Student']);
        $cumLaude = Student::create(['student_number' => 'LAW-CUM', 'first_name' => 'Cum', 'last_name' => 'Student']);

        $this->actingAs($staff)->postJson('/api/v1/law-ledger/cohort-store', [
            'course_id' => $f['law']->id,
            'academic_term_id' => $f['term']->id,
            'entry_type' => 'ar',
            'transaction_date' => '2026-11-20',
            'particulars' => 'Law Cohort Tuition',
            'rate' => 1000,
            'status' => 'Paid',
            'cohort_rows' => [
                ['student_id' => $magna->id, 'units' => 2, 'rate' => 1000, 'amount' => 2000, 'latin_honor' => 'MAGNA', 'discount_amount' => 1],
                ['student_id' => $cumLaude->id, 'units' => 2, 'rate' => 1000, 'amount' => 2000, 'latin_honor' => 'CUM_LAUDE', 'discount_amount' => 1],
                ['student_id' => $f['student']->id, 'units' => 2, 'rate' => 1000, 'amount' => 2000],
            ],
        ])->assertCreated()
            ->assertJsonPath('created_count', 3)
            ->assertJsonPath('scholarships_applied', 2);

        $magnaAr = LawSchoolLedger::where('student_id', $magna->id)->where('entry_type', 'ar')->firstOrFail();
        $cumAr = LawSchoolLedger::where('student_id', $cumLaude->id)->where('entry_type', 'ar')->firstOrFail();
        $this->assertSame('1500.00', $magnaAr->discount_amount);
        $this->assertSame('1000.00', $cumAr->discount_amount);
        $this->assertDatabaseHas('law_school_ledgers', ['reference_number' => 'HONOR-MAG-'.$magnaAr->id, 'entry_type' => 'adjustment', 'amount' => '1500.00']);
        $this->assertDatabaseHas('law_school_ledgers', ['reference_number' => 'HONOR-CUM-'.$cumAr->id, 'entry_type' => 'adjustment', 'amount' => '1000.00']);
        $this->assertSame(5, LawSchoolLedger::count());
    }

    public function test_law_cohort_rolls_back_all_rows_when_an_honor_adjustment_fails(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $f = $this->fixtures();
        $second = Student::create(['student_number' => 'LAW-ROLLBACK', 'first_name' => 'Rollback', 'last_name' => 'Student']);
        LawSchoolLedger::creating(function (LawSchoolLedger $record): void {
            if ($record->entry_type === 'adjustment') {
                throw new \RuntimeException('Simulated honor adjustment failure');
            }
        });

        try {
            $this->actingAs($staff)->postJson('/api/v1/law-ledger/cohort-store', [
                'course_id' => $f['law']->id,
                'academic_term_id' => $f['term']->id,
                'entry_type' => 'ar',
                'transaction_date' => '2026-11-20',
                'particulars' => 'Must Roll Back',
                'rate' => 100,
                'amount' => 100,
                'cohort_rows' => [
                    ['student_id' => $f['student']->id, 'rate' => 100, 'amount' => 100],
                    ['student_id' => $second->id, 'rate' => 100, 'amount' => 100, 'latin_honor' => 'SUMMA'],
                ],
            ]);
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated honor adjustment failure', $exception->getMessage());
        }

        $this->assertDatabaseMissing('law_school_ledgers', ['particulars' => 'Must Roll Back']);
    }

    public function test_law_single_create_applies_latin_honor_like_the_monolith(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $f = $this->fixtures();

        $this->actingAs($staff)->postJson('/api/v1/law-ledger', [
            'student_id' => $f['student']->id,
            'course_id' => $f['law']->id,
            'academic_term_id' => $f['term']->id,
            'entry_type' => 'ar',
            'transaction_date' => '2026-11-20',
            'particulars' => 'Single Magna Tuition',
            'rate' => 10000,
            'amount' => 10000,
            'latin_honor' => 'MAGNA',
            'discount_amount' => 1,
        ])->assertCreated()->assertJsonPath('scholarships_applied', 1);

        $ar = LawSchoolLedger::where('entry_type', 'ar')->firstOrFail();
        $this->assertSame('MAGNA', $ar->latin_honor);
        $this->assertSame('7500.00', $ar->discount_amount);
        $this->assertDatabaseHas('law_school_ledgers', [
            'entry_type' => 'adjustment',
            'reference_number' => 'HONOR-MAG-'.$ar->id,
            'amount' => '7500.00',
        ]);
    }

    public function test_law_batch_applies_each_honor_and_ignores_honor_on_non_ar_rows(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $f = $this->fixtures();

        $this->actingAs($staff)->postJson('/api/v1/law-ledger', [
            'student_id' => $f['student']->id,
            'course_id' => $f['law']->id,
            'academic_term_id' => $f['term']->id,
            'transaction_date' => '2026-11-20',
            'entry_type' => 'ar',
            'particulars' => 'Batch',
            'rate' => 0,
            'amount' => 0,
            'items' => [
                ['entry_type' => 'ar', 'particulars' => 'Summa Tuition', 'rate' => 4000, 'amount' => 4000, 'latin_honor' => 'SUMMA', 'discount_amount' => 1],
                ['entry_type' => 'ar', 'particulars' => 'Cum Tuition', 'rate' => 4000, 'amount' => 4000, 'latin_honor' => 'CUM_LAUDE', 'discount_amount' => 1],
                ['entry_type' => 'payment', 'particulars' => 'Payment', 'rate' => 1000, 'amount' => 1000, 'latin_honor' => 'MAGNA', 'discount_amount' => 750],
            ],
        ])->assertCreated()->assertJsonPath('scholarships_applied', 2);

        $summa = LawSchoolLedger::where('particulars', 'Summa Tuition')->firstOrFail();
        $cum = LawSchoolLedger::where('particulars', 'Cum Tuition')->firstOrFail();
        $payment = LawSchoolLedger::where('particulars', 'Payment')->firstOrFail();
        $this->assertSame('4000.00', $summa->discount_amount);
        $this->assertSame('2000.00', $cum->discount_amount);
        $this->assertNull($payment->latin_honor);
        $this->assertSame('0.00', $payment->discount_amount);
        $this->assertDatabaseHas('law_school_ledgers', ['reference_number' => 'HONOR-SUM-'.$summa->id, 'amount' => '4000.00']);
        $this->assertDatabaseHas('law_school_ledgers', ['reference_number' => 'HONOR-CUM-'.$cum->id, 'amount' => '2000.00']);
        $this->assertSame(5, LawSchoolLedger::count());
    }

    public function test_law_single_honor_rolls_back_source_ar_when_adjustment_fails(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $f = $this->fixtures();
        LawSchoolLedger::creating(function (LawSchoolLedger $record): void {
            if ($record->entry_type === 'adjustment') {
                throw new \RuntimeException('Simulated single honor failure');
            }
        });

        try {
            $this->actingAs($staff)->postJson('/api/v1/law-ledger', [
                'student_id' => $f['student']->id,
                'course_id' => $f['law']->id,
                'academic_term_id' => $f['term']->id,
                'entry_type' => 'ar',
                'transaction_date' => '2026-11-20',
                'particulars' => 'Rollback Single Honor',
                'rate' => 1000,
                'amount' => 1000,
                'latin_honor' => 'SUMMA',
                'discount_amount' => 1000,
            ]);
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated single honor failure', $exception->getMessage());
        }

        $this->assertDatabaseMissing('law_school_ledgers', ['particulars' => 'Rollback Single Honor']);
    }

    public function test_staff_can_update_profile_and_password(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'password' => Hash::make('OldPassword123!')]);
        $this->actingAs($staff)->patchJson('/api/v1/settings/profile', ['name' => 'Updated Name', 'email' => 'updated@example.com'])->assertOk();
        $this->actingAs($staff)->putJson('/api/v1/settings/password', ['current_password' => 'OldPassword123!', 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'])->assertOk();
    }

    public function test_non_staff_cannot_access_ledger_or_assessment_api(): void
    {
        $cashier = User::factory()->cashier()->create();
        $this->actingAs($cashier)->getJson('/api/v1/graduate-ledger')->assertForbidden();
        $this->actingAs($cashier)->getJson('/api/v1/assessments')->assertForbidden();
    }
}
