<?php

namespace App\Http\Controllers;

use App\Models\GraduateLedger;
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

class GraduateLedgerController extends Controller
{
    /**
     * Display the main Graduate Ledger overview index page.
     */
    public function index(Request $request): Response
    {
        $year = $request->input('year');
        $month = $request->input('month');

        $query = GraduateLedger::query()
            ->when($request->input('search'), function ($query, $search) {
                $query->where('student_name', 'like', "%{$search}%")
                    ->orWhere('id_number', 'like', "%{$search}%")
                    ->orWhere('course', 'like', "%{$search}%")
                    ->orWhere('reference_or_jev_number', 'like', "%{$search}%");
            })
            ->when($year, function ($query, $year) {
                $query->whereYear('transaction_date', $year);
            })
            ->when($month, function ($query, $month) {
                $query->whereMonth('transaction_date', $month);
            });

        // 1. Calculate overall metrics using a cloned query BEFORE pagination
        $totalStudents = (clone $query)
            ->whereNotNull('student_name')
            ->where('student_name', '!=', '')
            ->distinct('student_name')
            ->count('student_name');

        $totalUnits = (float) (clone $query)->sum('units');

        // Aggregates for AR (Charges) and Payments
        $totalCharges = (float) (clone $query)
            ->whereRaw("UPPER(TRIM(ar_payment)) = 'AR'")
            ->sum('amount');

        $totalPayments = (float) (clone $query)
            ->where(function ($q) {
                $q->whereIn(DB::raw("UPPER(TRIM(ar_payment))"), ['PAYMENT', 'P', 'PAYMENR', 'ADJUSTMENT', 'ADJ', 'SETTLED'])
                  ->orWhere('amount', 'like', '%(%');
            })
            ->sum('amount');

        // 2. Fetch paginated records
        $records = $query
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        // Transform each row into the shape Index.tsx expects
        $records->through(fn ($r) => $this->transformRecord($r));

        return Inertia::render('graduate-ledger/Index', [
            'records' => $records,
            'filters' => $request->only(['search', 'year', 'month']),
            'stats' => [
                'totalStudents' => $totalStudents,
                'totalUnits' => $totalUnits,
                'totalCharges' => $totalCharges,
                'totalPayments' => $totalPayments,
                'outstandingBalance' => $totalCharges - $totalPayments,
            ],
        ]);
    }

    /**
     * Renders the form for creating a new ledger transaction.
     */
    public function create(): Response
    {
        return Inertia::render('graduate-ledger/AddTransaction');
    }

