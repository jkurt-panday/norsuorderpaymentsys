<?php

namespace Tests\Feature\Api;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\GraduateLedger;
use App\Models\LawSchoolLedger;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test for PUT /api/v1/{graduate|law}-ledger/{id} returning 404.
 *
 * Root cause: update() declared three parameters (Request, string $ledger, int $id).
 * Laravel's dependency injector bound the single URL segment {id} to $ledger and
 * resolved $id from the route defaults bag, yielding (int)"graduate" = 0 → findOrFail(0) → 404.
 * Fix: drop $ledger from the signature and read it via $request->route('ledger').
 */
class LedgerCrudTest extends TestCase
{
    use RefreshDatabase;

    // ── helpers ───────────────────────────────────────────────────────────────

    private function staff(): User
    {
        return User::factory()->create(['role' => 'staff']);
    }

    private function term(): AcademicTerm
    {
        return AcademicTerm::create([
            'school_year' => '2026-2027',
            'semester'    => 'First Semester',
        ]);
    }

    private function graduateCourse(): Course
    {
        return Course::create([
            'course_code'    => 'MBA',
            'course_desc'    => 'Master of Business Administration',
            'course_college' => 'Graduate School',
        ]);
    }

    private function lawCourse(): Course
    {
        return Course::create([
            'course_code'    => 'JD',
            'course_desc'    => 'Juris Doctor',
            'course_college' => 'School of Law',
        ]);
    }

    private function minPayload(int $courseId, int $termId, int $studentId): array
    {
        return [
            'student_id'       => $studentId,
            'course_id'        => $courseId,
            'academic_term_id' => $termId,
            'entry_type'       => 'payment',
            'transaction_date' => '2026-11-01',
            'particulars'      => 'Test payment',
            'rate'             => 0,
            'amount'           => 500,
        ];
    }

    // ── graduate ledger ───────────────────────────────────────────────────────

    public function test_put_graduate_ledger_returns_200_not_404(): void
    {
        $staff   = $this->staff();
        $term    = $this->term();
        $course  = $this->graduateCourse();
        $student = Student::factory()->create();

        $record = GraduateLedger::create([
            'student_id'       => $student->id,
            'course_id'        => $course->id,
            'academic_term_id' => $term->id,
            'entry_type'       => 'payment',
            'transaction_date' => '2026-11-01',
            'particulars'      => 'Original',
            'rate'             => 0,
            'amount'           => 100,
            'status'           => 'posted',
        ]);

        $this->actingAs($staff)
            ->putJson("/api/v1/graduate-ledger/{$record->id}", array_merge(
                $this->minPayload($course->id, $term->id, $student->id),
                ['particulars' => 'Updated particulars', 'amount' => 750],
            ))
            ->assertOk()
            ->assertJsonPath('message', 'Ledger transaction updated successfully.')
            ->assertJsonPath('data.id', $record->id)
            ->assertJsonPath('data.particulars', 'Updated particulars');
    }

    public function test_put_graduate_ledger_404_for_missing_record(): void
    {
        $staff  = $this->staff();
        $term   = $this->term();
        $course = $this->graduateCourse();
        $student = Student::factory()->create();

        $this->actingAs($staff)
            ->putJson('/api/v1/graduate-ledger/99999', $this->minPayload($course->id, $term->id, $student->id))
            ->assertNotFound();
    }

    // ── law ledger ────────────────────────────────────────────────────────────

    public function test_put_law_ledger_returns_200_not_404(): void
    {
        $staff   = $this->staff();
        $term    = $this->term();
        $course  = $this->lawCourse();
        $student = Student::factory()->create();

        $record = LawSchoolLedger::create([
            'student_id'       => $student->id,
            'course_id'        => $course->id,
            'academic_term_id' => $term->id,
            'entry_type'       => 'payment',
            'transaction_date' => '2026-11-01',
            'particulars'      => 'Original',
            'rate'             => 0,
            'amount'           => 200,
            'status'           => 'Paid',
        ]);

        $this->actingAs($staff)
            ->putJson("/api/v1/law-ledger/{$record->id}", array_merge(
                $this->minPayload($course->id, $term->id, $student->id),
                ['particulars' => 'Updated law particulars', 'amount' => 900],
            ))
            ->assertOk()
            ->assertJsonPath('message', 'Ledger transaction updated successfully.')
            ->assertJsonPath('data.id', $record->id)
            ->assertJsonPath('data.particulars', 'Updated law particulars');
    }

    public function test_put_law_ledger_404_for_missing_record(): void
    {
        $staff   = $this->staff();
        $term    = $this->term();
        $course  = $this->lawCourse();
        $student = Student::factory()->create();

        $this->actingAs($staff)
            ->putJson('/api/v1/law-ledger/99999', $this->minPayload($course->id, $term->id, $student->id))
            ->assertNotFound();
    }
}
