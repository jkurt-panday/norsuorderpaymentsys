<?php

namespace App\Http\Controllers;

use App\Models\GraduateLedger;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $schoolYear = $request->input('school_year');
        $semester = $request->input('semester');
        $course = $request->input('course');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = GraduateLedger::query()
            ->when($request->input('search'), function ($query, $search) {
                $query->where('student_name', 'like', "%{$search}%")
                    ->orWhere('course', 'like', "%{$search}%")
                    ->orWhere('reference_or_jev_number', 'like', "%{$search}%");
            })
            ->when($schoolYear, function ($query, $schoolYear) {
                $query->where('school_year', $schoolYear);
            })
            ->when($semester, function ($query, $semester) {
                $query->where(function ($q) use ($semester) {
                    $q->where('semester_short', $semester)
                      ->orWhere('semester', $semester);
                });
            })
            ->when($course, function ($query, $course) {
                $query->where('course', $course);
            })
            ->when($dateFrom, function ($query, $dateFrom) {
                $query->whereDate('transaction_date', '>=', $dateFrom);
            })
            ->when($dateTo, function ($query, $dateTo) {
                $query->whereDate('transaction_date', '<=', $dateTo);
            });

        $totalStudents = (clone $query)
            ->whereNotNull('student_name')
            ->where('student_name', '!=', '')
            ->distinct('student_name')
            ->count('student_name');

        $totalAssessments = (float) (clone $query)
            ->whereRaw("UPPER(TRIM(ar_payment)) = 'AR'")
            ->sum('amount');

        $totalPayments = (float) (clone $query)
            ->where(function ($q) {
                $q->whereIn(DB::raw('UPPER(TRIM(ar_payment))'), ['PAYMENT', 'P', 'PAYMENR', 'ADJUSTMENT', 'ADJ', 'SETTLED'])
                    ->orWhere('amount', 'like', '%(%');
            })
            ->sum('amount');

        $outstandingBalance = $totalAssessments - $totalPayments;

        $records = $query
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $records->through(fn ($r) => $this->transformRecord($r));

        return Inertia::render('graduate-ledger/Index', [
            'records' => $records,
            'filters' => $request->only(['search', 'school_year', 'semester', 'course', 'date_from', 'date_to']),
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
     * Renders the form for creating a new ledger transaction.
     */
    public function create(): Response
    {
        $studentNames = GraduateLedger::query()
            ->whereNotNull('student_name')
            ->where('student_name', '!=', '')
            ->distinct()
            ->orderBy('student_name')
            ->pluck('student_name');

        return Inertia::render('graduate-ledger/AddTransaction', [
            'studentNames' => $studentNames,
            'authUserName' => auth()->user()?->name ?? '',
        ]);
    }

    /**
     * Shows the edit form for an existing ledger record.
     */
    public function edit(int $id): Response
    {
        $record = GraduateLedger::findOrFail($id);

        $studentNames = GraduateLedger::query()
            ->whereNotNull('student_name')
            ->where('student_name', '!=', '')
            ->distinct()
            ->orderBy('student_name')
            ->pluck('student_name');

        return Inertia::render('graduate-ledger/EditTransaction', [
            'record' => $record,
            'studentNames' => $studentNames,
            'authUserName' => auth()->user()?->name ?? '',
        ]);
    }

    /**
     * Updates an existing ledger record.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $record = GraduateLedger::findOrFail($id);

        $data = $request->validate([
            'student_name'            => ['required', 'string', 'max:255'],
            'course'                  => ['nullable', 'string', 'max:255'],
            'school_year'             => ['nullable', 'string', 'max:50'],
            'semester'                => ['nullable', 'string', 'max:100'],
            'semester_short'          => ['nullable', 'string', 'max:50'],
            'units'                   => ['nullable', 'integer', 'min:0'],
            'transaction_date'        => ['nullable', 'date'],
            'reference_or_jev_number' => ['nullable', 'string', 'max:255'],
            'particulars'             => ['nullable', 'string'],
            'tuition_per_unit_or_misc'=> ['nullable', 'numeric', 'min:0'],
            'ar_payment'              => ['nullable', 'string', 'max:50'],
            'amount'                  => ['nullable', 'numeric', 'min:0'],
            'remarks'                 => ['nullable', 'string'],
            'input_by'                => ['nullable', 'string', 'max:255'],
        ]);

        if (($data['ar_payment'] ?? null) === 'AR' && ($data['amount'] === null || $data['amount'] === '')) {
            $units = $data['units'] ?? 0;
            $rate = $data['tuition_per_unit_or_misc'] ?? 0;
            $data['amount'] = $units * $rate;
        }

        $record->update($data);

        return redirect()->route('graduate-ledger.index')->with('success', 'Transaction updated successfully.');
    }

    /**
     * Deletes a ledger record.
     */
    public function destroy(int $id): RedirectResponse
    {
        GraduateLedger::findOrFail($id)->delete();

        return redirect()->route('graduate-ledger.index')->with('success', 'Transaction deleted successfully.');
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

        if (($data['ar_payment'] ?? null) === 'AR' && ($data['amount'] === null || $data['amount'] === '')) {
            $units = $data['units'] ?? 0;
            $rate = $data['tuition_per_unit_or_misc'] ?? 0;
            $data['amount'] = $units * $rate;
        }

        GraduateLedger::create($data);

        return redirect()->route('graduate-ledger.index');
    }

    /**
     * Imports a CSV/Excel spreadsheet into the graduate ledger.
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

        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $uploadedFile = $request->file('file');
        $extension = strtolower($uploadedFile->getClientOriginalExtension());

        $imported = 0;
        $skipped  = 0;
        $insertData = [];
        $now = now();

        if ($extension === 'csv') {
            $path = $uploadedFile->getRealPath();
            $handle = fopen($path, 'r');
            if (!$handle) {
                return redirect()->route('graduate-ledger.index')
                    ->with('error', 'Could not open the uploaded CSV file.');
            }

            fgetcsv($handle);

            while (($row = fgetcsv($handle)) !== false) {
                $data = $this->mapImportRow($row);

                if ($data === null) {
                    $skipped++;
                    continue;
                }

                $data['created_at'] = $now;
                $data['updated_at'] = $now;
                $insertData[] = $data;

                if (count($insertData) >= 1000) {
                    DB::transaction(function () use ($insertData) {
                        GraduateLedger::insert($insertData);
                    });
                    $imported += count($insertData);
                    $insertData = [];
                }
            }

            if (!empty($insertData)) {
                DB::transaction(function () use ($insertData) {
                    GraduateLedger::insert($insertData);
                });
                $imported += count($insertData);
            }

            fclose($handle);
        } else {
            HeadingRowFormatter::default('none');
            $rows = Excel::toCollection(null, $uploadedFile)->first() ?? collect();

            if ($rows->isEmpty()) {
                return redirect()->route('graduate-ledger.index')
                    ->with('success', 'No rows found in the uploaded file.');
            }

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

            if (!empty($insertData)) {
                DB::transaction(function ($insertData, &$imported) {
                    foreach (array_chunk($insertData, 1000) as $chunk) {
                        GraduateLedger::insert($chunk);
                        $imported += count($chunk);
                    }
                });
            }
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
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);

        return $pdf->stream("Statement_of_Account_{$studentName}.pdf");
    }

    /**
     * Maps a positional CSV/Excel row array to DB columns.
     */
    private function mapImportRow(array $row): ?array
    {
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
            'semester' => $r->semester_short ?: $r->semester ?: '',
            'units' => (float) $r->units,
            'transactionDate' => $r->transaction_date,
            'referenceNo' => $r->reference_or_jev_number,
            'particulars' => $r->particulars,
            'tuitionPerUnitOrFeePerSemester' => (float) ($r->tuition_per_unit_or_misc ?? 0),
            'arPayment' => $this->normalizeArPaymentType($r->ar_payment, $isParenthesesNegative),
            'amount' => $this->cleanAmount($r->amount),
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

    private function getFilterOptions(): array
    {
        $currentYear = (int) date('Y');
        $defaultSchoolYears = [];
        for ($i = $currentYear - 5; $i <= $currentYear + 3; $i++) {
            $defaultSchoolYears[] = $i . '-' . ($i + 1);
        }

        $schoolYears = GraduateLedger::query()
            ->whereNotNull('school_year')
            ->where('school_year', '!=', '')
            ->distinct()
            ->orderBy('school_year', 'desc')
            ->pluck('school_year')
            ->filter()
            ->values()
            ->all();

        if (empty($schoolYears)) {
            $schoolYears = $defaultSchoolYears;
        }

        return [
            'courses' => GraduateLedger::query()
                ->whereNotNull('course')
                ->where('course', '!=', '')
                ->distinct()
                ->orderBy('course')
                ->pluck('course')
                ->filter()
                ->values()
                ->all(),
            'schoolYears' => $schoolYears,
            'semesters' => GraduateLedger::query()
                ->whereNotNull('semester_short')
                ->where('semester_short', '!=', '')
                ->distinct()
                ->orderBy('semester_short')
                ->pluck('semester_short')
                ->filter()
                ->values()
                ->all(),
        ];
    }
}
