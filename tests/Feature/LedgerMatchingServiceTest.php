<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AssessmentForm;
use App\Models\Course;
use App\Models\GraduateLedger;
use App\Models\LawSchoolLedger;
use App\Models\Student;
use App\Services\LedgerMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerMatchingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_graduate_student_matches_by_student_id(): void
    {
        $student = $this->student('202600001', 'Maria', 'Reyes');
        $this->graduateRecord($student, 'AR-ID');

        $result = $this->match(['student_id' => '202600001']);

        $this->assertSame('matched', $result['matchStatus']);
        $this->assertSame($student->student_number, $result['selectedStudent']['studentId']);
    }

    public function test_law_student_matches_by_student_id(): void
    {
        $student = $this->student('LAW-001', 'Juan', 'Cruz');
        $this->lawRecord($student, 'LAW-ID');

        $result = $this->match(['enrolled_under' => 'School of Law', 'student_id' => 'LAW-001']);

        $this->assertSame('matched', $result['matchStatus']);
        $this->assertSame('LAW-001', $result['selectedStudent']['studentId']);
    }

    public function test_student_matches_by_first_and_last_name(): void
    {
        $student = $this->student('202600002', 'Ana', 'Santos');
        $this->graduateRecord($student, 'AR-NAME');

        $result = $this->match(['student_id' => null, 'first_name' => 'Ana', 'last_name' => 'Santos']);

        $this->assertSame('matched', $result['matchStatus']);
    }

    public function test_student_matches_when_middle_name_is_omitted(): void
    {
        $student = $this->student('202600003', 'Pedro', 'Dela Cruz', 'Q');
        $this->graduateRecord($student, 'AR-MIDDLE');

        $result = $this->match([
            'student_id' => null, 'first_name' => 'Pedro', 'middle_name' => null, 'last_name' => 'Dela Cruz',
        ]);

        $this->assertSame('matched', $result['matchStatus']);
    }

    public function test_student_matching_normalizes_case_and_extra_whitespace(): void
    {
        $student = $this->student('202600004', 'Maria Anne', 'Santos', 'Q.');
        $this->graduateRecord($student, 'AR-NORMALIZED');

        $result = $this->match([
            'student_id' => null,
            'first_name' => '  maria   anne ',
            'middle_name' => 'q',
            'last_name' => ' SANTOS ',
        ]);

        $this->assertSame('matched', $result['matchStatus']);
    }

    public function test_incorrect_id_falls_back_to_name_matching_and_incorrect_name_is_missing(): void
    {
        $student = $this->student('202600005', 'Liza', 'Garcia');
        $this->graduateRecord($student, 'AR-WRONG-ID');

        $byWrongId = $this->match([
            'student_id' => '999999', 'first_name' => 'Liza', 'last_name' => 'Garcia',
        ]);
        $byWrongName = $this->match([
            'student_id' => null, 'first_name' => 'Unknown', 'last_name' => 'Person',
        ]);

        $this->assertSame('matched', $byWrongId['matchStatus']);
        $this->assertSame('missing', $byWrongName['matchStatus']);
    }

    public function test_missing_student_has_empty_summary(): void
    {
        $result = $this->match(['student_id' => 'missing']);

        $this->assertSame('missing', $result['matchStatus']);
        $this->assertNull($result['selectedStudent']);
        $this->assertCount(0, $result['records']);
        $this->assertSame([
            'totalCharges' => 0.0,
            'totalPayments' => 0.0,
            'outstandingBalance' => 0.0,
        ], $result['summary']);
    }

    public function test_multiple_matching_students_are_ambiguous(): void
    {
        $first = $this->student('202600006', 'Alex', 'Cruz', 'A');
        $second = $this->student('202600007', 'Alex', 'Cruz', 'B');
        $this->graduateRecord($first, 'AR-A');
        $this->graduateRecord($second, 'AR-B');

        $result = $this->match(['student_id' => null, 'first_name' => 'Alex', 'last_name' => 'Cruz']);

        $this->assertSame('ambiguous', $result['matchStatus']);
        $this->assertNull($result['selectedStudent']);
        $this->assertCount(2, $result['candidates']);
    }

    public function test_manual_selection_chooses_the_specified_candidate(): void
    {
        $first = $this->student('202600008', 'Alex', 'Cruz', 'A');
        $second = $this->student('202600009', 'Alex', 'Cruz', 'B');
        $this->graduateRecord($first, 'AR-MANUAL-A', 100);
        $this->graduateRecord($second, 'AR-MANUAL-B', 200);

        $result = $this->match(
            ['student_id' => null, 'first_name' => 'Alex', 'last_name' => 'Cruz'],
            'graduate:'.$second->id,
        );

        $this->assertSame('manual', $result['matchStatus']);
        $this->assertSame('graduate:'.$second->id, $result['selectedStudent']['key']);
        $this->assertSame('AR-MANUAL-B', $result['records'][0]['referenceNo']);
    }

    public function test_invalid_manual_selection_is_ignored_and_normal_matching_continues(): void
    {
        $student = $this->student('202600010', 'Valid', 'Candidate');
        $this->graduateRecord($student, 'AR-VALID');

        $result = $this->match(['student_id' => '202600010'], 'graduate:999999');

        $this->assertSame('matched', $result['matchStatus']);
        $this->assertSame('202600010', $result['selectedStudent']['studentId']);
    }

    public function test_graduate_and_law_candidates_are_separated(): void
    {
        $graduate = $this->student('SHARED-GRAD', 'Graduate', 'Student');
        $law = $this->student('SHARED-LAW', 'Law', 'Student');
        $this->graduateRecord($graduate, 'GRADUATE-ONLY');
        $this->lawRecord($law, 'LAW-ONLY');

        $graduateResult = $this->match(['student_id' => 'SHARED-GRAD']);
        $lawResult = $this->match(['enrolled_under' => 'School of Law', 'student_id' => 'SHARED-LAW']);

        $this->assertSame('GRADUATE-ONLY', $graduateResult['records'][0]['referenceNo']);
        $this->assertSame('LAW-ONLY', $lawResult['records'][0]['referenceNo']);
    }

    public function test_matching_respects_school_year_and_semester(): void
    {
        $student = $this->student('202600011', 'Term', 'Specific');
        $this->graduateRecord($student, 'AR-REQUESTED', 100, '2025-2026', 'Second Semester');
        $this->graduateRecord($student, 'AR-OTHER-SEMESTER', 200, '2025-2026', 'First Semester');
        $this->graduateRecord($student, 'AR-OTHER-YEAR', 300, '2024-2025', 'Second Semester');

        $result = $this->match([
            'student_id' => '202600011', 'sy_last_attended' => '2025-2026', 'semester' => 'Second Semester',
        ]);

        $this->assertSame(['AR-REQUESTED'], collect($result['records'])->pluck('referenceNo')->all());
        $this->assertSame('2025-2026', $result['schoolYear']);
        $this->assertSame('Second Semester', $result['semester']);
    }

    public function test_matching_returns_correct_records_and_summary(): void
    {
        $student = $this->student('202600012', 'Records', 'Student');
        $this->graduateRecord($student, 'AR-RECORD', 1500, '2025-2026', 'First Semester', 'ar');
        $this->graduateRecord($student, 'PAY-RECORD', 500, '2025-2026', 'First Semester', 'payment');

        $result = $this->match(['student_id' => '202600012']);

        $this->assertCount(2, $result['records']);
        $this->assertSame(1500.0, $result['summary']['totalCharges']);
        $this->assertSame(500.0, $result['summary']['totalPayments']);
        $this->assertSame(1000.0, $result['summary']['outstandingBalance']);
    }

    public function test_null_optional_names_are_handled(): void
    {
        $student = $this->student(null, 'NoMiddle', 'Name', null);
        $this->graduateRecord($student, 'AR-NULL-NAME');

        $result = $this->match([
            'student_id' => null, 'first_name' => 'NoMiddle', 'middle_name' => null, 'last_name' => 'Name',
        ]);

        $this->assertSame('matched', $result['matchStatus']);
    }

    public function test_duplicate_student_numbers_are_handled_safely_by_the_database_constraint(): void
    {
        $this->student('DUP-001', 'First', 'Duplicate');

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $this->student('DUP-001', 'Second', 'Duplicate');
    }

    /** @param array<string, mixed> $overrides */
    private function match(array $overrides = [], ?string $manual = null): array
    {
        $assessment = AssessmentForm::create(array_merge([
            'reference_number' => 'ASSESS-'.uniqid(),
            'email' => 'student@example.com',
            'contact_num' => '09123456789',
            'first_name' => 'Maria',
            'middle_name' => null,
            'last_name' => 'Reyes',
            'student_id' => null,
            'course_id' => $this->course('MATCH-COURSE')->id,
            'address' => 'Dumaguete',
            'enrolled_under' => 'Graduate',
            'sy_last_attended' => '2025-2026',
            'semester' => 'First Semester',
        ], $overrides));

        return app(LedgerMatchingService::class)->forAssessment($assessment, $manual);
    }

    private function student(?string $number, string $first, string $last, ?string $middle = null): Student
    {
        return Student::create([
            'student_number' => $number,
            'first_name' => $first,
            'middle_name' => $middle,
            'last_name' => $last,
        ]);
    }

    private function course(string $code): Course
    {
        return Course::firstOrCreate([
            'course_code' => $code,
        ], [
            'course_desc' => $code,
            'course_college' => 'Graduate School',
        ]);
    }

    private function term(string $year = '2025-2026', string $semester = 'First Semester'): AcademicTerm
    {
        return AcademicTerm::firstOrCreate([
            'school_year' => $year,
            'semester' => $semester,
        ]);
    }

    private function graduateRecord(
        Student $student,
        string $reference,
        float $amount = 100,
        string $year = '2025-2026',
        string $semester = 'First Semester',
        string $entryType = 'ar',
    ): GraduateLedger {
        $course = $this->course('MATCH-GRAD');

        return GraduateLedger::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'academic_term_id' => $this->term($year, $semester)->id,
            'entry_type' => $entryType,
            'transaction_date' => '2026-01-01',
            'reference_number' => $reference,
            'particulars' => 'Tuition',
            'amount' => $amount,
            'status' => 'posted',
        ]);
    }

    private function lawRecord(Student $student, string $reference): LawSchoolLedger
    {
        $course = Course::firstOrCreate(['course_code' => 'MATCH-LAW'], [
            'course_desc' => 'Juris Doctor', 'course_college' => 'School of Law',
        ]);

        return LawSchoolLedger::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'academic_term_id' => $this->term()->id,
            'entry_type' => 'ar',
            'transaction_date' => '2026-01-01',
            'reference_number' => $reference,
            'particulars' => 'Tuition',
            'amount' => 100,
            'status' => 'posted',
        ]);
    }
}
