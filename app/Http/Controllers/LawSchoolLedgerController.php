<?php

namespace App\Http\Controllers;

use App\Exports\LawSchoolLedgerExport;
use App\Models\LawSchoolLedger;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;

class LawSchoolLedgerController extends Controller
{
    /**
     * Display the Law School Ledger overview index page.
     */
    public function index(Request $request): Response
    {
        set_time_limit(300);

        $query = $this->buildFilteredQuery($request);

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

        // Payments/credits are stored as negative amounts, so compare the transaction
        // type case-insensitively and accumulate payment magnitudes (positive) to keep
        // the overview figures readable. Adjustments are treated as credits.
        $totalAssessments = (float) (clone $query)
            ->whereRaw("UPPER(TRIM(ar_or_payment)) IN ('AR', 'ASSESSMENT')")
            ->sum('amount');

        $totalPayments = (float) (clone $query)
            ->whereRaw("UPPER(TRIM(ar_or_payment)) IN ('PAYMENT', 'P', 'ADJUSTMENT', 'ADJ')")
            ->sum(DB::raw('ABS(amount)'));

        $outstandingBalance = $totalAssessments - $totalPayments;

        // 2. Fetch paginated records
        $records = $query
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        // Transform each row into the shape Index.tsx expects
        $records->through(fn ($r) => $this->transformRecord($r));

        return Inertia::render('law-ledger/Index', [
            'records' => $records,
            'filters' => $request->only([
                'search', 'school_year', 'semester_or_summer', 'course', 'status', 'ar_or_payment', 'date_from', 'date_to',
            ]),
            'stats' => [
                'totalStudents' => $totalStudents,
                'totalAssessments' => $totalAssessments,
                'totalPayments' => $totalPayments,
                'outstandingBalance' => $outstandingBalance,
            ],
            'filterOptions' => $this->getFilterOptions(),
        ]);
    }

    public function searchStudents(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $query = LawSchoolLedger::query()
            ->whereNotNull('last_name')
            ->where('last_name', '!=', '');

        if ($search !== '') {
            $searchLower = strtolower($search);
            $query->where(function ($q) use ($searchLower) {
                $q->whereRaw('LOWER(last_name) LIKE ?', ["%{$searchLower}%"])
                    ->orWhereRaw('LOWER(first_name) LIKE ?', ["%{$searchLower}%"])
                    ->orWhereRaw('LOWER(middle_initial) LIKE ?', ["%{$searchLower}%"])
                    ->orWhereRaw("LOWER(TRIM(CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_initial, '')))) LIKE ?", ["%{$searchLower}%"]);
            });
        }

        $students = $query
            ->get(['last_name', 'first_name', 'middle_initial'])
            ->map(function ($student) {
                return trim("$student->last_name, $student->first_name ".($student->middle_initial ? "$student->middle_initial" : ''));
            })
            ->unique()
            ->values()
            ->all();

        return response()->json($students);
    }

    /**
     * Exports the filtered Law School Ledger records to an Excel file.
     */
    public function export(Request $request)
    {
        $query = $this->buildFilteredQuery($request);

        $filename = 'law_ledger_export_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(new LawSchoolLedgerExport($query), $filename);
    }

    /**
     * Renders the form for creating a new law ledger transaction.
     */
    public function create(): Response
    {
        $studentNames = LawSchoolLedger::query()
            ->whereNotNull('last_name')
            ->where('last_name', '!=', '')
            ->distinct()
            ->orderBy('last_name', 'asc')
            ->get(['last_name', 'first_name', 'middle_initial'])
            ->map(function ($student) {
                return trim("$student->last_name, $student->first_name ".($student->middle_initial ? "$student->middle_initial" : ''));
            })
            ->unique()
            ->values();

        return Inertia::render('law-ledger/AddTransaction', [
            'studentNames' => $studentNames,
            'authUserName' => auth()->user()?->name ?? '',
        ]);
    }

