<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\AssessmentForm;
use App\Models\GraduateLedger;
use App\Models\LawSchoolLedger;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @phpstan-type Candidate array{
 *     key: string,
 *     name: string,
 *     studentId: string|null,
 *     firstName: string|null,
 *     middleName: string|null,
 *     lastName: string|null,
 *     pdfQueryKey: 'student'|'student_id',
 *     pdfQueryValue: string,
 *     modelId: int|null
 * }
 * @phpstan-type PublicCandidate array{
 *     key: string,
 *     name: string,
 *     studentId: string|null,
 *     pdfQueryKey: 'student'|'student_id',
 *     pdfQueryValue: string
 * }
 * @phpstan-type Summary array{
 *     totalCharges: float,
 *     totalPayments: float,
 *     outstandingBalance: float
 * }
 * @phpstan-type Match array{
 *     status: 'manual'|'matched'|'ambiguous'|'missing',
 *     selected: Candidate|null,
 *     candidates: list<Candidate>
 * }
 */
class LedgerMatchingService
{
    /** @return array<string, mixed> */
    public function forAssessment(AssessmentForm $assessment, ?string $manualSelection = null): array
    {
        return match ($assessment->enrolled_under) {
            'Graduate' => $this->graduateStatement($assessment, $manualSelection),
            'School of Law' => $this->lawStatement($assessment, $manualSelection),
            default => $this->unsupportedStatement($assessment),
        };
    }

