<?php

namespace App\Services;

use App\Models\UACS;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Facades\Excel;

class UACSService
{
    /**
     * Import the six-column UACS accounting-code workbook.
     *
     * The workbook contains title/header rows before the data, so rows are
     * identified by a valid 12-digit NORSU object code instead of assuming a
     * fixed header row.
     */
    public function import(UploadedFile $file): int
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['csv', 'xlsx', 'xls'], true)) {
            throw new \InvalidArgumentException('Unsupported UACS file type.');
        }

        $rows = [];
        $collection = new class implements ToCollection {
            public Collection $rows;

            public function collection(Collection $collection): void
            {
                $this->rows = $collection;
            }
        };

        Excel::import($collection, $file);

        foreach ($collection->rows as $index => $row) {
            $rawValues = match (true) {
                $row instanceof Collection => $row->all(),
                is_array($row) => $row,
                method_exists($row, 'toArray') => $row->toArray(),
                default => [],
            };

            $values = array_map(
                static fn ($value): string => trim((string) $value),
                array_values($rawValues),
            );

            if ($this->isBlankRow($values) || $this->isHeaderRow($values)) {
                continue;
            }

            // WPS stores the NORSU object code in some rows as a formula such
            // as =C1427&B1427. Laravel Excel returns the formula text rather
            // than the calculated display value, so resolve this workbook's
            // concatenation formula from the current row before importing.
            $values[0] = $this->resolveObjectCodeFormula($values[0] ?? '', $values);

            // Keep the import tolerant: accounting workbooks can contain
            // placeholders such as '-', N/A, or blank code cells. Preserve
            // those values as text instead of rejecting the entire upload.
            $record = [
                'norsu_object_code' => $this->normalizeImportedCode($values[0] ?? null, 12),
                'norsu_sub_object_code' => $this->normalizeImportedCode($values[1] ?? null, 2),
                'uacs_object_code' => $this->normalizeImportedCode($values[2] ?? null, 10),
                'uacs_sub_object_code' => $this->normalizeImportedCode($values[3] ?? null, 2),
                'rca_code' => $this->normalizeImportedCode($values[4] ?? null, 8),
                'account_title' => $this->nullableValue($values[5] ?? null),
            ];

            $rows[] = $record;
        }

        if ($rows === []) {
            throw new \InvalidArgumentException('The uploaded file contains no valid UACS data rows.');
        }

        $count = 0;
        DB::transaction(function () use ($rows, &$count): void {
            foreach (array_chunk($rows, 500) as $chunk) {
                $now = now();
                UACS::insert(array_map(
                    static fn (array $row): array => $row + [
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    $chunk,
                ));
                $count += count($chunk);
            }
        });

        return $count;
    }

    /** @param array<int, string> $values */
    private function isBlankRow(array $values): bool
    {
        return $values === [] || count(array_filter($values, static fn (string $value): bool => $value !== '')) === 0;
    }

    /** @param array<int, string> $values */
    private function isHeaderRow(array $values): bool
    {
        return strcasecmp(trim($values[0] ?? ''), 'NORSU Object Code') === 0;
    }

    private function nullableValue(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }

    private function normalizeCode(?string $value, int $length): ?string
    {
        $value = $this->nullableValue($value);

        if ($value === null) {
            return null;
        }

        $value = str_replace(',', '', $value);
        if (preg_match('/^(\d+)(?:\.0+)?$/', $value, $matches) === 1) {
            $value = $matches[1];
        } elseif (is_numeric($value) && str_contains(strtolower($value), 'e')) {
            $value = sprintf('%.0f', (float) $value);
        }

        return ctype_digit($value)
            ? str_pad($value, $length, '0', STR_PAD_LEFT)
            : $value;
    }

    /** @param array<int, string> $values */
    private function resolveObjectCodeFormula(string $value, array $values): string
    {
        if (preg_match('/^=C(\d+)\&B\d+$/i', $value, $matches) !== 1) {
            return $value;
        }

        return ($values[2] ?? '').($values[1] ?? '');
    }

    private function normalizeImportedCode(?string $value, int $length): ?string
    {
        return $this->normalizeCode($value, $length);
    }
}
