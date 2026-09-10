<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AssessmentForm;
use App\Models\Course;
use App\Models\Courses;
use App\Models\GraduateLedger;
use App\Models\LawSchoolLedger;
use App\Models\Student;
use App\Services\LedgerMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentStatementPrinterTest extends TestCase
{
    use RefreshDatabase;

    public function test_graduate_student_id_match_is_term_specific_and_calculates_the_summary(): void
    {
        $assessment = $this->assessment([
            'enrolled_under' => 'Graduate',
            'student_id' => '2026-00123',
        ]);
        $student = Student::create([
            'student_number' => '2026-00123',
            'last_name' => 'Reyes',
            'first_name' => 'Maria',
        ]);
        $course = $this->course('MBA', 'Graduate School', 'Master of Business Administration');
        $requestedTerm = $this->academicTerm('2025-2026', 'First Semester', 1);
        $otherTerm = $this->academicTerm('2025-2026', 'Second Semester', 2);

        $this->graduateRecord($student, $course, $requestedTerm, 'ar', 1500, 'AR-001');
        $this->graduateRecord($student, $course, $requestedTerm, 'payment', 500, 'OR-001');
        $this->graduateRecord($student, $course, $requestedTerm, 'adjustment', 100, 'ADJ-001');
        $this->graduateRecord($student, $course, $otherTerm, 'ar', 9000, 'AR-OTHER');

        $statement = app(LedgerMatchingService::class)->forAssessment($assessment);

        $this->assertSame('matched', $statement['matchStatus']);
        $this->assertSame('2026-00123', $statement['selectedStudent']['studentId']);
        $this->assertCount(3, $statement['records']);
        $this->assertSame(1500.0, $statement['summary']['totalCharges']);
        $this->assertSame(600.0, $statement['summary']['totalPayments']);
        $this->assertSame(900.0, $statement['summary']['outstandingBalance']);
        $this->assertNotContains('AR-OTHER', collect($statement['records'])->pluck('referenceNo')->all());
    }

    public function test_graduate_student_can_be_matched_by_a_normalized_name_when_ids_are_blank(): void
    {
        $assessment = $this->assessment([
            'enrolled_under' => 'Graduate',
            'student_id' => null,
            'first_name' => '  Ana  Marie ',
            'middle_name' => 'Q.',
            'last_name' => 'Santos',
        ]);
        $student = Student::create([
            'student_number' => null,
            'first_name' => 'Ana Marie',
            'middle_name' => 'Q',
            'last_name' => 'Santos',
        ]);
        $course = $this->course('MAED');
        $term = $this->academicTerm('2025-2026', 'First Semester', 1);
        $this->graduateRecord($student, $course, $term, 'ar', 800, 'AR-NAME');

        $statement = app(LedgerMatchingService::class)->forAssessment($assessment);

        $this->assertSame('matched', $statement['matchStatus']);
        $this->assertSame('graduate:'.$student->id, $statement['selectedStudent']['key']);
    }

    public function test_ambiguous_names_require_a_manual_term_candidate_selection(): void
    {
        $assessment = $this->assessment([
            'enrolled_under' => 'Graduate',
            'student_id' => null,
            'first_name' => 'Juan',
            'middle_name' => null,
            'last_name' => 'Cruz',
        ]);
        $course = $this->course('MSIT');
        $term = $this->academicTerm('2025-2026', 'First Semester', 1);
        $first = Student::create(['first_name' => 'Juan', 'middle_name' => 'A', 'last_name' => 'Cruz']);
        $second = Student::create(['first_name' => 'Juan', 'middle_name' => 'B', 'last_name' => 'Cruz']);
        $this->graduateRecord($first, $course, $term, 'ar', 500, 'AR-A');
        $this->graduateRecord($second, $course, $term, 'ar', 700, 'AR-B');

        $service = app(LedgerMatchingService::class);
        $ambiguous = $service->forAssessment($assessment);
        $selected = $service->forAssessment($assessment, 'graduate:'.$second->id);

        $this->assertSame('ambiguous', $ambiguous['matchStatus']);
        $this->assertNull($ambiguous['selectedStudent']);
        $this->assertCount(2, $ambiguous['candidates']);
        $this->assertSame('manual', $selected['matchStatus']);
        $this->assertSame('graduate:'.$second->id, $selected['selectedStudent']['key']);
        $this->assertSame(700.0, $selected['summary']['totalCharges']);
    }

    public function test_law_student_id_match_normalizes_the_semester_and_excludes_other_terms(): void
    {
        $assessment = $this->assessment([
            'enrolled_under' => 'School of Law',
            'student_id' => 'LAW-100',
            'semester' => 'Second Semester',
        ]);
        $student = Student::create([
            'student_number' => 'LAW-100',
            'last_name' => 'Dela Cruz',
            'first_name' => 'Juan',
            'middle_name' => 'Q',
        ]);
        $course = $this->course('JD', 'School of Law');
        $secondTerm = $this->academicTerm('2025-2026', 'Second Semester');
        $firstTerm = $this->academicTerm('2025-2026', 'First Semester');
        $this->lawRecord($student, $course, $secondTerm, 'ar', 2000, 'LAW-AR');
        $this->lawRecord($student, $course, $secondTerm, 'payment', 750, 'LAW-OR');
        $this->lawRecord($student, $course, $firstTerm, 'ar', 9000, 'LAW-OTHER');

        $statement = app(LedgerMatchingService::class)->forAssessment($assessment);

        $this->assertSame('matched', $statement['matchStatus']);
        $this->assertCount(2, $statement['records']);
        $this->assertSame('student_id', $statement['selectedStudent']['pdfQueryKey']);
        $this->assertSame(2000.0, $statement['summary']['totalCharges']);
        $this->assertSame(750.0, $statement['summary']['totalPayments']);
        $this->assertSame(1250.0, $statement['summary']['outstandingBalance']);
    }

    public function test_undergraduate_assessments_return_an_unsupported_statement(): void
    {
        $statement = app(LedgerMatchingService::class)->forAssessment($this->assessment([
            'enrolled_under' => 'Undergraduate',
        ]));

        $this->assertSame('unsupported', $statement['source']);
        $this->assertSame('unsupported', $statement['matchStatus']);
        $this->assertNull($statement['selectedStudent']);
        $this->assertSame([], $statement['records']);
    }

    private function assessment(array $attributes): AssessmentForm
    {
        $course = Courses::create([
            'course_code' => 'BS-TEST',
            'course_desc' => 'Test Course',
        ]);

        return AssessmentForm::create(array_merge([
            'reference_number' => 'ASSESS-001',
            'email' => 'student@example.test',
            'contact_num' => '09123456789',
            'first_name' => 'Maria',
            'middle_name' => null,
            'last_name' => 'Reyes',
            'student_id' => '2026-00123',
            'course_id' => $course->id,
            'address' => 'Dumaguete City',
            'enrolled_under' => 'Graduate',
            'sy_last_attended' => '2025-2026',
            'semester' => 'First Semester',
        ], $attributes));
    }

    private function academicTerm(string $schoolYear, string $semester, ?int $sortOrder = null): AcademicTerm
    {
        return AcademicTerm::create([
            'school_year' => $schoolYear,
            'semester' => $semester,
        ]);
    }

    private function graduateRecord(
        Student $student,
        Course $course,
        AcademicTerm $term,
        string $entryType,
        float $amount,
        string $reference,
    ): GraduateLedger {
        return GraduateLedger::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'academic_term_id' => $term->id,
            'entry_type' => $entryType,
            'transaction_date' => '2026-01-15',
            'reference_number' => $reference,
            'particulars' => 'Tuition',
            'amount' => $amount,
            'status' => 'posted',
        ]);
    }

    private function lawRecord(
        Student $student,
        Course $course,
        AcademicTerm $term,
        string $entryType,
        float $amount,
        string $reference,
    ): LawSchoolLedger {
        return LawSchoolLedger::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'academic_term_id' => $term->id,
            'transaction_date' => '2026-01-15',
            'reference_number' => $reference,
            'particulars' => 'Tuition',
            'entry_type' => $entryType,
            'amount' => $amount,
            'status' => 'posted',
        ]);
    }

    private function course(string $code, string $college = 'Graduate School', ?string $description = null): Course
    {
        return Course::create([
            'course_code' => $code,
            'course_desc' => $description ?? $code,
            'course_college' => $college,
        ]);
    }
}