    /**
     * Stores a new law ledger transaction.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_name' => ['nullable', 'string', 'max:510'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
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

        if (filled($data['student_name'] ?? null)) {
            $nameParts = $this->parseStudentName($data['student_name']) ?? [
                'last_name' => trim($data['student_name']),
                'first_name' => null,
                'middle_initial' => null,
            ];

            $data['last_name'] = $nameParts['last_name'];
            $data['first_name'] = $nameParts['first_name'];
            $data['middle_initial'] = $nameParts['middle_initial'];
        }

        // If Type is AR and no amount was supplied, auto-calculate from units × tuition rate.
        if (($data['ar_or_payment'] ?? null) === 'AR' && blank($data['amount'] ?? null)) {
            $data['amount'] = (float) ($data['units'] ?? 0) * (float) ($data['tuition_per_unit_or_fee_per_semester'] ?? 0);
        }

        $data['status'] = $this->determineStatus((float) ($data['amount'] ?? 0), $data['status'] ?? null);

        LawSchoolLedger::create($data);

        return redirect()->route('law-ledger.index');
    }

    /**
     * Renders the form for editing an existing law ledger transaction.
     */
    public function edit(int $id): Response
    {
        $record = LawSchoolLedger::findOrFail($id);

        return Inertia::render('law-ledger/EditTransaction', [
            'record' => $record,
            'filterOptions' => $this->getFilterOptions(),
        ]);
    }

