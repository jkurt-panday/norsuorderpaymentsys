<?php

namespace App\Http\Controllers;

use App\Models\GraduateLedger;
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
                $q->whereIn(DB::raw('UPPER(TRIM(ar_payment))'), ['PAYMENT', 'P', 'PAYMENR', 'ADJUSTMENT', 'ADJ', 'SETTLED'])
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
     * Imports a CSV/Excel spreadsheet into the graduate ledger.
     * Supports the actual Graduate School Ledger export format where
     * the first column header is blank (student name).
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => [
                'required', 
                'file', 
                function ($attribute, $value, $fail) {
                    $extension = strtolower($value->getClientOriginalExtension());
                    if (!in_array($extension, ['csv', 'xlsx', 'xls'])) {
                        $fail('The file must be a file of type: csv, xlsx, xls.');
                    }
                }
            ],
        ]);

        // 1. Extend execution time for large imports
        set_time_limit(300);

        // Disable automatic heading slug formatting — we handle mapping manually
        HeadingRowFormatter::default('none');

        $uploadedFile = $request->file('file');
        $rows = Excel::toCollection(null, $uploadedFile)->first() ?? collect();

        if ($rows->isEmpty()) {
            return redirect()->route('graduate-ledger.index')
                ->with('success', 'No rows found in the uploaded file.');
        }

        $imported = 0;
        $skipped  = 0;
        $insertData = [];
        $now = now();

        // 2. Map data rows (skip header row)
        $rows->slice(1)->each(function ($row) use (&$insertData, &$skipped, $now) {
            $r = $row->values()->all();
            $data = $this->mapImportRow($r);

            if ($data === null) {
                $skipped++;
                return;
            }

            $data['created_at'] = $now;
            $data['updated_at'] = $now;
            $insertData[] = $data;
        });

        // 3. Bulk insert inside a transaction for maximum speed
        if (!empty($insertData)) {
            DB::transaction(function () use ($insertData, &$imported) {
                foreach (array_chunk($insertData, 1000) as $chunk) {
                    GraduateLedger::insert($chunk);
                    $imported += count($chunk);
                }
            });
        }

        return redirect()->route('graduate-ledger.index')
            ->with('success', "Import complete: {$imported} records imported, {$skipped} blank rows skipped.");
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
     * Maps a positional CSV/Excel row array to DB columns.
     *
     * Expected column positions (0-indexed) for the Graduate School Ledger export:
     *  0  = student_name         (header is blank in the CSV)
     *  1  = course
     *  2  = school_year
     *  3  = semester_short       (e.g. "1st Sem.", "SUMMER")
     *  4  = semester             (e.g. "First Semester", "Summer")
     *  5  = units
     *  6  = transaction_date     (M/D/YYYY format)
     *  7  = reference_or_jev_number
     *  8  = particulars
     *  9  = tuition_per_unit_or_misc
     *  10 = ar_payment           (AR / Payment)
     *  11 = amount
     *  12 = remarks
     *  13 = input_by
     *  14+ = ignored trailing columns
     */
    private function mapImportRow(array $row): ?array
    {
        // --- Student name is always column 0 ---
        $studentName = trim(str_replace(['−', '–', '—'], '-', (string) ($row[0] ?? '')));

        if ($studentName === '') {
            return null;
        }

        $rawAmount  = (string) ($row[11] ?? '0');
        $rawTuition = (string) ($row[9]  ?? '0');

        return [
            'student_name'             => $studentName,
            'course'                   => trim((string) ($row[1] ?? '')),
            'school_year'              => trim((string) ($row[2] ?? '')),
            'semester_short'           => trim((string) ($row[3] ?? '')),
            'semester'                 => trim((string) ($row[4] ?? '')),
            'units'                    => is_numeric($row[5] ?? null) ? (int) $row[5] : null,
            'transaction_date'         => $this->normalizeDate($row[6] ?? null),
            'reference_or_jev_number'  => trim((string) ($row[7] ?? '')),
            'particulars'              => trim((string) ($row[8] ?? '')),
            'tuition_per_unit_or_misc' => $this->cleanAmount($rawTuition),
            'ar_payment'               => $this->normalizeArPaymentType(
                                              (string) ($row[10] ?? ''),
                                              str_contains($rawAmount, '(') && str_contains($rawAmount, ')')
                                          ),
            'amount'                   => $this->cleanAmount($rawAmount),
            'remarks'                  => trim((string) ($row[12] ?? '')),
            'input_by'                 => trim((string) ($row[13] ?? '')),
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
            return Carbon::createFromFormat('Ymd', (string) $value)->format('Y-m-d');
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
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
