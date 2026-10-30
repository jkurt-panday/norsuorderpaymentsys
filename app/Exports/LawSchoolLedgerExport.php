<?php

namespace App\Exports;

use App\Models\LawSchoolLedger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LawSchoolLedgerExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    /** @param Builder<LawSchoolLedger> $query */
    public function __construct(
        private readonly Builder $query
    ) {}

    /** @var array<string, float> */
    private array $termBalanceCache = [];

    /** @return Builder<LawSchoolLedger> */
    public function query(): Builder
    {
        return $this->query->latest('id');
    }

    public function headings(): array
    {
        return [
            'Student ID Number',
            'Student Name',
            'Last Name',
            'First Name',
            'Middle Initial',
            'Course',
            'School Year',
            'Semester',
            'Units',
            'Transaction Date',
            'Reference/JEV Number',
            'Particulars',
            'Tuition/Unit or Fee/Semester',
            'AR or Payment',
            'Amount',
            'Status',
            'Remarks',
            'Input By',
        ];
    }

    public function map($row): array
    {
        $studentNumber = $row->lawStudent?->student_number ?? '';
        $lastName = $row->lawStudent?->last_name ?? '';
        $firstName = $row->lawStudent?->first_name ?? '';
        $middleInitial = $row->lawStudent?->middle_name ? substr($row->lawStudent->middle_name, 0, 1) : '';
        $remark = $this->resolveRemark($row);

        return [
            $studentNumber,
            trim("$lastName, $firstName ".($middleInitial ? "$middleInitial" : '')),
            $lastName,
            $firstName,
            $middleInitial,
            $row->lawCourse?->course_code ?? '',
            $row->lawAcademicTerm?->school_year ?? '',
            $row->lawAcademicTerm?->semester ?? '',
            (float) ($row->units ?? 0),
            $row->transaction_date?->format('Y-m-d') ?? '',
            $row->reference_number ?? '',
            $row->particulars ?? '',
            (float) ($row->rate ?? 0),
            $this->formatEntryType($row->entry_type),
            (float) ($row->amount ?? 0),
            $row->status ?? '',
            $remark,
            $row->inputByDisplay() ?? '',
        ];
    }

    private function resolveRemark(LawSchoolLedger $row): string
    {
        $studentId = $row->student_id;
        $termId = $row->academic_term_id;

        if ($studentId && $termId) {
            $key = "{$studentId}_{$termId}";
            if (! array_key_exists($key, $this->termBalanceCache)) {
                $this->termBalanceCache[$key] = (float) DB::table('law_school_ledgers')
                    ->where('student_id', $studentId)
                    ->where('academic_term_id', $termId)
                    ->sum(DB::raw("CASE WHEN LOWER(TRIM(entry_type)) = 'ar' THEN amount WHEN LOWER(TRIM(entry_type)) IN ('payment', 'adjustment') THEN -amount ELSE 0 END"));
            }

            return $this->termBalanceCache[$key] > 0 ? 'Outstanding' : 'Settled';
        }

        if (strtolower(trim((string) $row->entry_type)) === 'ar' && (float) $row->amount > 0) {
            return 'Outstanding';
        }

        return 'Settled';
    }

    private function formatEntryType(?string $entryType): string
    {
        return match ($entryType) {
            'ar' => 'AR',
            'adjustment' => 'Adjustment',
            'payment' => 'Payment',
            default => 'AR',
        };
    }
}
