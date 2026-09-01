<?php

namespace App\Services;

use App\Models\AssessmentForm;
use App\Models\GraduateLedger;
use App\Models\LawSchoolLedger;
use App\Models\AcademicTerm;
use Illuminate\Support\Collection;

class LedgerMatchingService
{
    public function forAssessment(AssessmentForm $assessment): Collection
    {
        return match ($assessment->enrolled_under) {
            'Graduate' => $this->graduateRecords($assessment),
            'School of Law' => $this->lawRecords($assessment),
            default => collect(), // Undergraduate — no ledger source yet
        };
    }

    protected function graduateRecords(AssessmentForm $assessment): Collection
    {
        $query = GraduateLedger::query()
            ->whereHas('academic_term_id', function ($q) use ($assessment) {
                $q->where('semester', $assessment->semester)
                    ->where('school_year', $assessment->sy_last_attended);
            })
            ->with(['course', 'academic_term_id']);

        $this->matchStudent($query, $assessment, viaRelation: 'student');

        return $query->orderBy('transaction_date')->get()->map(fn ($row) => [
                'date' => $row->transaction_date?->format('Y-m-d'),
                'particulars' => $row->particulars,
                'course' => $row->course?->code,
                'units' => $row->units,
                'amount' => $row->amount,
                'reference' => $row->reference_or_jev_number,
            ]);
    }

    protected function lawRecords(AssessmentForm $assessment): Collection
    {
        $query = LawSchoolLedger::query()
            ->where('school_year', $assessment->sy_last_attended);

        $this->matchStudent($query, $assessment);

        return $query->get()
            ->filter(fn ($row) => $this->normalizeLawSemester($row->semester_or_summer) === $assessment->semester)
            ->sortBy('transaction_date')
            ->values();
    }

    protected function matchStudent($query, AssessmentForm $assessment, ?string $viaRelation = null): void
    {
        $lastName = trim($assessment->last_name);
        $firstName = trim($assessment->first_name);

        if ($assessment->student_id && $viaRelation) {
            $query->whereHas($viaRelation, fn ($q) =>
                $q->where('student_number', (string) $assessment->student_id)
            );
        } elseif ($assessment->student_id) {
            $query->where('student_id', (string) $assessment->student_id);
        } elseif ($viaRelation) {
            $query->whereHas($viaRelation, function ($q) use ($lastName, $firstName) {
                $q->whereRaw('UPPER(last_name) = UPPER(?)', [$lastName])
                    ->whereRaw('UPPER(first_name) = UPPER(?)', [$firstName]);
            });
        } else {
            $query->whereRaw('UPPER(last_name) = UPPER(?)', [$lastName])
                ->whereRaw('UPPER(first_name) = UPPER(?)', [$firstName]);
        }
    }

    protected function normalizeLawSemester(?string $raw): string
    {
        $lower = strtolower(trim((string) $raw));

        return match (true) {
            str_contains($lower, '2nd'), str_contains($lower, 'second') => 'Second Semester',
            str_contains($lower, 'summer') => 'Summer',
            default => 'First Semester',
        };
    }
}