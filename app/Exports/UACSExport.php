<?php

namespace App\Exports;

use App\Models\UACS;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;

class UACSExport extends DefaultValueBinder implements FromQuery, ShouldAutoSize, WithColumnFormatting, WithCustomValueBinder, WithHeadings, WithMapping
{
    /** @return Builder<UACS> */
    public function query(): Builder
    {
        return UACS::query()
            ->orderBy('uacs_object_code', 'asc')
            ->orderBy('uacs_sub_object_code', 'asc');
    }

    public function headings(): array
    {
        return [
            'NORSU Object Code',
            'NORSU Sub-Object Code',
            'UACS Object Code',
            'UACS Sub-Object Code',
            'RCA Code',
            'Account Title',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function bindValue(Cell $cell, $value): bool
    {
        if (in_array($cell->getColumn(), ['A', 'B', 'C', 'D', 'E'], true)) {
            $cell->setValueExplicit((string) ($value ?? ''), DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    /** @param UACS $row */
    public function map($row): array
    {
        return [
            $row->norsu_object_code ?? '',
            $row->norsu_sub_object_code ?? '',
            $row->uacs_object_code ?? '',
            $row->uacs_sub_object_code ?? '',
            $row->rca_code ?? '',
            $row->account_title ?? '',
        ];
    }
}
