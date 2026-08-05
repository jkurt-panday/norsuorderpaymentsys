<?php

namespace App\Http\Controllers;

use App\Models\LawSchoolLedger;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

class LawSchoolLedgerController extends Controller
{
    /**
     * Display the Law School Ledger overview index page.
     */
    public function index(Request $request): Response
    {
        $schoolYear = $request->input('school_year');
        $semester = $request->input('semester_or_summer');
        $course = $request->input('course');
        $status = $request->input('status');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = LawSchoolLedger::query()
            ->when($request->input('search'), function ($query, $search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('middle_initial', 'like', "%{$search}%")
                    ->orWhere('reference_jev_or_number', 'like', "%{$search}%")
                    ->orWhere('particulars', 'like', "%{$search}%");
            })
            ->when($schoolYear, function ($query, $schoolYear) {
                $query->where('school_year', $schoolYear);
            })
            ->when($semester, function ($query, $semester) {
                $query->where('semester_or_summer', $semester);
            })
            ->when($course, function ($query, $course) {
                $query->where('course', $course);
            })
            ->when($status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($dateFrom, function ($query, $dateFrom) {
                $query->whereDate('transaction_date', '>=', $dateFrom);
            })
            ->when($dateTo, function ($query, $dateTo) {
                $query->whereDate('transaction_date', '<=', $dateTo);
            });

        // 1. Calculate overall metrics using a cloned query BEFORE pagination
        $totalStudents = DB::query()
            ->fromSub(
                (clone $query)
                    ->whereNotNull('last_name')
                    ->where('last_name', '!=', '')
                    ->select(['last_name', 'first_name', 'middle_initial'])
                    ->distinct(),
                'students'
            )
            ->count();

        $totalUnits = (float) (clone $query)->sum('units');

        $statsQuery = clone $query;

        $totalCharges = (float) (clone $statsQuery)
            ->whereIn(DB::raw('UPPER(TRIM(ar_or_payment))'), ['AR', 'ASSESSMENT'])
            ->sum('amount');

        $totalPayments = (float) (clone $statsQuery)
            ->whereNotNull('ar_or_payment')
            ->where('ar_or_payment', '!=', '')
            ->whereNotIn(DB::raw('UPPER(TRIM(ar_or_payment))'), ['AR', 'ASSESSMENT'])
            ->sum(DB::raw('ABS(amount)'));

        $outstandingBalance = max(0, $totalCharges - $totalPayments);

        // 2. Fetch paginated records
        $records = $query
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        // Transform each row into the shape Index.tsx expects
        $records = $records->through(fn ($r) => $this->transformRecord($r));

        return Inertia::render('law-ledger/Index', [
            'records' => $records,
            'filters' => $request->only([
                'search', 'school_year', 'semester_or_summer', 'course', 'status', 'date_from', 'date_to'
            ]),
            'stats' => [
                'totalStudents' => $totalStudents,
                'totalUnits' => $totalUnits,
                'totalCharges' => $totalCharges,
                'totalPayments' => $totalPayments,
                'outstandingBalance' => $outstandingBalance,
            ],
            'filterOptions' => $this->getFilterOptions(),
        ]);
    }

    /**
     * Renders the form for creating a new law ledger transaction.
     */
    public function create(): Response
    {
        return Inertia::render('law-ledger/AddTransaction', [
            'filterOptions' => $this->getFilterOptions(),
        ]);
    }

    /**
     * Stores a new law ledger transaction.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'last_name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_initial' => ['nullable', 'string', 'max:10'],
            'course' => ['nullable', 'string', 'max:255'],
            'school_year' => ['nullable', 'string', 'max:20'],
            'semester_or_summer' => ['nullable', 'string', 'max:50'],
            'units' => ['nullable', 'numeric'],
            'transaction_date' => ['nullable', 'date'],
            'reference_jev_or_number' => ['nullable', 'string', 'max:255'],
            'particulars' => ['nullable', 'string'],
            'tuition_per_unit_or_fee_per_semester' => ['nullable', 'numeric'],
            'ar_or_payment' => ['nullable', 'string', 'max:50'],
            'amount' => ['nullable', 'numeric'],
            'status' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
            'input_by' => ['nullable', 'string', 'max:255'],
        ]);

        LawSchoolLedger::create($data);

        return redirect()->route('law-ledger.index');
    }

    /**
     * Imports a spreadsheet into the law school ledger.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:51200', 'mimes:xlsx,csv,xls'],
        ]);

        // Large law ledgers (20k+ rows + pivot sheets) need extra headroom.
        $previousMemoryLimit = ini_get('memory_limit');
        $previousTimeLimit = (int) ini_get('max_execution_time');
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        try {
            $uploadedFile = $request->file('file');
            $extension = strtolower($uploadedFile->getClientOriginalExtension() ?: $uploadedFile->extension() ?: '');

            if (in_array($extension, ['xlsx', 'xls'], true)) {
                $result = $this->importFromSpreadsheet($uploadedFile->getRealPath());
            } else {
                $result = $this->importFromCsvCollection($uploadedFile);
            }

            if (! empty($result['error'])) {
                return redirect()->route('law-ledger.index')->with('error', $result['error']);
            }

            $importedCount = $result['imported'];
            $skippedCount = $result['skipped'];
            $failedRows = $result['failed'];

            if ($importedCount === 0 && empty($failedRows)) {
                return redirect()->route('law-ledger.index')->with('error', 'No valid data rows found in the uploaded file.');
            }

            if (! empty($failedRows)) {
                $sampleFailures = array_slice($failedRows, 0, 10);
                $message = "Import finished: {$importedCount} imported";
                if ($skippedCount > 0) {
                    $message .= ", {$skippedCount} skipped";
                }
                $message .= ', '.count($failedRows).' failed. Sample: '.json_encode($sampleFailures);

                return redirect()->route('law-ledger.index')->with('warning', $message);
            }

            $message = "Import completed successfully. Imported {$importedCount} rows.";
            if ($skippedCount > 0) {
                $message .= " Skipped {$skippedCount} empty rows.";
            }

            return redirect()->route('law-ledger.index')->with('success', $message);
        } catch (\Throwable $e) {
            return redirect()->route('law-ledger.index')->with('error', 'Failed to import file: '.$e->getMessage());
        } finally {
            if ($previousMemoryLimit !== false) {
                ini_set('memory_limit', $previousMemoryLimit);
            }
            if ($previousTimeLimit > 0) {
                set_time_limit($previousTimeLimit);
            }
        }
    }

    /**
     * Stream-friendly import for large .xlsx/.xls law ledgers.
     *
     * @return array{imported:int,skipped:int,failed:array<int,array{row:int,error:string}>,error?:string}
     */
    private function importFromSpreadsheet(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);

        if (method_exists($reader, 'setLoadAllSheets')) {
            // Prefer the main ledger sheet; avoid pivot/extra sheets.
            $sheetNames = method_exists($reader, 'listWorksheetNames')
                ? $reader->listWorksheetNames($path)
                : [];

            $preferred = null;
            foreach ($sheetNames as $name) {
                if (stripos($name, 'LAW') !== false || stripos($name, 'LEDGER') !== false) {
                    $preferred = $name;
                    break;
                }
            }

            if ($preferred !== null && method_exists($reader, 'setLoadSheetsOnly')) {
                $reader->setLoadSheetsOnly([$preferred]);
            } elseif (! empty($sheetNames) && method_exists($reader, 'setLoadSheetsOnly')) {
                $reader->setLoadSheetsOnly([$sheetNames[0]]);
            }
        }

        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = (int) $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        // Cap to expected ledger columns (A–N) to avoid sparse far-right cells.
        $highestColumnIndex = min($highestColumnIndex, 20);

        if ($highestRow < 2) {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            return ['imported' => 0, 'skipped' => 0, 'failed' => [], 'error' => 'No rows found in the uploaded file.'];
        }

        $headerRow = [];
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $columnLetter = Coordinate::stringFromColumnIndex($col);
            $value = $sheet->getCell($columnLetter . '1')->getValue();
            $headerRow[] = is_string($value)
                ? Str::slug(str_replace(["\n", "\r"], ' ', $value), '_')
                : Str::slug((string) $value, '_');
        }

