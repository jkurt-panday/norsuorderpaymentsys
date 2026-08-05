<?php

namespace App\Http\Controllers;

use App\Models\LawSchoolLedger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
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

        $totalCharges = (float) (clone $query)
            ->where('ar_or_payment', 'AR')
            ->sum('amount');

        $totalPayments = abs((float) (clone $query)
            ->where('ar_or_payment', 'Payment')
            ->sum('amount'));

        $outstandingBalance = max(0, $totalCharges - $totalPayments);

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
            'file' => ['required', 'file', 'mimes:xlsx,csv,xls'],
        ]);

        HeadingRowFormatter::default('slug');

        try {
            $uploadedFile = $request->file('file');
            $rows = Excel::toCollection(null, $uploadedFile)->first() ?? collect();

            if ($rows->isEmpty()) {
                return redirect()->route('law-ledger.index')->with('error', 'No rows found in the uploaded file.');
            }

            $headers = collect($rows->first())->map(fn ($header) => Str::slug((string) $header, '_'))->all();

            if (empty($headers)) {
                return redirect()->route('law-ledger.index')->with('error', 'No valid headers found in the uploaded file.');
            }

            // Required fields (matching the variants handled by mapImportRow)
            $nameHeaders = ['name_last_name_first_name_m_i', 'name_last_name_first_name_mi', 'student_name', 'student', 'name'];
            $typeHeaders = ['ar_or_payment', 'ar_payment', 'arpayment', 'transaction_type', 'type'];
            $missingHeaders = [];

            if (!array_intersect($nameHeaders, $headers)) {
                $missingHeaders[] = 'Name';
            }

            if (!in_array('amount', $headers)) {
                $missingHeaders[] = 'Amount';
            }

            if (!array_intersect($typeHeaders, $headers)) {
                $missingHeaders[] = 'AR/Payment';
            }

            if (!empty($missingHeaders)) {
                return redirect()->route('law-ledger.index')->with('error', 'Missing required headers: ' . implode(', ', $missingHeaders));
            }

            $importedCount = 0;
            $failedRows = [];
            $rowIndex = 1; // Start after header row

            DB::beginTransaction();

            try {
                $rows->slice(1)->each(function ($row) use ($headers, &$importedCount, &$failedRows, &$rowIndex) {
                    $rowIndex++;
                    if (!is_array($row) && !$row instanceof \Illuminate\Support\Collection) {
                        $failedRows[] = ['row' => $rowIndex, 'error' => 'Invalid row format'];
                        return;
                    }

                    $rowData = collect($row)->mapWithKeys(function ($value, $index) use ($headers) {
                        return [$headers[$index] ?? 'column_' . $index => $value];
                    })->all();

                    try {
                        $data = $this->mapImportRow($rowData);
                        if ($data !== null) {
                            LawSchoolLedger::create($data);
                            $importedCount++;
                        } else {
                            $failedRows[] = ['row' => $rowIndex, 'error' => 'Failed to map row data'];
                        }
                    } catch (\Throwable $e) {
                        $failedRows[] = ['row' => $rowIndex, 'error' => $e->getMessage()];
                    }
                });

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }

            if (!empty($failedRows)) {
                $errorMessage = "Import completed with {$importedCount} rows. Failed rows: " . json_encode($failedRows);
                return redirect()->route('law-ledger.index')->with('warning', $errorMessage);
            }

            return redirect()->route('law-ledger.index')->with('success', "Import completed successfully. Imported {$importedCount} rows.");
        } catch (\Throwable $e) {
            return redirect()->route('law-ledger.index')->with('error', 'Failed to import file: ' . $e->getMessage());
        }
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

            // 8-digit value like 20250805 is a Ymd date; otherwise treat as an Excel serial date
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
