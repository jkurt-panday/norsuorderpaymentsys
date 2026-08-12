<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class GraduateLedgerExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithCustomChunkSize
{
    public function __construct(
        private readonly Builder $query
    ) {
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function query(): Builder
    {
        return $this->query->latest('id');
    }

    public function headings(): array
    {
        return [
            'Student Name',
            'Last Name',
            'First Name',
            'Middle Name',
            'Course',
            'School Year',
            'Semester',
            'Units',
            'Transaction Date',
            'Reference/JEV Number',
            'Particulars',
            'Tuition/Unit or Fee',
            'Type (AR/Payment)',
            'Amount',
            'Remarks',
            'Input By',
        ];
    }

    public function map($row): array
    {
        $student    = $row->student;
        $courseCode = $row->course?->code ?? '';
        $schoolYear = $row->academicTerm?->school_year ?? '';
        $semester   = $row->academicTerm?->semester_short ?? ($row->academicTerm?->semester ?? '');
        $type       = strtoupper($row->entry_type ?? 'AR');

        return [
            $student?->full_name ?? '',
            $student?->last_name ?? '',
            $student?->first_name ?? '',
            $student?->middle_name ?? '',
            $courseCode,
            $schoolYear,
            $semester,
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
