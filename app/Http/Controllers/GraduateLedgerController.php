<?php

namespace App\Http\Controllers;

use App\Models\GraduateLedger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GraduateLedgerController extends Controller
{
    /**
     * Display the main Graduate Ledger overview index page.
     */
    public function index(Request $request): Response
    {
        $query = GraduateLedger::query()
            ->when($request->input('search'), function ($query, $search) {
                $query->where('student_name', 'like', "%{$search}%")
                    ->orWhere('id_number', 'like', "%{$search}%");
            });

        $records = $query
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        // Transform each row into the shape Index.tsx expects (camelCase,
        // normalized amount/type) without breaking the paginator's
        // links/meta metadata.
        $records->through(fn ($r) => $this->transformRecord($r));

        $totalStudents = (clone $query)
            ->whereNotNull('student_name')
            ->where('student_name', '!=', '')
            ->select('student_name')
            ->distinct('student_name')
            ->count();

        return Inertia::render('graduate-ledger/Index', [
            'records' => $records,
            'filters' => $request->only(['search']),
            'stats' => [
                'totalStudents' => $totalStudents,
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
     * Renders the React UI for choosing a student and previewing their balance.
     */
    public function printSelect(Request $request): Response
    {
        // Fetch distinct student names for the select dropdown
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
            'generatedAt' => now()->format('F d, Y'),
        ])->setPaper('a4', 'portrait')
            ->setOption('defaultFont', 'DejaVu Sans');

        return $pdf->stream("Statement_of_Account_{$studentName}.pdf");
    }

    /**
     * Normalizes the ar_payment column into the 3 canonical labels the
     * frontend expects: 'AR' | 'Payment' | 'Adjustment'.
     */
    private function normalizeArPaymentType(?string $rawType, bool $isParenthesesNegative): string
    {
        $type = strtoupper(trim($rawType ?? ''));

        if ($type === 'AR') {
            return 'AR';
        }

        if ($type === 'ADJUSTMENT' || $type === 'ADJ') {
            return 'Adjustment';
        }

        // PAYMENT, P, PAYMENR (typo in source data), or parentheses-negative
        // amounts all count as payments.
        if (in_array($type, ['PAYMENT', 'P', 'PAYMENR']) || $isParenthesesNegative) {
            return 'Payment';
        }

        // Fallback: default unknown types to Payment so they still bucket
        // into a valid value for the frontend's union type.
        return 'Payment';
    }

    /**
     * Cleans a raw amount string like "(1,175.00)" or "1,800.00" into a
     * clean unsigned float.
     */
    private function cleanAmount($rawAmount): float
    {
        $rawAmount = (string) $rawAmount;

        return abs((float) preg_replace('/[^\d.]/', '', $rawAmount));
    }

    /**
     * Transforms a single GraduateLedger model into the exact shape
     * Index.tsx's LedgerRecord interface expects.
     *
     * ASSUMPTION: adjust the left-hand $r-> column names below if your
     * actual DB/migration uses different column names.
     */
    private function transformRecord(GraduateLedger $r): array
    {
        $rawAmount = (string) $r->amount;
        $isParenthesesNegative = str_contains($rawAmount, '(') && str_contains($rawAmount, ')');

        return [
            'id' => $r->id,
            'name' => $r->student_name,
            'course' => $r->course,
            'schoolYear' => $r->school_year,
            'term' => $r->term,
            'units' => (float) $r->units,
            'transactionDate' => $r->transaction_date,
            'referenceNo' => $r->reference_no,
            'particulars' => $r->particulars,
            'ratePerUnit' => (float) $r->rate_per_unit,
            'amount' => $this->cleanAmount($r->amount),
            'arPayment' => $this->normalizeArPaymentType($r->ar_payment, $isParenthesesNegative),
            'remark' => $r->remark,
            'inputBy' => $r->input_by,
        ];
    }

    /**
     * Helper to compute total charges, payments, and running balance.
     */
    private function calculateStudentBalance($records)
    {
        $totalCharges = 0;
        $totalPayments = 0;

        foreach ($records as $record) {
            $rawType = strtoupper(trim($record->ar_payment ?? ''));

            // Convert string formats like "(1,175.00)" or "1,800.00" to clean numeric values
            $rawAmount = (string) $record->amount;
            $isParenthesesNegative = str_contains($rawAmount, '(') && str_contains($rawAmount, ')');
            $cleanAmount = abs((float) preg_replace('/[^\d.]/', '', $rawAmount));

            if ($rawType === 'AR') {
                $totalCharges += $cleanAmount;
            } elseif (in_array($rawType, ['PAYMENT', 'P', 'PAYMENR']) || $isParenthesesNegative) {
                $totalPayments += $cleanAmount;
            } elseif ($rawType === 'ADJUSTMENT' || $rawType === 'ADJ') {
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
