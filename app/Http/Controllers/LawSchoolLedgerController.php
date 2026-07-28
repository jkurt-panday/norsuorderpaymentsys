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
        $academicYear = $request->input('academic_year');
        $semester = $request->input('semester');
        $program = $request->input('program');
        $yearLevel = $request->input('year_level');
        $status = $request->input('status');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = LawSchoolLedger::query()
            ->when($request->input('search'), function ($query, $search) {
                $query->where('student_name', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%")
                    ->orWhere('reference_or_jev_number', 'like', "%{$search}%")
                    ->orWhere('particulars', 'like', "%{$search}%");
            })
            ->when($academicYear, function ($query, $academicYear) {
                $query->where('academic_year', $academicYear);
            })
            ->when($semester, function ($query, $semester) {
                $query->where('semester', $semester);
            })
            ->when($program, function ($query, $program) {
                $query->where('program', $program);
            })
            ->when($yearLevel, function ($query, $yearLevel) {
                $query->where('year_level', $yearLevel);
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
        $totalStudents = (clone $query)
            ->whereNotNull('student_name')
            ->where('student_name', '!=', '')
            ->distinct('student_name')
            ->count('student_name');

        $totalAssessments = (float) (clone $query)
            ->whereRaw("UPPER(TRIM(transaction_type)) = 'ASSESSMENT'")
            ->sum('amount');

        $totalPayments = (float) (clone $query)
            ->whereRaw("UPPER(TRIM(transaction_type)) IN ('PAYMENT', 'P', 'ADJUSTMENT', 'SETTLED')")
            ->sum('amount');

        $outstandingBalance = (float) (clone $query)
            ->where('status', '!=', 'Paid')
            ->sum('remaining_balance');

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
                'search', 'academic_year', 'semester', 'program', 'year_level', 'status', 'date_from', 'date_to'
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
            'student_id' => ['nullable', 'string', 'max:255'],
            'student_name' => ['required', 'string', 'max:255'],
            'program' => ['nullable', 'string', 'max:50'],
            'year_level' => ['nullable', 'string', 'max:10'],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'semester' => ['nullable', 'string', 'max:50'],
            'units' => ['nullable', 'numeric'],
            'transaction_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'reference_or_jev_number' => ['nullable', 'string', 'max:255'],
            'particulars' => ['nullable', 'string'],
            'tuition_per_unit_or_misc' => ['nullable', 'numeric'],
            'transaction_type' => ['nullable', 'string', 'max:50'],
            'amount' => ['nullable', 'numeric'],
            'remaining_balance' => ['nullable', 'numeric'],
            'status' => ['nullable', 'string', 'max:50'],
            'remarks' => ['nullable', 'string'],
            'input_by' => ['nullable', 'string', 'max:255'],
        ]);

        // Auto-calculate remaining_balance if not provided
        if (!isset($data['remaining_balance'])) {
            $data['remaining_balance'] = $data['amount'] ?? 0;
        }

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

        $uploadedFile = $request->file('file');
        $rows = Excel::toCollection(null, $uploadedFile)->first() ?? collect();

        if ($rows->isEmpty()) {
            return redirect()->route('law-ledger.index')->with('success', 'No rows found in the uploaded file.');
        }

        $headers = collect($rows->first())->map(fn ($header) => Str::slug((string) $header, '_'))->all();

        $rows->slice(1)->each(function ($row) use ($headers) {
            $rowData = collect($row)->mapWithKeys(function ($value, $index) use ($headers) {
                return [$headers[$index] ?? 'column_'.$index => $value];
            })->all();

            $data = $this->mapImportRow($rowData);

            if ($data !== null) {
                LawSchoolLedger::create($data);
            }
        });

        return redirect()->route('law-ledger.index')->with('success', 'Import completed successfully.');
    }

    /**
     * Renders the React UI for choosing a student and previewing their balance.
     */
    public function printSelect(Request $request): Response
    {
        $students = LawSchoolLedger::query()
            ->whereNotNull('student_name')
            ->where('student_name', '!=', '')
            ->distinct()
            ->orderBy('student_name', 'asc')
            ->pluck('student_name');

        $selectedStudent = $request->input('student');
        $studentRecords = collect();
        $balanceSummary = [
            'totalAssessments' => 0,
            'totalPayments' => 0,
            'outstandingBalance' => 0,
        ];

        if ($selectedStudent) {
$studentRecords = LawSchoolLedger::query()
            ->where('student_name', $selectedStudent)
            ->orderBy('id', 'asc')
            ->get()
            ->map(fn($r) => $this->transformRecord($r));

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

        $records = LawSchoolLedger::query()
            ->where('student_name', $studentName)
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

        $amount = (float) (Arr::get($normalized, 'amount', 0) ?? 0);

        return [
            'student_id' => Arr::get($normalized, 'student_id') ?? Arr::get($normalized, 'id_number'),
            'student_name' => $studentName,
            'program' => Arr::get($normalized, 'program') ?? Arr::get($normalized, 'course'),
            'year_level' => Arr::get($normalized, 'year_level') ?? Arr::get($normalized, 'year'),
            'academic_year' => Arr::get($normalized, 'academic_year') ?? Arr::get($normalized, 'school_year') ?? Arr::get($normalized, 'sy'),
            'semester' => Arr::get($normalized, 'semester'),
            'units' => (float) (Arr::get($normalized, 'units', 0) ?? 0),
            'transaction_date' => $this->normalizeDate(
                Arr::get($normalized, 'transaction_date') ?? Arr::get($normalized, 'date')
            ),
            'due_date' => $this->normalizeDate(Arr::get($normalized, 'due_date')),
            'reference_or_jev_number' => Arr::get($normalized, 'reference_jev_o_r_number')
                ?? Arr::get($normalized, 'reference_jev_or_number')
                ?? Arr::get($normalized, 'reference_or_jev_number')
                ?? Arr::get($normalized, 'jev_no')
                ?? Arr::get($normalized, 'or_no')
                ?? Arr::get($normalized, 'ref_no'),
            'particulars' => Arr::get($normalized, 'particulars'),
            'tuition_per_unit_or_misc' => (float) (
                Arr::get($normalized, 'tuition_per_unit_reg_and_miscellaneous_per_semester')
                ?? Arr::get($normalized, 'tuition_per_unit_or_misc', 0) 
                ?? 0
            ),
            'transaction_type' => Arr::get($normalized, 'transaction_type') 
                ?? Arr::get($normalized, 'type') 
                ?? 'Assessment',
            'amount' => $amount,
            'remaining_balance' => $amount, // For new imports, remaining balance equals amount initially
            'status' => $this->determineStatus($amount, Arr::get($normalized, 'status')),
            'remarks' => Arr::get($normalized, 'remarks') ?? Arr::get($normalized, 'remark'),
            'input_by' => Arr::get($normalized, 'input_by'),
        ];
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
            return \Carbon\Carbon::createFromFormat('Ymd', (string) $value)->format('Y-m-d');
        }

        try {
            return \Carbon\Carbon::parse((string) $value)->format('Y-m-d');
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
            'studentId' => $r->student_id,
            'name' => $r->student_name,
            'program' => $r->program,
            'yearLevel' => $r->year_level,
            'academicYear' => $r->academic_year,
            'semester' => $r->semester,
            'units' => (float) $r->units,
            'transactionDate' => $r->transaction_date,
            'dueDate' => $r->due_date,
            'referenceNo' => $r->reference_or_jev_number,
            'particulars' => $r->particulars,
            'tuitionPerUnitOrMisc' => (float) ($r->tuition_per_unit_or_misc ?? 0),
            'transactionType' => $r->transaction_type,
            'amount' => (float) $r->amount,
            'remainingBalance' => (float) $r->remaining_balance,
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
            $rawType = strtoupper(trim($record->transaction_type ?? $record['transaction_type'] ?? ''));
            $cleanAmount = (float) ($record->amount ?? $record['amount'] ?? 0);

            if ($rawType === 'ASSESSMENT') {
                $totalAssessments += $cleanAmount;
            } else {
                $totalPayments += $cleanAmount;
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
        $defaultAcademicYears = [];
        for ($i = $currentYear - 5; $i <= $currentYear + 3; $i++) {
            $defaultAcademicYears[] = $i . '-' . ($i + 1);
        }

        $academicYears = LawSchoolLedger::distinct()
            ->orderBy('academic_year', 'desc')
            ->pluck('academic_year')
            ->filter()
            ->values()
            ->all();

        if (empty($academicYears)) {
            $academicYears = $defaultAcademicYears;
        }

        return [
            'programs' => LawSchoolLedger::distinct()->orderBy('program')->pluck('program')->filter()->values()->all(),
            'yearLevels' => LawSchoolLedger::distinct()->orderBy('year_level')->pluck('year_level')->filter()->values()->all(),
            'academicYears' => $academicYears,
            'semesters' => LawSchoolLedger::distinct()->orderBy('semester')->pluck('semester')->filter()->values()->all(),
            'statuses' => LawSchoolLedger::distinct()->orderBy('status')->pluck('status')->filter()->values()->all(),
        ];
    }
}
