<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\FormInput;
use App\Models\GraduateLedger;
use App\Models\Membership;
use App\Models\PaymentDetailOption;
use App\Models\StaffInput;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_foreign_keys_prevent_invalid_relationships(): void
    {
        $this->expectException(QueryException::class);

        GraduateLedger::create([
            'student_id' => 999999,
            'course_id' => 999999,
            'academic_term_id' => 999999,
            'entry_type' => 'ar',
            'transaction_date' => '2026-01-01',
            'amount' => 100,
            'particulars' => 'Invalid',
            'status' => 'posted',
        ]);
    }

    public function test_required_columns_reject_null_values(): void
    {
        $this->expectException(QueryException::class);

        User::create(['name' => null, 'email' => null, 'password' => null]);
    }

    public function test_unique_constraints_work(): void
    {
        User::factory()->create(['email' => 'unique@example.com']);

        $this->expectException(QueryException::class);
        User::factory()->create(['email' => 'unique@example.com']);
    }

    public function test_cascade_delete_removes_dependent_staff_input(): void
    {
        $form = $this->formInput();
        $staffInput = StaffInput::create([
            'form_input_id' => $form->id,
            'ref_date' => '2026-01-01',
            'status' => 'processed',
        ]);

        $form->delete();

        $this->assertDatabaseMissing('staff_inputs', ['id' => $staffInput->id]);
    }

    public function test_user_soft_deletion_preserves_the_row_and_marks_deleted_at(): void
    {
        $user = User::factory()->create();
        $user->delete();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertNotNull(User::withTrashed()->find($user->id));
    }

    public function test_decimal_values_preserve_expected_precision(): void
    {
        $record = $this->ledger();
        $record->update(['amount' => '1234.56', 'rate' => '78.90']);
        $record->refresh();

        $this->assertSame('1234.56', $record->amount);
        $this->assertSame('78.90', $record->rate);
    }

    public function test_transaction_rolls_back_after_an_exception(): void
    {
        $student = Student::create(['first_name' => 'Rollback', 'last_name' => 'Test']);

        try {
            DB::transaction(function () use ($student): void {
                GraduateLedger::create([
                    'student_id' => $student->id,
                    'course_id' => null,
                    'academic_term_id' => null,
                    'entry_type' => 'ar',
                    'transaction_date' => '2026-01-01',
                    'amount' => 100,
                    'particulars' => 'Should rollback',
                    'status' => 'posted',
                ]);

                throw new \RuntimeException('forced rollback');
            });
        } catch (\RuntimeException) {
            // Expected test failure inside the transaction.
        }

        $this->assertDatabaseMissing('graduate_ledgers', ['particulars' => 'Should rollback']);
    }

    public function test_seeders_run_successfully_on_a_clean_test_database(): void
    {
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder'])
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', ['email' => 'admin@norsu.edu.ph']);
        $this->assertDatabaseHas('users', ['email' => 'staff@norsu.edu.ph']);
        $this->assertDatabaseCount('memberships', 3);
    }

    public function test_seeders_are_idempotent_and_do_not_create_duplicates(): void
    {
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder'])->assertExitCode(0);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder'])->assertExitCode(0);

        $this->assertSame(1, User::where('email', 'admin@norsu.edu.ph')->count());
        $this->assertSame(1, User::where('email', 'staff@norsu.edu.ph')->count());
        $this->assertSame(3, Membership::count());
        $this->assertSame(
            PaymentDetailOption::query()->count(),
            PaymentDetailOption::query()->distinct('payment_desc')->count('payment_desc'),
        );
    }

    public function test_migration_schema_contains_expected_integrity_constraints(): void
    {
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('graduate_ledgers'));
        $this->assertTrue(Schema::hasColumn('users', 'deleted_at'));
        $this->assertTrue(Schema::hasColumn('graduate_ledgers', 'amount'));
        $this->assertTrue(Schema::hasColumn('graduate_ledgers', 'student_id'));
    }

    private function formInput(): FormInput
    {
        $membership = Membership::firstOrCreate(['member_code' => 'INTEGRITY'], ['member_desc' => 'Integrity']);
        $option = PaymentDetailOption::firstOrCreate(['payment_desc' => 'Integrity Payment']);

        return FormInput::create([
            'reference_number' => 'INTEGRITY-'.uniqid(),
            'email' => 'integrity@example.com',
            'contact_num' => '09123456789',
            'firstname_or_office' => 'Integrity',
            'lastname_or_agency' => 'Test',
            'office_or_college' => 'Office',
            'position_or_designation' => 'Tester',
            'address' => 'Dumaguete',
            'amount' => 100,
            'request_type' => 'New Request',
            'membership_id' => $membership->id,
            'payment_detail_option_id' => $option->id,
        ]);
    }

    private function ledger(): GraduateLedger
    {
        $student = Student::create(['first_name' => 'Decimal', 'last_name' => 'Test']);
        $course = Course::create(['course_code' => 'INT-MBA', 'course_desc' => 'Integrity MBA', 'course_college' => 'Graduate School']);
        $term = AcademicTerm::create(['school_year' => '2025-2026', 'semester' => 'First Semester']);

        return GraduateLedger::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'academic_term_id' => $term->id,
            'entry_type' => 'ar',
            'transaction_date' => '2026-01-01',
            'amount' => '100.00',
            'rate' => '10.00',
            'particulars' => 'Integrity',
            'status' => 'posted',
        ]);
    }
}
