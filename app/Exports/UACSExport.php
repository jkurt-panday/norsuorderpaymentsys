<?php

namespace App\Exports;

use App\Models\UACS;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UACSExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    /** @return Builder<UACS> */
    public function query(): Builder
    {
        return UACS::query()->orderBy('id');
    }

    public function headings(): array
    {
        return [
            'Classification',
            'Sub-Class',
            'Group',
            'Object Code',
            'Sub-Object Code',
            'UACS',
            'Status',
        ];
    }

    /** @param UACS $row */
    public function map($row): array
    {
        return [
            $row->classification ?? '',
            $row->sub_class ?? '',
            $row->group ?? '',
            $row->object_code ?? '',
            $row->sub_object_code ?? '',
            $row->uacs ?? '',
            $row->status ?? 'Inactive',
        ];
    }
}