        $headerCheck = $this->validateImportHeaders($headerRow);
        if ($headerCheck !== null) {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            return ['imported' => 0, 'skipped' => 0, 'failed' => [], 'error' => $headerCheck];
        }

        $importedCount = 0;
        $skippedCount = 0;
        $failedRows = [];
        $batch = [];
        $now = now();
        $batchSize = 250;

        DB::beginTransaction();

        try {
            for ($row = 2; $row <= $highestRow; $row++) {
                $rowValues = [];
                $hasAnyValue = false;

                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $columnLetter = Coordinate::stringFromColumnIndex($col);
                    $cell = $sheet->getCell($columnLetter . $row);
                    $value = $cell->getValue();

                    // Prefer calculated value for formula cells (amount, remarks).
                    if (is_string($value) && str_starts_with(ltrim($value), '=')) {
                        try {
                            $calculated = $cell->getCalculatedValue();
                            if ($calculated !== null && $calculated !== '') {
                                $value = $calculated;
                            }
                        } catch (\Throwable) {
                            // Keep formula string; normalizeAmount can recompute units * tuition.
                        }
                    }

                    if ($value !== null && $value !== '') {
                        $hasAnyValue = true;
                    }

                    $rowValues[$headerRow[$col - 1] ?? 'column_'.($col - 1)] = $value;
                }

                if (! $hasAnyValue) {
                    $skippedCount++;
                    continue;
                }

                try {
                    $data = $this->mapImportRow($rowValues);

                    if ($data === null) {
                        $skippedCount++;
                        continue;
                    }

                    $batch[] = array_merge($data, [
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $importedCount++;

                    if (count($batch) >= $batchSize) {
                        LawSchoolLedger::insert($batch);
                        $batch = [];
                    }
                } catch (\Throwable $e) {
                    $failedRows[] = ['row' => $row, 'error' => $e->getMessage()];
                }
            }

            if (! empty($batch)) {
                LawSchoolLedger::insert($batch);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            throw $e;
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return [
            'imported' => $importedCount,
            'skipped' => $skippedCount,
            'failed' => $failedRows,
        ];
    }

    /**
     * CSV import path (smaller files / simple exports).
     *
     * @return array{imported:int,skipped:int,failed:array<int,array{row:int,error:string}>,error?:string}
     */
    private function importFromCsvCollection($uploadedFile): array
    {
        HeadingRowFormatter::default('slug');

        $rows = Excel::toCollection(null, $uploadedFile)->first() ?? collect();

        if ($rows->isEmpty()) {
            return ['imported' => 0, 'skipped' => 0, 'failed' => [], 'error' => 'No rows found in the uploaded file.'];
        }

        $headers = collect($rows->first())
            ->map(fn ($header) => Str::slug(str_replace(["\n", "\r"], ' ', (string) $header), '_'))
            ->all();

        $headerCheck = $this->validateImportHeaders($headers);
        if ($headerCheck !== null) {
            return ['imported' => 0, 'skipped' => 0, 'failed' => [], 'error' => $headerCheck];
        }

        $importedCount = 0;
        $skippedCount = 0;
        $failedRows = [];
        $batch = [];
        $now = now();
        $rowIndex = 1;

        DB::beginTransaction();

        try {
            foreach ($rows->slice(1) as $row) {
                $rowIndex++;

                $rowData = collect($row)->mapWithKeys(function ($value, $index) use ($headers) {
                    return [$headers[$index] ?? 'column_'.$index => $value];
                })->all();

                if (collect($rowData)->filter(fn ($v) => $v !== null && $v !== '')->isEmpty()) {
                    $skippedCount++;
                    continue;
                }

                try {
                    $data = $this->mapImportRow($rowData);

                    if ($data === null) {
                        $skippedCount++;
                        continue;
                    }

                    $batch[] = array_merge($data, [
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $importedCount++;

                    if (count($batch) >= 250) {
                        LawSchoolLedger::insert($batch);
                        $batch = [];
                    }
                } catch (\Throwable $e) {
                    $failedRows[] = ['row' => $rowIndex, 'error' => $e->getMessage()];
                }
            }

            if (! empty($batch)) {
                LawSchoolLedger::insert($batch);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'imported' => $importedCount,
            'skipped' => $skippedCount,
            'failed' => $failedRows,
        ];
    }

    /**
     * @param  array<int,string>  $headers
     */
    private function validateImportHeaders(array $headers): ?string
    {
        if (empty(array_filter($headers))) {
            return 'No valid headers found in the uploaded file.';
        }

        $nameHeaders = ['name_last_name_first_name_m_i', 'name_last_name_first_name_mi', 'student_name', 'student', 'name'];
        $typeHeaders = ['ar_or_payment', 'ar_payment', 'arpayment', 'transaction_type', 'type'];
        $missingHeaders = [];

        if (! array_intersect($nameHeaders, $headers)) {
            $missingHeaders[] = 'Name';
        }

        if (! in_array('amount', $headers, true)) {
            $missingHeaders[] = 'Amount';
        }

        if (! array_intersect($typeHeaders, $headers)) {
            $missingHeaders[] = 'AR/Payment';
        }

        if (! empty($missingHeaders)) {
            return 'Missing required headers: '.implode(', ', $missingHeaders);
        }

        return null;
    }

    /**
     * Renders the React UI for choosing a student and previewing their balance.
     */
    public function printSelect(Request $request): Response
    {
        $students = LawSchoolLedger::query()
            ->whereNotNull('last_name')
            ->where('last_name', '!=', '')
            ->distinct()
            ->orderBy('last_name', 'asc')
            ->get(['last_name', 'first_name', 'middle_initial'])
            ->map(function ($student) {
                return trim("$student->last_name, $student->first_name " . ($student->middle_initial ? "$student->middle_initial" : ''));
            })
            ->unique()
            ->values();

        $selectedStudent = $request->input('student');
        $studentRecords = collect();
        $balanceSummary = [
            'totalAssessments' => 0,
            'totalPayments' => 0,
            'outstandingBalance' => 0,
        ];

        if ($selectedStudent) {
            $studentRecords = $this->queryStudentByName($selectedStudent)
                ->orderBy('id', 'asc')
                ->get()
                ->map(fn ($r) => $this->transformRecord($r));

            $balanceSummary = $this->calculateStudentBalance($studentRecords);
        }

        return Inertia::render('law-ledger/PrintSelect', [
            'students' => $students,
            'selectedStudent' => $selectedStudent,
            'records' => $studentRecords,
            'summary' => $balanceSummary,
        ]);
    }

    /**
     * Generates and streams the PDF statement.
     */
    public function generatePdf(Request $request)
    {
        $request->validate([
            'student' => 'required|string',
        ]);

        $studentName = str_replace(['−', '–', '—'], '-', (string) $request->input('student'));

        $records = $this->queryStudentByName($studentName)
            ->orderBy('id', 'asc')
            ->get();

        $summary = $this->calculateStudentBalance($records);

        $pdf = Pdf::loadView('pdf.law-student-ledger-statement', [
            'studentName' => $studentName,
            'records' => $records,
            'summary' => $summary,
            'generatedAt' => now()->format('Y-m-d'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);

        return $pdf->stream("Statement_of_Account_{$studentName}.pdf");
    }

    /**
     * Maps Excel/CSV rows flexibly to DB columns, tailored for Law School Ledger Excel format.
     */
    private function mapImportRow(array $row): ?array
    {
        $normalized = [];

        foreach ($row as $key => $value) {
            $normalized[Str::slug((string) $key, '_')] = $value;
        }

        // Handles "NAME\n(Last Name, First Name, M.I.)" and standard variations
        $studentName = Arr::get($normalized, 'name_last_name_first_name_m_i')
            ?? Arr::get($normalized, 'name_last_name_first_name_mi')
            ?? Arr::get($normalized, 'student_name')
            ?? Arr::get($normalized, 'student')
            ?? Arr::get($normalized, 'name');

        $studentName = is_string($studentName) ? trim($studentName) : null;

        if (blank($studentName)) {
            return null;
        }

        $nameParts = $this->parseStudentName($studentName);

        $tuitionPerUnit = (float) (
            Arr::get($normalized, 'tuition_per_unit_registration_and_misc_fee_per_semester')
            ?? Arr::get($normalized, 'tuition_per_unit_reg_and_miscellaneous_per_semester')
            ?? Arr::get($normalized, 'tuition_per_unit_or_fee_per_semester')
            ?? Arr::get($normalized, 'tuition_per_unit_or_misc', 0)
            ?? 0
        );

        $units = (float) (Arr::get($normalized, 'units', 0) ?? 0);
        $amount = $this->normalizeAmount(Arr::get($normalized, 'amount'), $units, $tuitionPerUnit);

        return [
            'last_name' => $nameParts['last_name'] ?? $studentName,
            'first_name' => $nameParts['first_name'] ?? null,
            'middle_initial' => $nameParts['middle_initial'] ?? null,
            'course' => Arr::get($normalized, 'course') ?? Arr::get($normalized, 'program'),
            'school_year' => Arr::get($normalized, 'school_year') ?? Arr::get($normalized, 'academic_year') ?? Arr::get($normalized, 'sy'),
            'semester_or_summer' => Arr::get($normalized, 'semester_or_summer')
                ?? Arr::get($normalized, 'semester_summer')
                ?? Arr::get($normalized, 'semester')
                ?? Arr::get($normalized, 'term'),
            'units' => $units,
            'transaction_date' => $this->normalizeDate(
                Arr::get($normalized, 'transaction_date') ?? Arr::get($normalized, 'date')
            ),
            'reference_jev_or_number' => Arr::get($normalized, 'reference_jev_or_number')
                ?? Arr::get($normalized, 'reference_jev_o_r_number')
                ?? Arr::get($normalized, 'reference_or_jev_number')
                ?? Arr::get($normalized, 'jev_no')
                ?? Arr::get($normalized, 'or_no')
                ?? Arr::get($normalized, 'ref_no'),
            'particulars' => Arr::get($normalized, 'particulars'),
            'tuition_per_unit_or_fee_per_semester' => $tuitionPerUnit,
            'ar_or_payment' => Arr::get($normalized, 'ar_or_payment')
                ?? Arr::get($normalized, 'ar_payment')
                ?? Arr::get($normalized, 'arpayment')
                ?? Arr::get($normalized, 'transaction_type')
                ?? Arr::get($normalized, 'type')
                ?? 'AR',
            'amount' => $amount,
            'status' => $this->determineStatus($amount, Arr::get($normalized, 'status')),
            'remarks' => $this->cleanFormulaValue(Arr::get($normalized, 'remarks') ?? Arr::get($normalized, 'remark')),
            'input_by' => Arr::get($normalized, 'input_by'),
        ];
    }

    private function queryStudentByName(string $studentName)
    {
        $cleanName = trim((string) str_replace(['−', '–', '—'], '-', $studentName));

        return LawSchoolLedger::query()->where(function ($q) use ($cleanName) {
            $q->whereRaw("TRIM(CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_initial, ''))) = ?", [$cleanName])
              ->orWhereRaw("TRIM(CONCAT(last_name, ', ', first_name)) = ?", [$cleanName])
              ->orWhere('last_name', 'like', "%{$cleanName}%");
        });
    }

    private function parseStudentName(string $name): ?array
    {
        $name = trim((string) str_replace(['−', '–', '—'], '-', $name));

        if (str_contains($name, ',')) {
            [$lastName, $rest] = explode(',', $name, 2);
            $rest = trim($rest);

            // If there's a middle initial as the last single character / word
            $parts = preg_split('/\s+/', $rest) ?? [];
            if (count($parts) > 1) {
                $lastPart = end($parts);
                if (strlen($lastPart) <= 2) { // Single letter or letter with dot like "A" or "A."
                    $middleInitial = array_pop($parts);
                    $firstName = implode(' ', $parts);
                    return [
                        'last_name' => trim($lastName),
                        'first_name' => trim($firstName),
                        'middle_initial' => rtrim($middleInitial, '.'),
                    ];
                }
            }

            return [
                'last_name' => trim($lastName),
                'first_name' => $rest,
                'middle_initial' => null,
            ];
        }

        return null;
    }

    private function normalizeAmount($value, float $units, float $tuitionPerUnit): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $string = trim((string) $value);

        if (str_starts_with($string, '=')) {
            // Formula cell such as "=E2*I2" or "=I5*E5" (units x tuition per unit)
            if ($units > 0 && $tuitionPerUnit > 0) {
                return $units * $tuitionPerUnit;
            }

            return 0.0;
        }

        return (float) preg_replace('/[^\d.-]/', '', $string);
    }

    private function cleanFormulaValue($value)
    {
        if (is_string($value) && str_starts_with(trim($value), '=')) {
            return null;
        }

        return $value;
    }

    private function normalizeDate($value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value)) {
            $numeric = (float) $value;

            if ($numeric >= 19000000 && $numeric <= 21001231) {
                $date = \Carbon\Carbon::createFromFormat('Ymd', (string) (int) $numeric);

                return $date ? $date->format('Y-m-d') : null;
            }

            try {
                return \Carbon\Carbon::instance(
                    \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($numeric)
                )->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }

        try {
            return \Carbon\Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function determineStatus($amount, ?string $rawStatus): string
    {
        if ($rawStatus) {
            return $rawStatus;
        }

        return $amount > 0 ? 'Pending' : 'Paid';
    }

    private function transformRecord(LawSchoolLedger $r): array
    {
        $studentName = trim("$r->last_name, $r->first_name " . ($r->middle_initial ? "$r->middle_initial" : ''));
        $studentRecords = $this->queryStudentByName($studentName)->get();
        $balanceSummary = $this->calculateStudentBalance($studentRecords);
        
        return [
            'id' => $r->id,
            'lastName' => $r->last_name,
            'firstName' => $r->first_name,
            'middleInitial' => $r->middle_initial,
            'name' => $studentName,
            'course' => $r->course,
            'schoolYear' => $r->school_year,
            'semesterOrSummer' => $r->semester_or_summer,
            'units' => (float) $r->units,
            'transactionDate' => $r->transaction_date,
            'referenceNo' => $r->reference_jev_or_number,
            'particulars' => $r->particulars,
            'tuitionPerUnitOrFeePerSemester' => (float) ($r->tuition_per_unit_or_fee_per_semester ?? 0),
            'arOrPayment' => $r->ar_or_payment,
            'amount' => (float) $r->amount,
            'status' => $r->status,
            'remark' => $balanceSummary['outstandingBalance'] <= 0 ? 'Settled' : 'Outstanding',
            'inputBy' => $r->input_by,
        ];
    }

    private function calculateStudentBalance($records): array
    {
        $totalAssessments = 0;
        $totalPayments = 0;

        foreach ($records as $record) {
            // Support both Eloquent models and transformed arrays
            $rawType = strtoupper(trim($record->ar_or_payment ?? $record['arOrPayment'] ?? $record['ar_or_payment'] ?? ''));
            $cleanAmount = (float) ($record->amount ?? $record['amount'] ?? 0);

            if ($rawType === 'AR' || $rawType === 'ASSESSMENT') {
                $totalAssessments += $cleanAmount;
            } else {
                $totalPayments += abs($cleanAmount); // Ensure payments are treated as positive
            }
        }

        return [
            'totalAssessments' => $totalAssessments,
            'totalPayments' => $totalPayments,
            'outstandingBalance' => $totalAssessments - $totalPayments,
        ];
    }

    private function getFilterOptions(): array
    {
        $currentYear = (int) date('Y');
        $defaultSchoolYears = [];
        for ($i = $currentYear - 5; $i <= $currentYear + 3; $i++) {
            $defaultSchoolYears[] = $i . '-' . ($i + 1);
        }

        $schoolYears = LawSchoolLedger::distinct()
            ->orderBy('school_year', 'desc')
            ->pluck('school_year')
            ->filter()
            ->values()
            ->all();

        if (empty($schoolYears)) {
            $schoolYears = $defaultSchoolYears;
        }

        return [
            'courses' => LawSchoolLedger::distinct()->orderBy('course')->pluck('course')->filter()->values()->all(),
            'schoolYears' => $schoolYears,
            'semesters' => LawSchoolLedger::distinct()->orderBy('semester_or_summer')->pluck('semester_or_summer')->filter()->values()->all(),
            'statuses' => LawSchoolLedger::distinct()->orderBy('status')->pluck('status')->filter()->values()->all(),
        ];
    }
}