    /** @return array<string, mixed> */
    private function graduateStatement(AssessmentForm $assessment, ?string $manualSelection): array
    {
        $semester = AcademicTerm::normalizeSemester($assessment->semester);

        $students = Student::query()
            ->whereHas('graduateLedgers', fn ($query) => $query->whereHas(
                'academicTerm',
                fn ($termQuery) => $termQuery
                    ->where('school_year', $assessment->sy_last_attended)
                    ->where('semester', $semester),
            ))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'student_number', 'last_name', 'first_name', 'middle_name'])
            ->map(fn (Student $student): array => $this->graduateCandidate($student));

        $match = $this->resolveCandidate($students, $assessment, $manualSelection);
        $selected = $match['selected'];
        $records = $selected
            ? GraduateLedger::query()
                ->with(['student', 'course', 'academicTerm'])
                ->where('student_id', $selected['modelId'])
                ->whereHas('academicTerm', fn ($query) => $query
                    ->where('school_year', $assessment->sy_last_attended)
                    ->where('semester', $semester))
                ->orderBy('transaction_date')
                ->orderBy('id')
                ->get()
            : collect();

        return $this->statementPayload(
            source: 'graduate',
            assessment: $assessment,
            semester: $semester,
            match: $match,
            records: $records->map(fn (GraduateLedger $record) => $this->transformGraduateRecord($record)),
            summary: $this->graduateSummary($records),
        );
    }

    /** @return array<string, mixed> */
    private function lawStatement(AssessmentForm $assessment, ?string $manualSelection): array
    {
        $semester = AcademicTerm::normalizeSemester($assessment->semester);
        $termRecords = LawSchoolLedger::query()
            ->where('school_year', $assessment->sy_last_attended)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get()
            ->filter(fn (LawSchoolLedger $record) => AcademicTerm::normalizeSemester(
                (string) $record->semester_or_summer,
            ) === $semester)
            ->values();

        $groupedRecords = $termRecords->groupBy(fn (LawSchoolLedger $record) => $this->lawCandidateKey($record));
        $students = $groupedRecords
            ->map(fn (Collection $records, string $key): array => $this->lawCandidate($records, $key))
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $match = $this->resolveCandidate($students, $assessment, $manualSelection);
        $selected = $match['selected'];
        $records = $selected ? ($groupedRecords->get($selected['key']) ?? collect()) : collect();

        return $this->statementPayload(
            source: 'law',
            assessment: $assessment,
            semester: $semester,
            match: $match,
            records: $records->map(fn (LawSchoolLedger $record) => $this->transformLawRecord($record))->values(),
            summary: $this->lawSummary($records),
        );
    }

    /** @return Candidate */
    private function graduateCandidate(Student $student): array
    {
        return [
            'key' => 'graduate:'.$student->id,
            'name' => $student->full_name,
            'studentId' => $student->student_number,
            'firstName' => $student->first_name,
            'middleName' => $student->middle_name,
            'lastName' => $student->last_name,
            'pdfQueryKey' => 'student_id',
            'pdfQueryValue' => (string) $student->id,
            'modelId' => $student->id,
        ];
    }

    /**
     * @param  Collection<int, LawSchoolLedger>  $records
     * @return Candidate
     */
    private function lawCandidate(Collection $records, string $key): array
    {
        $student = $records->firstOrFail();
        $name = $this->lawStudentName($student);

        return [
            'key' => $key,
            'name' => $name,
            'studentId' => $student->student_id,
            'firstName' => $student->first_name,
            'middleName' => $student->middle_initial,
            'lastName' => $student->last_name,
            'pdfQueryKey' => filled($student->student_id) ? 'student_id' : 'student',
            'pdfQueryValue' => filled($student->student_id) ? (string) $student->student_id : $name,
            'modelId' => null,
        ];
    }

    /**
     * @param  Collection<int, covariant Candidate>  $candidates
     * @return Match
     */
    private function resolveCandidate(Collection $candidates, AssessmentForm $assessment, ?string $manualSelection): array
    {
        if (filled($manualSelection)) {
            $manualCandidate = $candidates->firstWhere('key', $manualSelection);

            if ($manualCandidate) {
                return ['status' => 'manual', 'selected' => $manualCandidate, 'candidates' => []];
            }
        }

        if (filled($assessment->student_id)) {
            $idMatches = $candidates->filter(fn (array $candidate) => $this->normalizeIdentifier(
                $candidate['studentId'] ?? null,
            ) === $this->normalizeIdentifier($assessment->student_id));

            if ($idMatches->count() === 1) {
                return ['status' => 'matched', 'selected' => $idMatches->first(), 'candidates' => []];
            }

            if ($idMatches->count() > 1) {
                return ['status' => 'ambiguous', 'selected' => null, 'candidates' => array_values($idMatches->all())];
            }
        }

        $firstName = $this->normalizeName($assessment->first_name);
        $middleName = $this->normalizeName($assessment->middle_name);
        $lastName = $this->normalizeName($assessment->last_name);
        $nameMatches = $candidates->filter(function (array $candidate) use ($firstName, $middleName, $lastName) {
            if ($this->normalizeName($candidate['firstName'] ?? null) !== $firstName
                || $this->normalizeName($candidate['lastName'] ?? null) !== $lastName) {
                return false;
            }

            return $middleName === ''
                || $this->normalizeName($candidate['middleName'] ?? null) === $middleName;
        });

        if ($nameMatches->count() === 1) {
            return ['status' => 'matched', 'selected' => $nameMatches->first(), 'candidates' => []];
        }

        return [
            'status' => $nameMatches->isEmpty() ? 'missing' : 'ambiguous',
            'selected' => null,
            'candidates' => array_values(($nameMatches->isEmpty() ? $candidates : $nameMatches)->all()),
        ];
    }

    /**
     * @param  Match  $match
     * @param  Collection<int, array<string, mixed>>  $records
     * @param  Summary  $summary
     * @return array<string, mixed>
     */
    private function statementPayload(
        string $source,
        AssessmentForm $assessment,
        string $semester,
        array $match,
        Collection $records,
        array $summary,
    ): array {
        $selected = $match['selected'];

        return [
            'source' => $source,
            'matchStatus' => $match['status'],
            'selectedStudent' => $selected === null ? null : $this->publicCandidate($selected),
            'candidates' => array_map($this->publicCandidate(...), $match['candidates']),
            'records' => $records->values(),
            'summary' => $summary,
            'schoolYear' => $assessment->sy_last_attended,
            'semester' => $semester,
        ];
    }

    /** @return array<string, mixed> */
    private function unsupportedStatement(AssessmentForm $assessment): array
    {
        return [
            'source' => 'unsupported',
            'matchStatus' => 'unsupported',
            'selectedStudent' => null,
            'candidates' => [],
            'records' => [],
            'summary' => $this->emptySummary(),
            'schoolYear' => $assessment->sy_last_attended,
            'semester' => AcademicTerm::normalizeSemester((string) $assessment->semester),
        ];
    }

    /**
     * @param  Candidate  $candidate
     * @return PublicCandidate
     */
    private function publicCandidate(array $candidate): array
    {
        return [
            'key' => $candidate['key'],
            'name' => $candidate['name'],
            'studentId' => $candidate['studentId'],
            'pdfQueryKey' => $candidate['pdfQueryKey'],
            'pdfQueryValue' => $candidate['pdfQueryValue'],
        ];
    }

    /** @return array<string, mixed> */
    private function transformGraduateRecord(GraduateLedger $record): array
    {
        return [
            'id' => $record->id,
            'name' => $record->student->full_name ?? '',
            'course' => $record->course?->code,
            'units' => $record->units,
            'schoolYear' => $record->academicTerm?->school_year,
            'semester' => $record->academicTerm?->semester,
            'transactionDate' => $record->transaction_date?->format('Y-m-d'),
            'referenceNo' => $record->reference_or_jev_number,
            'particulars' => $record->particulars,
            'type' => match ($record->entry_type) {
                'ar' => 'AR',
                'payment' => 'Payment',
                'adjustment' => 'Adjustment',
                default => Str::headline((string) $record->entry_type),
            },
            'amount' => abs((float) $record->amount),
        ];
    }

    /** @return array<string, mixed> */
    private function transformLawRecord(LawSchoolLedger $record): array
    {
        return [
            'id' => $record->id,
            'name' => $this->lawStudentName($record),
            'course' => $record->course,
            'schoolYear' => $record->school_year,
            'semester' => AcademicTerm::normalizeSemester((string) $record->semester_or_summer),
            'transactionDate' => $record->transaction_date?->format('Y-m-d'),
            'referenceNo' => $record->reference_jev_or_number,
            'particulars' => $record->particulars,
            'type' => Str::upper((string) $record->ar_or_payment) === 'AR' ? 'AR' : Str::headline((string) $record->ar_or_payment),
            'amount' => abs((float) $record->amount),
        ];
    }

    /**
     * @param  Collection<int, GraduateLedger>  $records
     * @return Summary
     */
    private function graduateSummary(Collection $records): array
    {
        $charges = (float) $records->where('entry_type', 'ar')->sum(fn (GraduateLedger $record) => abs((float) $record->amount));
        $payments = (float) $records->reject(fn (GraduateLedger $record) => $record->entry_type === 'ar')
            ->sum(fn (GraduateLedger $record) => abs((float) $record->amount));

        return $this->summary($charges, $payments);
    }

    /**
     * @param  Collection<int, LawSchoolLedger>  $records
     * @return Summary
     */
    private function lawSummary(Collection $records): array
    {
        $charges = 0.0;
        $payments = 0.0;

        foreach ($records as $record) {
            if (in_array(Str::upper(trim((string) $record->ar_or_payment)), ['AR', 'ASSESSMENT'], true)) {
                $charges += abs((float) $record->amount);
            } else {
                $payments += abs((float) $record->amount);
            }
        }

        return $this->summary($charges, $payments);
    }

    /** @return Summary */
    private function summary(float $charges, float $payments): array
    {
        return [
            'totalCharges' => $charges,
            'totalPayments' => $payments,
            'outstandingBalance' => $charges - $payments,
        ];
    }

    /** @return Summary */
    private function emptySummary(): array
    {
        return $this->summary(0, 0);
    }

    private function lawCandidateKey(LawSchoolLedger $record): string
    {
        if (filled($record->student_id)) {
            return 'law-id:'.$this->normalizeIdentifier($record->student_id);
        }

        $identity = implode('|', [
            $this->normalizeName($record->last_name),
            $this->normalizeName($record->first_name),
            $this->normalizeName($record->middle_initial),
        ]);

        return 'law-name:'.hash('sha256', $identity);
    }

    private function lawStudentName(LawSchoolLedger $record): string
    {
        $givenNames = collect([$record->first_name, $record->middle_initial])
            ->filter(fn ($value) => filled($value))
            ->join(' ');

        return trim($record->last_name.', '.$givenNames, ', ');
    }

    private function normalizeIdentifier(mixed $value): string
    {
        return Str::of((string) $value)->squish()->lower()->toString();
    }

    private function normalizeName(mixed $value): string
    {
        return Str::of((string) $value)->squish()->lower()->replace('.', '')->toString();
    }
}
