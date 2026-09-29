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
        // Use accessor methods and relationships to get the correct data
        $lastName = $row->lawStudent?->last_name ?? '';
        $firstName = $row->lawStudent?->first_name ?? '';
        $middleInitial = $row->lawStudent?->middle_name ? substr($row->lawStudent->middle_name, 0, 1) : '';
        
        return [
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
            $row->remarks ?? '',
            $row->inputByDisplay() ?? '',
        ];
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
