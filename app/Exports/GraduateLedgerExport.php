<?php

namespace App\Exports;

use App\Models\GraduateLedger;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class GraduateLedgerExport implements FromQuery, ShouldAutoSize, WithCustomChunkSize, WithHeadings, WithMapping
{
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
            $row->remarks ?? '',
            $row->input_by ?? '',
        ];
    }
}