    /**
     * Stores a new ledger transaction.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_name' => ['required', 'string', 'max:255'],
            'course' => ['nullable', 'string', 'max:255'],
            'school_year' => ['nullable', 'string', 'max:50'],
            'semester_short' => ['nullable', 'string', 'max:50'],
            'semester' => ['nullable', 'string', 'max:100'],
            'units' => ['nullable', 'integer'],
            'transaction_date' => ['nullable', 'date'],
            'reference_or_jev_number' => ['nullable', 'string', 'max:255'],
            'particulars' => ['nullable', 'string'],
            'tuition_per_unit_or_misc' => ['nullable', 'numeric'],
            'ar_payment' => ['nullable', 'string', 'max:50'],
            'amount' => ['nullable', 'numeric'],
            'remarks' => ['nullable', 'string'],
            'input_by' => ['nullable', 'string', 'max:255'],
        ]);

        GraduateLedger::create($data);

        return redirect()->route('graduate-ledger.index');
    }

    /**
     * Imports a spreadsheet into the graduate ledger.
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
            return redirect()->route('graduate-ledger.index')->with('success', 'No rows found in the uploaded file.');
        }

        $headers = collect($rows->first())->map(fn ($header) => Str::slug((string) $header, '_'))->all();

        $rows->slice(1)->each(function ($row) use ($headers) {
            $rowData = collect($row)->mapWithKeys(function ($value, $index) use ($headers) {
                return [$headers[$index] ?? 'column_'.$index => $value];
            })->all();

            $data = $this->mapImportRow($rowData);

            if ($data !== null) {
                GraduateLedger::create($data);
            }
        });

        return redirect()->route('graduate-ledger.index')->with('success', 'Import completed successfully.');
    }

    /**
     * Renders the React UI for choosing a student and previewing their balance.
     */
    public function printSelect(Request $request): Response
    {
        $students = GraduateLedger::query()
            ->whereNotNull('student_name')
            ->where('student_name', '!=', '')
            ->distinct()
            ->orderBy('student_name', 'asc')
            ->pluck('student_name');

        $selectedStudent = $request->input('student');
        $studentRecords = collect();
        $balanceSummary = [
            'totalCharges' => 0,
            'totalPayments' => 0,
            'outstandingBalance' => 0,
        ];

        if ($selectedStudent) {
            $studentRecords = GraduateLedger::query()
                ->where('student_name', $selectedStudent)
                ->orderBy('id', 'asc')
                ->get();

            $balanceSummary = $this->calculateStudentBalance($studentRecords);
        }

        return Inertia::render('graduate-ledger/PrintSelect', [
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

        $records = GraduateLedger::query()
            ->where('student_name', $studentName)
            ->orderBy('id', 'asc')
            ->get();

        $summary = $this->calculateStudentBalance($records);

        $pdf = Pdf::loadView('pdf.student-ledger-statement', [
            'studentName' => $studentName,
            'records' => $records,
            'summary' => $summary,
            'generatedAt' => now()->format('Y-m-d'),
        ])->setPaper('a4', 'portrait')
            ->setOption('defaultFont', 'DejaVu Sans');

        return $pdf->stream("Statement_of_Account_{$studentName}.pdf");
    }

    /**
     * Maps Excel/CSV rows flexibly to DB columns, tailored for Graduate School Ledger Excel format.
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

        return [
            'student_name' => $studentName,
            'course' => Arr::get($normalized, 'course'),
            'school_year' => Arr::get($normalized, 'school_year') ?? Arr::get($normalized, 'sy'),
            'semester_short' => Arr::get($normalized, 'semester_summer') 
                ?? Arr::get($normalized, 'semester_short') 
                ?? Arr::get($normalized, 'term'),
            'semester' => Arr::get($normalized, 'semester'),
            'units' => (int) round((float) (Arr::get($normalized, 'units', 0) ?? 0)),
            'transaction_date' => $this->normalizeDate(
                Arr::get($normalized, 'transaction_date') ?? Arr::get($normalized, 'date')
            ),
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
            'ar_payment' => Arr::get($normalized, 'ar_payment') 
                ?? Arr::get($normalized, 'arpayment') 
                ?? Arr::get($normalized, 'type'),
            'amount' => (float) (Arr::get($normalized, 'amount', 0) ?? 0),
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

    private function normalizeArPaymentType(?string $rawType, bool $isParenthesesNegative): string
    {
        $type = strtoupper(trim($rawType ?? ''));

        if ($type === 'AR') {
            return 'AR';
        }

        if ($type === 'ADJUSTMENT' || $type === 'ADJ') {
            return 'Adjustment';
        }

        if (in_array($type, ['PAYMENT', 'P', 'PAYMENR', 'SETTLED']) || $isParenthesesNegative) {
            return 'Payment';
        }

        return 'Payment';
    }

    private function cleanAmount($rawAmount): float
    {
        $rawAmount = (string) $rawAmount;

        return abs((float) preg_replace('/[^\d.]/', '', $rawAmount));
    }

    private function transformRecord(GraduateLedger $r): array
    {
        $rawAmount = (string) $r->amount;
        $isParenthesesNegative = str_contains($rawAmount, '(') && str_contains($rawAmount, ')');

        return [
            'id' => $r->id,
            'name' => $r->student_name,
            'course' => $r->course,
            'schoolYear' => $r->school_year,
            'term' => $r->semester_short ?: $r->semester ?: '',
            'units' => (float) $r->units,
            'transactionDate' => $r->transaction_date,
            'referenceNo' => $r->reference_or_jev_number,
            'particulars' => $r->particulars,
            'ratePerUnit' => (float) ($r->tuition_per_unit_or_misc ?? 0),
            'amount' => $this->cleanAmount($r->amount),
            'arPayment' => $this->normalizeArPaymentType($r->ar_payment, $isParenthesesNegative),
            'remark' => $r->remarks,
            'inputBy' => $r->input_by,
        ];
    }

    private function calculateStudentBalance($records): array
    {
        $totalCharges = 0;
        $totalPayments = 0;

        foreach ($records as $record) {
            $rawType = strtoupper(trim($record->ar_payment ?? ''));
            $rawAmount = (string) $record->amount;
            $cleanAmount = abs((float) preg_replace('/[^\d.]/', '', $rawAmount));

            if ($rawType === 'AR') {
                $totalCharges += $cleanAmount;
            } else {
                $totalPayments += $cleanAmount;
            }
        }

        return [
            'totalCharges' => $totalCharges,
            'totalPayments' => $totalPayments,
            'outstandingBalance' => $totalCharges - $totalPayments,
        ];
    }
}