    /**
     * Updates an existing law ledger transaction.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $record = LawSchoolLedger::findOrFail($id);

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

        $record->update($data);

        return redirect()->route('law-ledger.index')->with('success', 'Transaction updated successfully.');
    }

    /**
     * Deletes an existing law ledger transaction.
     */
    public function destroy(int $id): RedirectResponse
    {
        LawSchoolLedger::findOrFail($id)->delete();

        return redirect()->route('law-ledger.index')->with('success', 'Transaction deleted successfully.');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                function ($attribute, $value, $fail) {
                    $extension = strtolower($value->getClientOriginalExtension());
                    if (! in_array($extension, ['csv', 'xlsx', 'xls'])) {
                        $fail('The file must be a file of type: csv, xlsx, xls.');
                    }
                },
            ],
        ]);

        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        $uploadedFile = $request->file('file');
        $extension    = strtolower($uploadedFile->getClientOriginalExtension());
        $path         = $uploadedFile->getRealPath();
        $imported     = 0;
        $now          = now();

        if ($extension === 'csv') {
            $handle = fopen($path, 'r');
            if (!$handle) {
                return redirect()->route('law-ledger.index')->with('error', 'Could not open CSV file.');
            }
            $headerRow = fgetcsv($handle);
            $headers   = $headerRow ? array_map(fn ($h) => Str::slug((string) $h, '_'), $headerRow) : [];
            $insertData = [];

            while (($row = fgetcsv($handle)) !== false) {
                $rowData = [];
                foreach ($row as $index => $value) {
                    $key = $headers[$index] ?? 'column_'.$index;
                    $rowData[$key] = $value;
                }
                $data = $this->mapImportRow($rowData);
                if ($data !== null) {
                    $data['created_at'] = $now;
                    $data['updated_at'] = $now;
                    $insertData[]       = $data;
                }

                if (count($insertData) >= 1000) {
                    DB::transaction(fn () => LawSchoolLedger::insert($insertData));
                    $imported  += count($insertData);
                    $insertData = [];
                }
            }
            if (!empty($insertData)) {
                DB::transaction(fn () => LawSchoolLedger::insert($insertData));
                $imported += count($insertData);
            }
            fclose($handle);

            return redirect()->route('law-ledger.index')->with('success', "Import complete: {$imported} records imported.");
        }

        // Excel (.xlsx, .xls)
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        $spreadsheet = $reader->load($path);
        $sheet       = $spreadsheet->getActiveSheet();
        $highestRow  = $sheet->getHighestRow();
        $highestCol  = $sheet->getHighestColumn();

        if ($highestRow <= 1) {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            return redirect()->route('law-ledger.index')->with('success', 'No rows found in the uploaded file.');
        }

        $headerRow = [];
        for ($col = 'A'; $col <= $highestCol; $col++) {
            $headerRow[] = Str::slug((string) $sheet->getCell($col . '1')->getValue(), '_');
        }

        $insertData = [];

        for ($r = 2; $r <= $highestRow; $r++) {
            $rowData = [];
            $cIdx    = 0;
            for ($col = 'A'; $col <= $highestCol; $col++) {
                $key           = $headerRow[$cIdx] ?? 'column_'.$cIdx;
                $rowData[$key] = $sheet->getCell($col . $r)->getValue();
                $cIdx++;
            }

            $data = $this->mapImportRow($rowData);
            if ($data !== null) {
                $data['created_at'] = $now;
                $data['updated_at'] = $now;
                $insertData[]       = $data;
            }

            if (count($insertData) >= 1000) {
                DB::transaction(fn () => LawSchoolLedger::insert($insertData));
                $imported  += count($insertData);
                $insertData = [];
            }
        }

        if (!empty($insertData)) {
            DB::transaction(fn () => LawSchoolLedger::insert($insertData));
            $imported += count($insertData);
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return redirect()->route('law-ledger.index')->with('success', "Import complete: {$imported} records imported.");
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
                return trim("$student->last_name, $student->first_name ".($student->middle_initial ? "$student->middle_initial" : ''));
            })
            ->unique()
            ->values();

        $selectedStudent = $request->input('student');
        $studentRecords = collect();
        $balanceSummary = [
            'totalCharges' => 0,
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
        set_time_limit(300);

        $request->validate([
            'student' => 'required|string',
        ]);

        $studentName = str_replace(['−', '–', '—'], '-', (string) $request->input('student'));

        $records = $this->queryStudentByName($studentName)
            ->orderBy('id', 'asc')
            ->get();

        $summary = $this->calculateStudentBalance($records);

        $logoPath = public_path('norsu.png');
        $logoDataUri = file_exists($logoPath)
            ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath))
            : null;

        $pdf = Pdf::loadView('pdf.law-student-ledger-statement', [
            'studentName' => $studentName,
            'records' => $records,
            'summary' => $summary,
            'generatedAt' => now()->timezone('Asia/Manila')->format('Y-m-d h:i A'),
            'logoDataUri' => $logoDataUri,
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

        // LAW_SCHOOL_Ledger.csv uses separate name columns; older exports may use
        // a combined name field. Handle both formats.
        $lastName = Arr::get($normalized, 'last_name');
        $firstName = Arr::get($normalized, 'first_name');
        $middleInitial = Arr::get($normalized, 'middle_initial');

        if ($lastName || $firstName) {
            $nameParts = [
                'last_name' => is_string($lastName) ? trim($lastName) : null,
                'first_name' => is_string($firstName) ? trim($firstName) : null,
                'middle_initial' => is_string($middleInitial) ? rtrim(trim($middleInitial), '.') : null,
            ];
        } else {
            $studentName = Arr::get($normalized, 'name_last_name_first_name_m_i')
                ?? Arr::get($normalized, 'name_last_name_first_name_mi')
                ?? Arr::get($normalized, 'student_name')
                ?? Arr::get($normalized, 'student')
                ?? Arr::get($normalized, 'name');

            $studentName = is_string($studentName) ? trim($studentName) : null;

            if (blank($studentName)) {
                return null;
            }

            $nameParts = $this->parseStudentName($studentName) ?? [
                'last_name' => $studentName,
                'first_name' => null,
                'middle_initial' => null,
            ];
        }

        $amount = (float) (Arr::get($normalized, 'amount', 0) ?? 0);

        return [
            'last_name' => $nameParts['last_name'],
            'first_name' => $nameParts['first_name'],
            'middle_initial' => $nameParts['middle_initial'],
            'course' => Arr::get($normalized, 'course') ?? Arr::get($normalized, 'program'),
            'school_year' => Arr::get($normalized, 'school_year') ?? Arr::get($normalized, 'academic_year') ?? Arr::get($normalized, 'sy'),
            'semester_or_summer' => Arr::get($normalized, 'semester_or_summer')
                ?? Arr::get($normalized, 'semester')
                ?? Arr::get($normalized, 'term'),
            'units' => (float) (Arr::get($normalized, 'units', 0) ?? 0),
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
            'tuition_per_unit_or_fee_per_semester' => (float) (
                Arr::get($normalized, 'tuition_per_unit_registration_and_misc_fee_per_semester')
                ?? Arr::get($normalized, 'tuition_per_unit_reg_and_miscellaneous_per_semester')
                ?? Arr::get($normalized, 'tuition_per_unit_or_fee_per_semester')
                ?? Arr::get($normalized, 'tuition_per_unit_or_misc', 0)
                ?? 0
            ),
            'ar_or_payment' => Arr::get($normalized, 'ar_or_payment')
                ?? Arr::get($normalized, 'ar_payment')
                ?? Arr::get($normalized, 'transaction_type')
                ?? Arr::get($normalized, 'type')
                ?? 'AR',
            'amount' => $amount,
            'status' => $this->determineStatus($amount, Arr::get($normalized, 'status')),
            'remarks' => Arr::get($normalized, 'remarks') ?? Arr::get($normalized, 'remark'),
            'input_by' => Arr::get($normalized, 'input_by'),
        ];
    }

    /**
     * Builds the Law School Ledger query with the filters shared by the index
     * and export methods.
     */
    private function buildFilteredQuery(Request $request): Builder
    {
        $schoolYear = $request->input('school_year');
        $semester = $request->input('semester_or_summer');
        $course = $request->input('course');
        $status = $request->input('status');
        $type = $request->input('ar_or_payment');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        return LawSchoolLedger::query()
            ->when($request->input('search'), function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->whereRaw('LOWER(first_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(middle_initial) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(reference_jev_or_number) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(particulars) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw("LOWER(TRIM(CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_initial, '')))) LIKE ?", ["%{$search}%"]);
                });
            })
            ->when($schoolYear, function ($query, $schoolYear) {
                $query->where('school_year', $schoolYear);
            })
            ->when($semester, function ($query, $semester) {
                // Match every stored variant that maps to the selected semester label
                // (e.g. "1st Sem" also matches "First Semester").
                $query->where(function ($query) use ($semester) {
                    foreach ($this->semesterAliases($semester) as $alias) {
                        $query->orWhereRaw('UPPER(TRIM(semester_or_summer)) = ?', [strtoupper($alias)]);
                    }
                });
            })
            ->when($course, function ($query, $course) {
                $query->where('course', $course);
            })
            ->when($status, function ($query, $status) {
                // Trim + case-insensitive match so "DROP" also finds rows stored as
                // " DROP" (the dropdown shows the deduplicated modal label).
                $query->whereRaw('UPPER(TRIM(status)) = ?', [strtoupper(trim($status))]);
            })
            ->when($type, function ($query, $type) {
                $query->whereRaw('UPPER(TRIM(ar_or_payment)) = ?', [strtoupper(trim($type))]);
            })
            ->when($dateFrom, function ($query, $dateFrom) {
                $query->whereDate('transaction_date', '>=', $dateFrom);
            })
            ->when($dateTo, function ($query, $dateTo) {
                $query->whereDate('transaction_date', '<=', $dateTo);
            });
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

    private function normalizeDate($value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value)) {
            return Carbon::createFromFormat('Ymd', (string) $value)->format('Y-m-d');
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Exception $e) {
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
        return [
            'id' => $r->id,
            'lastName' => $r->last_name,
            'firstName' => $r->first_name,
            'middleInitial' => $r->middle_initial,
            'name' => trim("$r->last_name, $r->first_name ".($r->middle_initial ? "$r->middle_initial" : '')),
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
            'remark' => $r->remarks,
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
                // Payments and adjustments are stored as negative amounts; accumulate
                // their magnitudes so the summary shows positive credit figures.
                $totalPayments += abs($cleanAmount);
            }
        }

        return [
            'totalCharges' => $totalAssessments,
            'totalPayments' => $totalPayments,
            'outstandingBalance' => $totalAssessments - $totalPayments,
        ];
    }

    private function getFilterOptions(): array
    {
        $currentYear = (int) date('Y');
        $defaultSchoolYears = [];
        for ($i = $currentYear - 5; $i <= $currentYear + 3; $i++) {
            $defaultSchoolYears[] = $i.'-'.($i + 1);
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
            // Normalize semester labels to a canonical set ("1st Sem", "2nd Sem",
            // "Summer") so equivalent values such as "First Semester" do not appear
            // as separate dropdown options. The canonical labels are always offered
            // even when no records exist yet for that term (e.g. Summer).
            'semesters' => collect(['1st Sem', '2nd Sem', 'Summer'])
                ->merge(
                    collect($this->deduplicatedOptions('semester_or_summer'))
                        ->map(fn ($value) => $this->normalizeSemester($value))
                )
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all(),
            // Deduplicate status options case-insensitively (ignoring whitespace) so
            // variants like " DROP" and "DROP" collapse into a single "DROP" option.
            'statuses' => $this->deduplicatedOptions('status'),
            'types' => $this->deduplicatedOptions('ar_or_payment'),
        ];
    }

    /**
     * Returns distinct values for a dropdown column, deduplicated case-insensitively
     * and ignoring surrounding whitespace. The most frequent stored variant is used
     * as the display label.
     */
    private function deduplicatedOptions(string $column): array
    {
        return collect(
            LawSchoolLedger::query()
                ->selectRaw("{$column} as value, UPPER(TRIM({$column})) as option_key, COUNT(*) as option_count")
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->groupBy('value', 'option_key')
                ->get()
        )
            ->groupBy('option_key')
            ->map(function ($variants) {
                return $variants->sortByDesc('option_count')->first()->value;
            })
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Maps any stored semester variant to a canonical dropdown label so equivalent
     * values ("1st Sem", "First Semester", ...) collapse into a single option.
     */
    private function normalizeSemester(string $value): string
    {
        $normalized = strtoupper((string) preg_replace('/\s+/', ' ', trim($value)));

        return match ($normalized) {
            '1ST SEM', 'FIRST SEMESTER', '1ST SEMESTER', 'FIRST SEM', '1ST', '1' => '1st Sem',
            '2ND SEM', 'SECOND SEMESTER', '2ND SEMESTER', 'SECOND SEM', '2ND', '2' => '2nd Sem',
            'SUMMER', 'SUMMER TERM', 'SUMMER SEMESTER', '3RD SEM', '3RD SEMESTER' => 'Summer',
            default => trim($value),
        };
    }

    /**
     * Returns the stored-value aliases that belong to a canonical semester label,
     * used so the semester filter matches every equivalent stored variant.
     */
    private function semesterAliases(string $semester): array
    {
        return match ($this->normalizeSemester($semester)) {
            '1st Sem' => ['1st Sem', 'First Semester', '1st Semester', 'First Sem'],
            '2nd Sem' => ['2nd Sem', 'Second Semester', '2nd Semester', 'Second Sem'],
            'Summer' => ['Summer', 'Summer Term', 'Summer Semester'],
            default => [trim($semester)],
        };
    }
}
