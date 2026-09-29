<?php

namespace App\Exports;

use App\Models\GraduateLedger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class GraduateLedgerExport implements FromQuery, ShouldAutoSize, WithCustomChunkSize, WithHeadings, WithMapping
{
    /** @var array<string, float> */
    private array $termBalanceCache = [];

    /** @param Builder<GraduateLedger> $query */
    public function __construct(
        private readonly Builder $query,
    ) {}

    public function chunkSize(): int
    {
        return 500;
    }

    /** @return Builder<GraduateLedger> */
    public function query(): Builder
    {
        return $this->query->latest('id');
    }

    public function headings(): array
    {
        return [
            'FF',
            'COURSE',
            'SCHOOL YEAR',
            '',
            "SEMESTER/\nSUMMER",
            'UNITS',
            'TRANSACTION DATE',
            'Reference JEV  / O.R. NUMBER',
            'PARTICULARS',
            'TUITION per UNIT/ Reg. and Miscellaneous per semester',
            'AR/PAYMENT',
            'AMOUNT',
            'REMARKS',
            'INPUT BY:',
        ];
    }

    public function map($row): array
    {
        $studentName = $row->student->full_name ?? '';
        $courseCode = $row->course->code ?? '';
        $schoolYear = $row->academicTerm->school_year ?? '';
        $semShort = '';
        $semFull = $row->academicTerm->semester ?? '';
        $type = strtoupper($row->entry_type ?? 'AR');
        $remark = $this->resolveRemark($row);

        return [
            $studentName,
            $courseCode,
            $schoolYear,
            $semShort,
            $semFull,
            (float) ($row->units ?? 0),
            $row->transaction_date ? (string) $row->transaction_date : '',
            $row->reference_or_jev_number ?? '',
            $row->particulars ?? '',
            (float) ($row->tuition_per_unit_or_misc ?? 0),
            $type,
            (float) ($row->amount ?? 0),
            $remark,
            $row->inputByDisplay(),
        ];
    }

    private function resolveRemark(GraduateLedger $row): string
    {
        $studentId = $row->student_id;
        $termId = $row->academic_term_id;

        if ($studentId && $termId) {
            $key = "{$studentId}_{$termId}";
            if (! array_key_exists($key, $this->termBalanceCache)) {
                $this->termBalanceCache[$key] = (float) DB::table('graduate_ledgers')
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
}
