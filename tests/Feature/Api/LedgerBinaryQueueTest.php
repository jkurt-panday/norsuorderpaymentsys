<?php

namespace Tests\Feature\Api;

use App\Jobs\SendGraduateLedgerStatementEmail;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\GraduateLedger;
use App\Models\LawSchoolLedger;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class LedgerBinaryQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_ledger_exports_return_xlsx_and_email_dispatch_is_synchronous(): void
    {
        Bus::fake();
        $staff = User::factory()->create(['role' => 'staff']);
        $student = Student::create(['student_number' => 'S1', 'first_name' => 'A', 'last_name' => 'B', 'email' => 'a@example.com']);
        $term = AcademicTerm::create(['school_year' => '2026-2027', 'semester' => 'First Semester']);
        $course = Course::create(['course_code' => 'G1', 'course_desc' => 'G', 'course_college' => 'Graduate School']);
        GraduateLedger::create(['student_id' => $student->id, 'course_id' => $course->id, 'academic_term_id' => $term->id, 'entry_type' => 'ar', 'transaction_date' => '2026-01-01', 'particulars' => 'AR', 'rate' => 10, 'amount' => 10, 'status' => 'posted']);
        $this->actingAs($staff)->get('/api/v1/graduate-ledger/export')->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $lawCourse = Course::create(['course_code' => 'JD', 'course_desc' => 'Law', 'course_college' => 'School of Law']);
        LawSchoolLedger::create(['student_id' => $student->id, 'course_id' => $lawCourse->id, 'academic_term_id' => $term->id, 'entry_type' => 'ar', 'transaction_date' => '2026-01-01', 'particulars' => 'AR', 'rate' => 10, 'amount' => 10, 'status' => 'posted']);
        $this->actingAs($staff)->get('/api/v1/law-ledger/export?semester=First%20Semester&entry_type=ar')->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($staff)->postJson('/api/v1/graduate-ledger/send-emails', ['student_ids' => [$student->id]])->assertOk()->assertJsonPath('queued_count', 1)->assertJsonPath('sent_count', 1);
        Bus::assertDispatchedSync(SendGraduateLedgerStatementEmail::class);
    }
}
