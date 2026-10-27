<?php

namespace App\Services;

use App\Models\UACS;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Collection;

class UACSService
{
    /**
     * Import UACS records from an uploaded XLSX, XLS, or CSV file.
     */
    public function import(UploadedFile $file): int
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['csv', 'xlsx', 'xls'], true)) {
            throw new \InvalidArgumentException('Unsupported UACS file type.');
        }

        $rows = [];

        if ($extension === 'csv') {
            $path = $file->getRealPath();
            if ($path === false) {
                throw new \RuntimeException('Could not read the uploaded CSV file.');
            }

            $handle = fopen($path, 'r');
            if ($handle === false) {
                throw new \RuntimeException('Could not open the uploaded CSV file.');
            }

            fgetcsv($handle); // skip header
            while (($data = fgetcsv($handle)) !== false) {
                if (count(array_filter($data)) > 0) {
                    $rows[] = [
                        'classification' => trim($data[0] ?? '') !== '' ? trim($data[0]) : null,
                        'sub_class' => trim($data[1] ?? '') !== '' ? trim($data[1]) : null,
                        'group' => trim($data[2] ?? '') !== '' ? trim($data[2]) : null,
                        'object_code' => trim($data[3] ?? '') !== '' ? trim($data[3]) : null,
                        'sub_object_code' => trim($data[4] ?? '') !== '' ? trim($data[4]) : null,
                        'uacs' => trim($data[5] ?? '') !== '' ? trim($data[5]) : null,
                        'status' => $this->normalizeStatus($data[6] ?? ''),
                    ];
                }
            }
            fclose($handle);
        } else {
            // Excel import via Maatwebsite Excel
            $collection = new class implements ToCollection {
                public Collection $rows;
                public function collection(Collection $collection): void
                {
                    $this->rows = $collection;
                }
            };

            Excel::import($collection, $file);

            $isFirst = true;
            foreach ($collection->rows as $row) {
                if ($isFirst) {
                    $isFirst = false; // skip header row
                    continue;
                }

                $classification = trim((string) ($row[0] ?? ''));
                $subClass = trim((string) ($row[1] ?? ''));
                $group = trim((string) ($row[2] ?? ''));
                $objectCode = trim((string) ($row[3] ?? ''));
                $subObjectCode = trim((string) ($row[4] ?? ''));
                $uacsCode = trim((string) ($row[5] ?? ''));
                $statusVal = trim((string) ($row[6] ?? ''));

                if ($classification === '' && $subClass === '' && $group === '' && $objectCode === '' && $subObjectCode === '' && $uacsCode === '') {
                    continue;
                }

                $rows[] = [
                    'classification' => $classification !== '' ? $classification : null,
                    'sub_class' => $subClass !== '' ? $subClass : null,
                    'group' => $group !== '' ? $group : null,
                    'object_code' => $objectCode !== '' ? $objectCode : null,
                    'sub_object_code' => $subObjectCode !== '' ? $subObjectCode : null,
                    'uacs' => $uacsCode !== '' ? $uacsCode : null,
                    'status' => $this->normalizeStatus($statusVal),
                ];
            }
        }

        $count = 0;
        if (!empty($rows)) {
            DB::transaction(function () use ($rows, &$count) {
                foreach (array_chunk($rows, 500) as $chunk) {
                    $now = now();
                    $insertData = array_map(function ($row) use ($now) {
                        return array_merge($row, [
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }, $chunk);

                    UACS::insert($insertData);
                    $count += count($insertData);
                }
            });
        }

        return $count;
    }

    private function normalizeStatus(?string $status): string
    {
        $clean = trim((string) $status);

        if ($clean === '' || $clean === '-' || strcasecmp($clean, 'inactive') === 0) {
            return 'Inactive';
        }

        return 'Active';
    }
}
