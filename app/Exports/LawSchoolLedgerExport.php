<?php

namespace App\Exports;

use App\Models\LawSchoolLedger;
use Illuminate\Database\Eloquent\Builder;
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

    /** @return Builder<LawSchoolLedger> */
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
        return [
            trim("$row->last_name, $row->first_name ".($row->middle_initial ? "$row->middle_initial" : '')),
            $row->last_name,
            $row->first_name,
            $row->middle_initial,
            $row->course,
            $row->school_year,
            $row->semester_or_summer,
            (float) $row->units,
            $row->transaction_date?->format('Y-m-d'),
            $row->reference_jev_or_number,
            $row->particulars,
            (float) $row->tuition_per_unit_or_fee_per_semester,
            $row->ar_or_payment,
            (float) $row->amount,
            $row->status,
            $row->remarks,
            $row->input_by,
        ];
    }
}
