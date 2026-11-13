<?php

namespace App\Http\Controllers;

use App\Exports\GraduateLedgerExport;
use App\Http\Requests\StoreGraduateLedgerRequest;
use App\Http\Requests\UpdateGraduateLedgerRequest;
use App\Mail\GraduateLedgerStatementMail;
use App\Models\AcademicTerm;
use App\Models\ActivityLog;
use App\Models\Course;
use App\Models\GraduateLedger;
use App\Models\StaffInput;
use App\Models\Student;
// use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\GraduateLedgerImportClassifier;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * @phpstan-type WarningCounts array<string, int>
 * @phpstan-type BalanceSummary array{totalCharges: float, totalPayments: float, outstandingBalance: float}
 */
class GraduateLedgerController extends Controller
{
    public function __construct(
        private readonly GraduateLedgerImportClassifier $importClassifier,
    ) {}

    // ─── Index ────────────────────────────────────────────────────────────────

    /**
     * Display the main Graduate Ledger overview index page.
     */
    public function index(Request $request): Response
    {
        $query = $this->buildFilteredQuery($request);

        // ── Stats ──────────────────────────────────────────────────────────
        $totalStudents = (clone $query)
            ->whereNotNull('student_id')
            ->reorder()
            ->distinct()
            ->count('student_id');

        $totalAssessments = (float) (clone $query)
            ->whereRaw("LOWER(TRIM(entry_type)) = 'ar'")
            ->sum('amount');

        $totalPayments = (float) (clone $query)
            ->whereRaw("LOWER(TRIM(entry_type)) = 'payment'")
            ->sum('amount');

        $totalAdjustments = (float) (clone $query)
            ->whereRaw("LOWER(TRIM(entry_type)) = 'adjustment'")
            ->sum('amount');

        $outstandingBalance = $totalAssessments - $totalPayments - $totalAdjustments;

        $records = $query->paginate(15)->withQueryString();
        $termBalances = $this->calculateTermBalancesForRecords($records->getCollection());
        $records->through(fn ($r) => $this->transformRecord($r, $termBalances));

        return Inertia::render('graduate-ledger/Index', [
            'records' => $records,
            'filters' => $request->only(['search', 'school_year', 'semester', 'course', 'entry_type', 'date_from', 'date_to', 'balance_status']),
            'stats' => [
                'totalStudents' => $totalStudents,
                'totalAssessments' => $totalAssessments,
                'totalPayments' => $totalPayments,
                'totalAdjustments' => $totalAdjustments,
                'outstandingBalance' => $outstandingBalance,
            ],
            'filterOptions' => $this->getFilterOptions(),
            // Used by the import preset modal
            'courses' => $this->courseList(),
            'academicTerms' => $this->academicTermList(),
        ]);
    }

    /**
     * Exports filtered records to an Excel file (.xlsx).
     */
    public function export(Request $request): BinaryFileResponse
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $query = $this->buildFilteredQuery($request);
        $filename = 'graduate_ledger_export_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(new GraduateLedgerExport($query), $filename);
    }

    /**
     * Builds the filtered Eloquent query based on request parameters.
     */
    /** @return Builder<GraduateLedger> */
    private function buildFilteredQuery(Request $request): Builder
    {
        $schoolYear = $request->input('school_year');
        $semester = $request->input('semester');
        $course = $request->input('course');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $search = $request->input('search');
        $balanceStatus = $request->input('balance_status');
        $entryType = $request->input('entry_type');

        $query = GraduateLedger::query();

        $query->with(['student', 'course', 'academicTerm', 'inputByUser:id,name']);

        $caseSql = "SUM(CASE WHEN LOWER(TRIM(balance_rows.entry_type)) = 'ar' THEN balance_rows.amount WHEN LOWER(TRIM(balance_rows.entry_type)) IN ('payment','adjustment') THEN -balance_rows.amount ELSE 0 END)";

        if ($balanceStatus === 'with_balance' || $request->boolean('has_balance')) {
            $query->whereExists(function ($subQuery) use ($caseSql) {
                $subQuery->selectRaw('1')
                    ->from('graduate_ledgers as balance_rows')
                    ->whereColumn('balance_rows.student_id', 'graduate_ledgers.student_id')
                    ->whereColumn('balance_rows.academic_term_id', 'graduate_ledgers.academic_term_id')
                    ->groupBy('balance_rows.student_id', 'balance_rows.academic_term_id')
                    ->havingRaw("{$caseSql} > 0");
            });
        } elseif ($balanceStatus === 'cleared') {
            $query->whereExists(function ($subQuery) use ($caseSql) {
                $subQuery->selectRaw('1')
                    ->from('graduate_ledgers as balance_rows')
                    ->whereColumn('balance_rows.student_id', 'graduate_ledgers.student_id')
                    ->whereColumn('balance_rows.academic_term_id', 'graduate_ledgers.academic_term_id')
                    ->groupBy('balance_rows.student_id', 'balance_rows.academic_term_id')
                    ->havingRaw("{$caseSql} <= 0");
            });
        }

        if ($search) {
            $term = '%'.strtolower($search).'%';
            $query->where(function ($q) use ($term) {
                $q->whereHas('student', fn ($sq) => $sq
                    ->whereRaw('LOWER(last_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(first_name) LIKE ?', [$term])
                    ->orWhereRaw("LOWER(CONCAT(last_name, ', ', first_name)) LIKE ?", [$term])
                    ->orWhereRaw("LOWER(CONCAT(first_name, ' ', last_name)) LIKE ?", [$term]))
                    ->orWhereHas('course', fn ($sq) => $sq
                        ->whereRaw('LOWER(course_code) LIKE ?', [$term]))
                    ->orWhereRaw('LOWER(reference_number) LIKE ?', [$term]);
            });
        }

        if ($schoolYear) {
            $query->whereHas('academicTerm', fn ($q) => $q->where('school_year', $schoolYear));
        }

        if ($semester) {
            $query->whereHas('academicTerm', fn ($q) => $q->where('semester', $semester));
        }

        if ($course) {
            $query->whereHas('course', fn ($q) => $q->where('course_code', $course));
        }

        if ($entryType) {
            $query->whereRaw('LOWER(TRIM(entry_type)) = ?', [strtolower(trim($entryType))]);
        }

        $query
            ->when($dateFrom, fn ($q, $v) => $q->whereDate('transaction_date', '>=', $v))
            ->when($dateTo, fn ($q, $v) => $q->whereDate('transaction_date', '<=', $v));

        // ── Relevance sort: starts-with results float to top ───────────────
        if ($search) {
            $prefix = strtolower($search).'%';
            // Join graduate_student so we can ORDER BY its columns
            $query->orderByRaw(
                'CASE WHEN EXISTS (
                    SELECT 1 FROM students gs
                    WHERE gs.id = graduate_ledgers.student_id
                    AND (LOWER(gs.last_name) LIKE ? OR LOWER(gs.first_name) LIKE ?)
                ) THEN 0 ELSE 1 END',
                [$prefix, $prefix]
            )->latest('id');
        } else {
            $query->latest('id');
        }

        return $query;
    }

    // ─── Create / Store ───────────────────────────────────────────────────────

    /**
     * Renders the form for creating a new ledger transaction.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('graduate-ledger/AddTransaction', [
            'students' => $this->studentList(),
            'courses' => $this->courseList(),
            'academicTerms' => $this->academicTermList(),
            'authUserName' => optional(auth()->user())->name ?? '',
            'selectedStudentId' => $request->integer('student_id') ?: null,
            'defaultEntryType' => in_array($request->input('entry_type'), ['ar', 'payment', 'adjustment'], true)
                ? $request->input('entry_type')
                : 'ar',
        ]);
    }

    /**
     * Return a student's complete graduate-ledger history and balance summary.
     */
    public function studentBalance(Student $student): JsonResponse
    {
        $records = GraduateLedger::query()
            ->with(['student', 'course', 'academicTerm', 'inputByUser:id,name'])
            ->where('student_id', $student->id)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();

        $termBalances = $this->calculateTermBalancesForRecords($records);
        $latestRecord = $records->first();

        return response()->json([
            'student' => [
                'id' => $student->id,
                'studentNumber' => $student->student_number,
                'name' => $student->full_name,
                'email' => $student->email,
                'contactNumber' => $student->contact_num,
                'course' => $latestRecord?->course?->code,
            ],
            'summary' => $this->calculateStudentBalanceNormalized($records),
            'transactions' => $records
                ->map(fn (GraduateLedger $record) => $this->transformRecord($record, $termBalances))
                ->values(),
        ]);
    }

    /**
     * Stores a new ledger transaction, or a batch of transactions when
     * `items` is present on the payload.
     */
    public function store(StoreGraduateLedgerRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // ── Batch mode: many line items sharing one student / term / date ──
        $batchItems = $data['items'] ?? null;

        if (is_array($batchItems) && filled($batchItems)) {
            return $this->storeBatchItems($request, $data, $batchItems);
        }

        DB::transaction(function () use ($data): void {
            $studentId = $this->resolveStudentId($data);

            $record = GraduateLedger::create($this->ledgerAttributes(
                $data,
                (int) $studentId,
                $this->resolveAcademicTermId($data),
            ));

            // Inline scholarship: tag the AR and create its adjustment twin.
            if ($this->shouldApplyMembershipOnCreate($data)) {
                $this->applyMembershipToRecord($record, (string) $data['membership']);
            }
        });

        $success = $this->shouldApplyMembershipOnCreate($data)
            ? sprintf(
                'Transaction created with %s scholarship applied.',
                strtoupper(trim((string) $data['membership'])),
            )
            : 'Transaction created successfully.';

        return redirect()->route('graduate-ledger.index')->with('success', $success);
    }

    /**
     * Stores a cohort transaction: applies a single template transaction to many students.
     */
    public function cohortStore(StoreGraduateLedgerRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $cohortRows = $data['cohort_rows'] ?? [];

        if (empty($cohortRows) || ! is_array($cohortRows)) {
            return back()->withErrors(['cohort_rows' => 'Please select at least one student for cohort entry.']);
        }

        $academicTermId = $this->resolveAcademicTermId($data);
        $courseId = $data['course_id'];
        $entryType = $data['entry_type'] ?? 'ar';
        $transactionDate = $data['transaction_date'];
        $referenceNumber = $data['reference_number'] ?? null;
        $particulars = $data['particulars'] ?? 'Tuition';
        $status = $data['status'] ?? 'posted';
        $inputBy = auth()->id();

        $created = 0;
        $scholarships = 0;

        DB::transaction(function () use (
            $cohortRows,
            $courseId,
            $academicTermId,
            $entryType,
            $transactionDate,
            $referenceNumber,
            $particulars,
            $status,
            $inputBy,
            &$created,
            &$scholarships
        ): void {
            foreach ($cohortRows as $row) {
                $studentId = (int) $row['student_id'];
                $units = $row['units'] ?? null;
                $rate = $row['rate'] ?? '0.00';
                $amount = $row['amount'];
                $remarks = $row['remarks'] ?? null;
                $membership = filled($row['membership'] ?? null) ? (string) $row['membership'] : null;

                $record = GraduateLedger::create([
                    'student_id' => $studentId,
                    'course_id' => $courseId,
                    'academic_term_id' => $academicTermId,
                    'entry_type' => $entryType,
                    'units' => $units,
                    'transaction_date' => $transactionDate,
                    'reference_number' => $referenceNumber,
                    'particulars' => $particulars,
                    'rate' => $rate,
                    'amount' => $amount,
                    'remarks' => $remarks,
                    'status' => $status,
                    'input_by' => $inputBy,
                ]);

                $created++;

                if ($entryType === 'ar' && filled($membership)) {
                    $this->applyMembershipToRecord($record, $membership);
                    $scholarships++;
                }
            }
        });

        $message = sprintf(
            'Cohort entry posted: %d %s transaction%s created across %d student%s.',
            $created,
            strtoupper($entryType),
            $created === 1 ? '' : 's',
            $created,
            $created === 1 ? '' : 's',
        );

        if ($scholarships > 0) {
            $message .= sprintf(' (%d scholarship adjustment%s applied)', $scholarships, $scholarships === 1 ? '' : 's');
        }

        return redirect()->route('graduate-ledger.index')->with('success', $message);
    }

    /**
     * Resolves the target student, creating a new one when none was selected.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveStudentId(array $data): int
    {
        $studentId = $data['student_id'] ?? null;

        if ($studentId) {
            return (int) $studentId;
        }

        $newStudent = $data['new_student'];
        $studentAttributes = [
            'student_number' => $newStudent['student_number'] ?? null,
            'email' => $newStudent['email'] ?? null,
            'last_name' => $newStudent['last_name'],
            'first_name' => $newStudent['first_name'],
            'middle_name' => $newStudent['middle_name'] ?? null,
        ];

        $student = filled($studentAttributes['student_number'])
            ? Student::create($studentAttributes)
            : Student::firstOrCreate(
                Arr::only($studentAttributes, ['last_name', 'first_name']),
                Arr::except($studentAttributes, ['last_name', 'first_name']),
            );

        return (int) $student->id;
    }

    /**
     * Whether the payload asks for a membership scholarship on a fresh AR.
     *
     * @param  array<string, mixed>  $data
     */
    private function shouldApplyMembershipOnCreate(array $data): bool
    {
        return ($data['entry_type'] ?? null) === 'ar'
            && filled($data['membership'] ?? null)
            && filled($data['discount_amount'] ?? null)
            && (float) $data['discount_amount'] > 0;
    }

    /**
     * Tags an AR with the membership scholarship and books its adjustment twin.
     * Mirrors applyMembership() so both paths produce identical rows.
     */
    private function applyMembershipToRecord(GraduateLedger $record, string $membership): void
    {
        $discountAmount = abs((float) $record->amount);

        if ($discountAmount <= 0) {
            return;
        }

        $record->update([
            'membership' => $membership,
            'discount_amount' => $discountAmount,
        ]);

        GraduateLedger::create([
            'student_id' => $record->student_id,
            'course_id' => $record->course_id,
            'academic_term_id' => $record->academic_term_id,
            'entry_type' => 'adjustment',
            'units' => $record->units,
            'transaction_date' => now()->toDateString(),
            'reference_number' => $record->reference_number,
            'particulars' => $record->particulars ?? 'Tuition',
            'rate' => $record->rate,
            'amount' => $discountAmount,
            'remarks' => $record->remarks,
            'status' => 'ADJUSTMENT',
            'membership' => $membership,
            'input_by' => auth()->id(),
        ]);
    }

    /**
     * Persists a multi-line batch submission in a single transaction.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $items
     */
    private function storeBatchItems(StoreGraduateLedgerRequest $request, array $data, array $items): RedirectResponse
    {
        $studentId = $this->resolveStudentId($data);
        $academicTermId = $this->resolveAcademicTermId($data);
        $created = 0;
        $scholarships = 0;

        DB::transaction(function () use ($items, $studentId, $academicTermId, &$created, &$scholarships): void {
            foreach ($items as $item) {
                $record = GraduateLedger::create($this->ledgerAttributes(
                    $item,
                    $studentId,
                    $academicTermId,
                ));
                $created++;

                if ($this->shouldApplyMembershipOnCreate($item)) {
                    $this->applyMembershipToRecord($record, (string) $item['membership']);
                    $scholarships++;
                }
            }
        });

        $message = trans_choice(
            '{1} :count transaction created.|:count transactions created.',
            $created,
            ['count' => $created],
        );

        if ($scholarships > 0) {
            $message .= sprintf(
                ' %d scholarship adjustment%s added.',
                $scholarships,
                $scholarships === 1 ? '' : 's',
            );
        }

        return redirect()->route('graduate-ledger.index')->with('success', $message);
    }

    // ─── Edit / Update ────────────────────────────────────────────────────────

    /**
     * Shows the edit form for an existing ledger record.
     */
    public function edit(int $id): Response
    {
        $record = GraduateLedger::with(['student', 'course', 'academicTerm', 'inputByUser:id,name'])->findOrFail($id);

        return Inertia::render('graduate-ledger/EditTransaction', [
            'record' => $this->recordForForm($record),
            'students' => $this->studentList(),
            'courses' => $this->courseList(),
            'academicTerms' => $this->academicTermList(),
            'authUserName' => optional(auth()->user())->name ?? '',
        ]);
    }

    /**
     * Updates an existing ledger record.
     */
    public function update(UpdateGraduateLedgerRequest $request, int $id): RedirectResponse
    {
        $record = GraduateLedger::findOrFail($id);
        $data = $request->validated();

        DB::transaction(function () use ($data, $record): void {
            $attributes = $this->ledgerAttributes(
                $data,
                (int) $data['student_id'],
                $this->resolveAcademicTermId($data),
            );

            if ($record->imported_input_by !== null) {
                $attributes['input_by'] = $record->input_by;
            }

            $record->update($attributes);
        });

        return redirect()->route('graduate-ledger.index')->with('success', 'Transaction updated successfully.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveAcademicTermId(array $data): int
    {
        if (! empty($data['academic_term_id'])) {
            return (int) $data['academic_term_id'];
        }

        $term = AcademicTerm::firstOrCreate(
            [
                'school_year' => $data['school_year'],
                'semester' => $data['semester'],
            ],
            []
        );

        return $term->id;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function ledgerAttributes(array $data, int $studentId, int $academicTermId): array
    {
        $attributes = Arr::only($data, [
            'course_id',
            'entry_type',
            'units',
            'transaction_date',
            'reference_number',
            'particulars',
            'rate',
            'amount',
            'remarks',
            'input_by',
        ]);

        $attributes['student_id'] = $studentId;
        $attributes['academic_term_id'] = $academicTermId;
        $attributes['rate'] = $data['rate'] ?? '0.00';
        $attributes['particulars'] = $data['particulars'] ?? 'Tuition';
        $attributes['status'] = $data['status'] ?? 'posted';
        $attributes['input_by'] = $data['input_by'] ?? auth()->id();

        if ($data['entry_type'] === 'ar' && empty($data['amount'])) {
            $attributes['amount'] = round(
                (float) ($data['units'] ?? 0) * (float) $attributes['rate'],
                2,
            );
        }

        return $attributes;
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    /**
     * Deletes a ledger record.
     */
    public function destroy(int $id): RedirectResponse
    {
        GraduateLedger::findOrFail($id)->delete();

        return redirect()->route('graduate-ledger.index')->with('success', 'Transaction deleted successfully.');
    }

    // ─── Import ───────────────────────────────────────────────────────────────

    /**
     * Imports a CSV/Excel spreadsheet into the graduate ledger.
     *
     * Optional preset fields sent from the import modal:
     *   - preset_course_id      : fallback course when a row's course value is blank / unrecognised
     *   - preset_academic_term_id : fallback term when a row's school_year/semester is blank / unrecognised
     */
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
            'preset_course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'preset_academic_term_id' => ['nullable', 'integer', 'exists:academic_terms,id'],
        ]);

        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        $uploadedFile = $request->file('file');
        if (! $uploadedFile instanceof UploadedFile) {
            return back()->with('error', 'The uploaded ledger file is invalid.');
        }

        $presetCourseId = $request->filled('preset_course_id') ? (int) $request->input('preset_course_id') : null;
        $presetTermId = $request->filled('preset_academic_term_id') ? (int) $request->input('preset_academic_term_id') : null;

        $extension = strtolower($uploadedFile->getClientOriginalExtension());
        $imported = 0;
        $skipped = 0;
        $duplicates = 0;
        $warnings = $this->emptyImportWarnings();
        $now = now();

        if ($extension === 'csv') {
            $path = $uploadedFile->getRealPath();
            if ($path === false) {
                return back()->with('error', 'Could not read the uploaded CSV file.');
            }

            return $this->importCsv($path, $imported, $skipped, $warnings, $now, $presetCourseId, $presetTermId, $duplicates);
        }

        return $this->importExcel($uploadedFile, $imported, $skipped, $warnings, $now, $presetCourseId, $presetTermId, $duplicates);
    }

    /**
     * Memory-efficient two-pass CSV importer.
     * Pass 1: stream once to collect distinct students/courses/terms, then bulk-resolve lookup maps.
     * Pass 2: stream again row-by-row, map to FK IDs, bulk insert in chunks.
     *
     * @param  WarningCounts  $warnings
     *
     * @param-out WarningCounts $warnings
     */
    private function importCsv(
        string $path,
        int &$imported,
        int &$skipped,
        array &$warnings,
        CarbonInterface $now,
        ?int $presetCourseId = null,
        ?int $presetTermId = null,
        int &$duplicates = 0,
    ): RedirectResponse {
        // Pass 1: collect distinct values without holding rows in memory
        $handle = fopen($path, 'r');
        if (! is_resource($handle)) {
            return redirect()->route('graduate-ledger.index')
                ->with('error', 'Could not open the uploaded CSV file.');
        }

        $firstRow = fgetcsv($handle);
        $headerRow = $firstRow ? array_map(fn ($h) => Str::slug((string) $h, '_'), $firstRow) : [];
        $columnMap = $this->resolveColumnMap($headerRow);

        $distinctStudents = [];
        $distinctCourses = [];
        $distinctTerms = [];

        $getPass1 = function (array $r, string $key, int $legacyIdx) use ($columnMap): mixed {
            if ($columnMap !== null) {
                return isset($columnMap[$key]) ? ($r[$columnMap[$key]] ?? null) : null;
            }

            return $r[$legacyIdx] ?? null;
        };

        while (($row = fgetcsv($handle)) !== false) {
            $studentInfo = $this->extractStudentIdentity($row, $columnMap);
            if ($studentInfo !== null) {
                $key = $studentInfo['student_number'] ?? $studentInfo['raw_name'];
                if (! isset($distinctStudents[$key])) {
                    $distinctStudents[$key] = $studentInfo;
                } else {
                    if (blank($distinctStudents[$key]['student_number']) && filled($studentInfo['student_number'])) {
                        $distinctStudents[$key]['student_number'] = $studentInfo['student_number'];
                    }
                    if (blank($distinctStudents[$key]['email']) && filled($studentInfo['email'])) {
                        $distinctStudents[$key]['email'] = $studentInfo['email'];
                    }
                }
            }
            $code = trim((string) ($getPass1($row, 'course', 1) ?? ''));
            if ($code !== '') {
                $distinctCourses[$code] = true;
            }
            $sy = AcademicTerm::parseSchoolYear(trim((string) ($getPass1($row, 'school_year', 2) ?? '')));
            $rawSemE = trim((string) ($getPass1($row, 'semester', 4) ?? ''));
            $rawSemD = trim((string) ($getPass1($row, 'semester_short', 3) ?? ''));
            $rawSemester = $rawSemE !== '' ? $rawSemE : $rawSemD;
            $sem = AcademicTerm::normalizeSemester($rawSemester);
            if ($sy !== null && $sem !== null) {
                $distinctTerms["{$sy}|||{$sem}"] = ['school_year' => $sy, 'semester' => $sem];
            }
        }
        fclose($handle);

        // Resolve lookup maps from the distinct sets
        [$studentMap, $courseMap, $termMap] = $this->buildImportLookupMaps($distinctStudents, $distinctCourses, $distinctTerms, $now, $presetCourseId);

        // Pass 2: stream rows again, map to insert array, chunk-insert
        $handle = fopen($path, 'r');
        if (! is_resource($handle)) {
            return redirect()->route('graduate-ledger.index')
                ->with('error', 'Could not reopen the uploaded CSV file.');
        }

        fgetcsv($handle); // skip header

        // Pass 2: single transaction wrapping all chunk inserts
        DB::transaction(function () use (
            $handle, $studentMap, $courseMap, $termMap, &$warnings,
            $presetCourseId, $presetTermId, &$skipped, &$imported, $now, $columnMap
        ): void {
            $insertData = [];

            while (($row = fgetcsv($handle)) !== false) {
                $data = $this->mapImportRowNormalized(
                    $row,
                    $studentMap,
                    $courseMap,
                    $termMap,
                    $warnings,
                    $presetCourseId,
                    $presetTermId,
                    $columnMap,
                );

                if ($data === null) {
                    $skipped++;

                    continue;
                }

                $data['created_at'] = $now;
                $data['updated_at'] = $now;
                $insertData[] = $data;

                if (count($insertData) >= 1000) {
                    GraduateLedger::insert($insertData);
                    $imported += count($insertData);
                    $insertData = [];
                }
            }

            if (! empty($insertData)) {
                GraduateLedger::insert($insertData);
                $imported += count($insertData);
            }
        });
        fclose($handle);

        ActivityLog::recordImport(GraduateLedger::class, $imported, 'Graduate Ledger');

        return redirect()->route('graduate-ledger.index')
            ->with('success', $this->importSummary($imported, $skipped));
    }

    /**
     * Memory-efficient Excel importer (xlsx/xls) using PhpSpreadsheet DataOnly Reader.
     *
     * @param  WarningCounts  $warnings
     *
     * @param-out WarningCounts $warnings
     */
    private function importExcel(
        UploadedFile $uploadedFile,
        int &$imported,
        int &$skipped,
        array &$warnings,
        CarbonInterface $now,
        ?int $presetCourseId = null,
        ?int $presetTermId = null,
        int &$duplicates = 0,
    ): RedirectResponse {
        $path = $uploadedFile->getRealPath();
        if ($path === false) {
            return redirect()->route('graduate-ledger.index')
                ->with('error', 'Could not read the uploaded Excel file.');
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(false); // must be false to evaluate =J*F formulas in col L

        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();

        if ($highestRow <= 1) {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            return redirect()->route('graduate-ledger.index')
                ->with('success', 'No rows found in the uploaded file.');
        }

        $highestCol = $sheet->getHighestColumn();
        $highestColIndex = Coordinate::columnIndexFromString($highestCol);

        $headerRow = [];
        for ($c = 1; $c <= $highestColIndex; $c++) {
            $colLetter = Coordinate::stringFromColumnIndex($c);
            $headerRow[] = Str::slug((string) $sheet->getCell($colLetter.'1')->getValue(), '_');
        }
        $columnMap = $this->resolveColumnMap($headerRow);

        $distinctStudents = [];
        $distinctCourses = [];
        $distinctTerms = [];

        $getExcelRow = function (int $r) use ($sheet, $columnMap, $highestColIndex): array {
            if ($columnMap !== null) {
                $row = [];
                for ($c = 1; $c <= $highestColIndex; $c++) {
                    $colLetter = Coordinate::stringFromColumnIndex($c);
                    $keyIndex = $c - 1;
                    if ($keyIndex === ($columnMap['rate'] ?? -999) || $keyIndex === ($columnMap['amount'] ?? -999)) {
                        $row[$keyIndex] = $this->getCalculatedCellValue($sheet, "{$colLetter}{$r}");
                    } else {
                        $row[$keyIndex] = $sheet->getCell("{$colLetter}{$r}")->getValue();
                    }
                }

                return $row;
            }

            return [
                $sheet->getCell("A{$r}")->getValue(),
                $sheet->getCell("B{$r}")->getValue(),
                $sheet->getCell("C{$r}")->getValue(),
                $sheet->getCell("D{$r}")->getValue(),
                $sheet->getCell("E{$r}")->getValue(),
                $sheet->getCell("F{$r}")->getValue(),
                $sheet->getCell("G{$r}")->getValue(),
                $sheet->getCell("H{$r}")->getValue(),
                $sheet->getCell("I{$r}")->getValue(),
                $this->getCalculatedCellValue($sheet, "J{$r}"),
                $sheet->getCell("K{$r}")->getValue(),
                $this->getCalculatedCellValue($sheet, "L{$r}"),
                $sheet->getCell("M{$r}")->getValue(),
                $sheet->getCell("N{$r}")->getValue(),
            ];
        };

        $getPass1 = function (array $r, string $key, int $legacyIdx) use ($columnMap): mixed {
            if ($columnMap !== null) {
                return isset($columnMap[$key]) ? ($r[$columnMap[$key]] ?? null) : null;
            }

            return $r[$legacyIdx] ?? null;
        };

        // Pass 1: Collect distinct values (rows 2 to highestRow)
        for ($r = 2; $r <= $highestRow; $r++) {
            $rawRow = $getExcelRow($r);
            $studentInfo = $this->extractStudentIdentity($rawRow, $columnMap);
            if ($studentInfo !== null) {
                $key = $studentInfo['student_number'] ?? $studentInfo['raw_name'];
                if (! isset($distinctStudents[$key])) {
                    $distinctStudents[$key] = $studentInfo;
                } else {
                    if (blank($distinctStudents[$key]['student_number']) && filled($studentInfo['student_number'])) {
                        $distinctStudents[$key]['student_number'] = $studentInfo['student_number'];
                    }
                    if (blank($distinctStudents[$key]['email']) && filled($studentInfo['email'])) {
                        $distinctStudents[$key]['email'] = $studentInfo['email'];
                    }
                }
            }
            $code = trim((string) ($getPass1($rawRow, 'course', 1) ?? ''));
            if ($code !== '') {
                $distinctCourses[$code] = true;
            }
            $sy = AcademicTerm::parseSchoolYear(trim((string) ($getPass1($rawRow, 'school_year', 2) ?? '')));
            $rawSemE = trim((string) ($getPass1($rawRow, 'semester', 4) ?? ''));
            $rawSemD = trim((string) ($getPass1($rawRow, 'semester_short', 3) ?? ''));
            $rawSemester = $rawSemE !== '' ? $rawSemE : $rawSemD;
            $sem = AcademicTerm::normalizeSemester($rawSemester);
            if ($sy !== null && $sem !== null) {
                $distinctTerms["{$sy}|||{$sem}"] = ['school_year' => $sy, 'semester' => $sem];
            }
        }

        [$studentMap, $courseMap, $termMap] = $this->buildImportLookupMaps(
            $distinctStudents, $distinctCourses, $distinctTerms, $now, $presetCourseId
        );

        // Pass 2: single transaction wrapping all chunk inserts
        DB::transaction(function () use (
            $getExcelRow, $highestRow, $studentMap, $courseMap, $termMap,
            &$warnings, $presetCourseId, $presetTermId, &$skipped, &$imported, $now, $columnMap
        ): void {
            $insertData = [];

            for ($r = 2; $r <= $highestRow; $r++) {
                $row = $getExcelRow($r);

                $data = $this->mapImportRowNormalized(
                    $row,
                    $studentMap,
                    $courseMap,
                    $termMap,
                    $warnings,
                    $presetCourseId,
                    $presetTermId,
                    $columnMap,
                );

                if ($data === null) {
                    $skipped++;

                    continue;
                }

                $data['created_at'] = $now;
                $data['updated_at'] = $now;
                $insertData[] = $data;

                if (count($insertData) >= 1000) {
                    GraduateLedger::insert($insertData);
                    $imported += count($insertData);
                    $insertData = [];
                }
            }

            if (! empty($insertData)) {
                GraduateLedger::insert($insertData);
                $imported += count($insertData);
            }
        });

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        ActivityLog::recordImport(GraduateLedger::class, $imported, 'Graduate Ledger');

        return redirect()->route('graduate-ledger.index')
            ->with('success', $this->importSummary($imported, $skipped));
    }

    // ─── Print Select / PDF ───────────────────────────────────────────────────

    /**
     * Renders the React UI for choosing a student and previewing their balance.
     */
    public function printSelect(Request $request): Response
    {
        $students = Student::query()
            ->whereHas('graduateLedgers')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'last_name', 'first_name', 'middle_name'])
            ->map(fn ($s) => ['id' => $s->id, 'full_name' => $s->full_name])
            ->values()
            ->all();

        $selectedStudentId = $request->input('student_id') ? (int) $request->input('student_id') : null;

        // Fallback: if a name string or numeric student parameter was passed instead, resolve to ID
        if (! $selectedStudentId && $request->filled('student')) {
            $raw = (string) $request->input('student');
            if (is_numeric($raw)) {
                $selectedStudentId = (int) $raw;
            } else {
                $parsed = Student::parseRawName($raw);
                $selectedStudentId = Student::query()
                    ->where('last_name', $parsed['last_name'])
                    ->where(function ($q) use ($parsed) {
                        $q->where('first_name', $parsed['first_name'])
                            ->orWhere('first_name', 'like', $parsed['first_name'].'%');
                    })
                    ->value('id');

                if (! $selectedStudentId) {
                    $selectedStudentId = Student::query()
                        ->where('student_number', $raw)
                        ->value('id');
                }
            }
        }

        $studentRecords = collect();
        $balanceSummary = [
            'totalCharges' => 0,
            'totalPayments' => 0,
            'totalAdjustments' => 0,
            'outstandingBalance' => 0,
        ];

        if ($selectedStudentId) {
            $studentRecords = GraduateLedger::with(['student', 'course', 'academicTerm', 'inputByUser:id,name'])
                ->where('student_id', $selectedStudentId)
                ->orderBy('id', 'asc')
                ->get();

            $balanceSummary = $this->calculateStudentBalanceNormalized($studentRecords);
        }

        return Inertia::render('graduate-ledger/PrintSelect', [
            'students' => $students,
            'selectedStudent' => $selectedStudentId,
            'records' => $studentRecords->map(fn ($r) => $this->transformRecord($r))->values(),
            'summary' => $balanceSummary,
        ]);
    }

    /**
     * Generates and streams the PDF statement.
     */
    public function generatePdf(Request $request): PdfBuilder
    {
        $validated = $request->validate([
            'student' => ['nullable', 'string'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'school_year' => ['nullable', 'string', 'max:20'],
            'semester' => ['nullable', 'in:First Semester,Second Semester,Summer'],
            'type' => ['nullable', 'string', 'max:100'],
        ]);

        $studentId = $validated['student_id'] ?? null;

        if (! $studentId && filled($validated['student'] ?? null)) {
            $raw = (string) $validated['student'];
            if (is_numeric($raw)) {
                $studentId = (int) $raw;
            } else {
                $parsed = Student::parseRawName($raw);
                $studentId = Student::query()
                    ->where('last_name', $parsed['last_name'])
                    ->where(function ($q) use ($parsed) {
                        $q->where('first_name', $parsed['first_name'])
                            ->orWhere('first_name', 'like', $parsed['first_name'].'%');
                    })
                    ->value('id');

                if (! $studentId) {
                    $studentId = Student::query()
                        ->where('student_number', $raw)
                        ->value('id');
                }
            }
        }

        abort_if(! $studentId, 404, 'Student not found.');
        $student = Student::query()->whereKey($studentId)->firstOrFail();

        $rawRecords = GraduateLedger::query()
            ->with(['student', 'course', 'academicTerm', 'inputByUser:id,name'])
            ->where('student_id', $studentId)
            ->when(
                $validated['school_year'] ?? null,
                fn ($query, $schoolYear) => $query->whereHas(
                    'academicTerm',
                    fn ($termQuery) => $termQuery->where('school_year', $schoolYear),
                ),
            )
            ->when(
                $validated['semester'] ?? null,
                fn ($query, $semester) => $query->whereHas(
                    'academicTerm',
                    fn ($termQuery) => $termQuery->where('semester', $semester),
                ),
            )
            ->orderBy('id', 'asc')
            ->get();

        if (filled($validated['type'] ?? null)) {
            $rawRecords = $rawRecords
                ->filter(fn (GraduateLedger $record) => $this->transformRecord($record)['arPayment'] === $validated['type'])
                ->values();
        }

        $summary = $this->calculateStudentBalanceNormalized($rawRecords);
        $studentName = $student->full_name;
        $records = $rawRecords->map(fn ($r) => (object) $this->transformRecord($r));

        $pdf = Pdf::view('pdf.student-ledger-statement', [
            'student' => $student,
            'studentName' => $studentName,
            'records' => $records,
            'summary' => $summary,
            'generatedAt' => now()->format('Y-m-d'),
        ])
            ->driver('browsershot')
            ->withBrowsershot(function (Browsershot $browsershot): void {
                $this->configureBrowsershot($browsershot);
            })
            ->format('a4');
        // ->setPaper('a4', 'portrait')
        // ->setOption('defaultFont', 'DejaVu Sans')
        // ->setOption('isHtml5ParserEnabled', true)
        // ->setOption('isRemoteEnabled', true);

        $filename = 'Statement_of_Account_'.str_replace(['/', '\\', ' '], '_', $studentName).'.pdf';

        return $pdf;
    }

    // ─── Bulk Email ─────────────────────────────────────────────────────────────

    /**
     * Returns all students matching the current filters (with email and
     * outstanding balance) for the email modal's recipient list.
     */
    public function emailRecipients(Request $request): JsonResponse
    {
        $query = $this->buildFilteredQuery($request);

        $studentIds = (clone $query)
            ->whereNotNull('student_id')
            ->reorder()
            ->distinct()
            ->pluck('student_id')
            ->filter()->unique()->values()->all();

        if (empty($studentIds)) {
            return response()->json([]);
        }

        $balances = DB::table('graduate_ledgers')
            ->whereIn('student_id', $studentIds)
            ->select('student_id')
            ->selectRaw("SUM(CASE WHEN LOWER(TRIM(entry_type)) = 'ar' THEN amount WHEN LOWER(TRIM(entry_type)) IN ('payment','adjustment') THEN -amount ELSE 0 END) as balance")
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        $students = Student::whereIn('id', $studentIds)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('last_name')
            ->get();

        return response()->json(
            $students->map(function (Student $student) use ($balances): array {
                $balance = isset($balances[$student->id]) ? (float) $balances[$student->id]->balance : 0.0;

                return [
                    'id' => $student->id,
                    'student_number' => $student->student_number,
                    'full_name' => $student->full_name,
                    'email' => $student->email,
                    'balance' => $balance,
                    'balance_status' => $balance > 0 ? 'outstanding' : 'settled',
                ];
            })->values()
        );
    }

    /**
     * Sends the statement-of-account PDF to students matching the
     * current filters (or a specific subset) who have an email address.
     */
    public function sendBulkEmail(Request $request): RedirectResponse
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        $validated = $request->validate([
            'school_year' => ['nullable', 'string', 'max:20'],
            'semester' => ['nullable', 'in:First Semester,Second Semester,Summer'],
            'subject' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'exam_period' => ['nullable', 'in:Midterm,Final'],
            'exam_deadline' => ['nullable', 'date'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer', 'exists:students,id'],
        ]);

        $query = $this->buildFilteredQuery($request);

        $studentIds = (clone $query)
            ->whereNotNull('student_id')
            ->reorder()
            ->distinct()
            ->pluck('student_id')
            ->filter()->unique()->values()->all();

        $specificIds = $request->input('student_ids');
        if (is_array($specificIds) && ! empty($specificIds)) {
            $studentIds = array_intersect($studentIds, array_map('intval', $specificIds));
        }

        $students = Student::whereIn('id', $studentIds)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('last_name')
            ->get();

        $sent = 0;
        $skipped = 0;

        foreach ($students as $student) {
            if (! $student->email) {
                $skipped++;

                continue;
            }

            $pdfContent = $this->generateStudentPdfContent(
                (int) $student->id,
                $validated['school_year'] ?? null,
                $validated['semester'] ?? null,
            );

            Mail::to($student->email)->send(
                new GraduateLedgerStatementMail(
                    $student,
                    $pdfContent,
                    $validated['subject'] ?? null,
                    $validated['note'] ?? null,
                    $validated['exam_period'] ?? null,
                    $validated['exam_deadline'] ?? null,
                )
            );

            try {
                $actor = auth()->user();
                ActivityLog::create([
                    'actor_id' => $actor?->getAuthIdentifier(),
                    'actor_name' => $actor?->name,
                    'actor_role' => $actor?->role,
                    'action' => 'email.sent',
                    'target_id' => null,
                    'subject_type' => null,
                    'subject_id' => null,
                    'description' => "Email sent to {$student->email} with subject \"".($validated['subject'] ?? 'Statement of Account').'".',
                    'meta' => ['to' => $student->email, 'subject' => $validated['subject'] ?? 'Statement of Account'],
                ]);
            } catch (\Throwable $e) {
            }

            $sent++;
        }

        return back()->with('success', "Emailed SOA to {$sent} student(s).".($skipped > 0 ? " {$skipped} student(s) skipped (no email)." : ''));
    }

    /**
     * Generates raw PDF content bytes for a single graduate student's
     * statement of account, reusing the same view and data as generatePdf().
     */
    private function generateStudentPdfContent(int $studentId, ?string $schoolYear, ?string $semester): string
    {
        $student = Student::query()->whereKey($studentId)->firstOrFail();

        $rawRecords = GraduateLedger::query()
            ->with(['student', 'course', 'academicTerm', 'inputByUser:id,name'])
            ->where('student_id', $studentId)
            ->when(
                $schoolYear,
                fn ($query, $sy) => $query->whereHas(
                    'academicTerm',
                    fn ($termQuery) => $termQuery->where('school_year', $sy),
                ),
            )
            ->when(
                $semester,
                fn ($query, $sem) => $query->whereHas(
                    'academicTerm',
                    fn ($termQuery) => $termQuery->where('semester', $sem),
                ),
            )
            ->orderBy('id', 'asc')
            ->get();

        $summary = $this->calculateStudentBalanceNormalized($rawRecords);
        $studentName = $student->full_name;
        $records = $rawRecords->map(fn ($r) => (object) $this->transformRecord($r));

        // Use Browsershot (Chrome) like generatePdf() does, so the PDF
        // matches the print statement exactly — including all Tailwind styles.
        // dompdf cannot parse the built Vite stylesheet.
        return Pdf::view('pdf.student-ledger-statement', [
            'student' => $student,
            'studentName' => $studentName,
            'records' => $records,
            'summary' => $summary,
            'generatedAt' => now()->format('Y-m-d'),
        ])
            ->driver('browsershot')
            ->withBrowsershot(function (Browsershot $browsershot): void {
                $this->configureBrowsershot($browsershot);
            })
            ->format('a4')
            ->generatePdfContent();
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    /**
     * Applies the shared Browsershot hardening used by the Chrome-rendered
     * statements. The statement templates are styled entirely with Tailwind via
     *
     * @vite, so they must go through Chrome - dompdf cannot parse the built
     * stylesheet and renders them unstyled.
     */
    private function configureBrowsershot(Browsershot $browsershot): void
    {
        // Full Chrome's new headless mode retains CSS (unlike the
        // chrome-headless-shell build) while avoiding the Windows shell IO.read
        // failure, and gets explicit timeouts so slow renders fail loudly instead
        // of hanging until the PHP execution limit.
        $browsershot
            ->newHeadless()
            ->timeout(300)
            ->setOption('protocolTimeout', 300_000);
    }

    /**
     * Transforms a GraduateLedger row to the frontend LedgerRecord shape.
     * Index.tsx consumes this shape.
     *
     * @param  array<string, float>  $termBalances
     * @return array<string, mixed>
     */
    private function transformRecord(GraduateLedger $r, array $termBalances = []): array
    {
        $name = $r->student->full_name ?? '';
        $courseCode = $r->course->code ?? '';
        $schoolYear = $r->academicTerm->school_year ?? '';
        $semester = $r->academicTerm->semester ?? '';
        $entryTypeLower = strtolower(trim((string) $r->entry_type));
        $arPayment = $this->entryTypeToLabel($entryTypeLower);

        // Append membership to adjustment label when present
        if ($entryTypeLower === 'adjustment' && filled($r->membership)) {
            $arPayment = 'Adjustment ('.strtoupper(trim($r->membership)).')';
        }

        // Flag the AR that the membership scholarship was applied to (e.g. AR(NAPU))
        if ($entryTypeLower === 'ar' && filled($r->membership)) {
            $arPayment = 'AR('.strtoupper(trim($r->membership)).')';
        }

        $studentId = $r->student_id;
        $termId = $r->academic_term_id;
        $remark = 'Settled';

        if ($studentId && $termId) {
            $key = "{$studentId}_{$termId}";
            if (array_key_exists($key, $termBalances)) {
                $remark = $termBalances[$key] > 0 ? 'Outstanding' : 'Settled';
            } else {
                $balance = (float) DB::table('graduate_ledgers')
                    ->where('student_id', $studentId)
                    ->where('academic_term_id', $termId)
                    ->sum(DB::raw("CASE WHEN LOWER(TRIM(entry_type)) = 'ar' THEN amount WHEN LOWER(TRIM(entry_type)) IN ('payment', 'adjustment') THEN -amount ELSE 0 END"));
                $remark = $balance > 0 ? 'Outstanding' : 'Settled';
            }
        } elseif ($entryTypeLower === 'ar' && (float) $r->amount > 0) {
            $remark = 'Outstanding';
        }

        // Resolve a clickable link to the Order of Payment for payment entries
        $orLink = null;
        if ($entryTypeLower === 'payment' && filled($r->reference_number)) {
            $staffInput = StaffInput::where('or_no', $r->reference_number)
                ->select('id', 'form_input_id')
                ->first();
            if ($staffInput) {
                $orLink = route('staff.requests.show', $staffInput->form_input_id);
            }
        }

        return [
            'id' => $r->id,
            'studentId' => $r->student_id,
            'studentNumber' => $r->student?->student_number,
            'name' => $name,
            'course' => $courseCode,
            'schoolYear' => $schoolYear,
            'semester' => $semester,
            'units' => (float) ($r->units ?? 0),
            'transactionDate' => $r->transaction_date,
            'referenceNo' => $r->reference_or_jev_number,
            'particulars' => $r->particulars,
            'tuitionPerUnitOrFeePerSemester' => (float) ($r->tuition_per_unit_or_misc ?? 0),
            'arPayment' => $arPayment,
            'amount' => $this->cleanAmount($r->amount),
            'remark' => $remark,
            'inputBy' => $r->inputByDisplay(),
            'membership' => $r->membership,
            'discountAmount' => (float) ($r->discount_amount ?? 0),
            'orLink' => $orLink,
        ];
    }

    /**
     * Batch calculate net balance for student + academic term pairs on the current page.
     *
     * @param  Collection<int, GraduateLedger>  $records
     * @return array<string, float> map of "studentId_termId" => netBalance
     */
    private function calculateTermBalancesForRecords(Collection $records): array
    {
        $studentIds = $records->pluck('student_id')->filter()->unique()->values()->all();
        $termIds = $records->pluck('academic_term_id')->filter()->unique()->values()->all();

        if (empty($studentIds) || empty($termIds)) {
            return [];
        }

        $balances = DB::table('graduate_ledgers')
            ->select('student_id', 'academic_term_id')
            ->selectRaw("SUM(CASE WHEN LOWER(TRIM(entry_type)) = 'ar' THEN amount WHEN LOWER(TRIM(entry_type)) IN ('payment', 'adjustment') THEN -amount ELSE 0 END) as net_balance")
            ->whereIn('student_id', $studentIds)
            ->whereIn('academic_term_id', $termIds)
            ->groupBy('student_id', 'academic_term_id')
            ->get();

        $map = [];
        foreach ($balances as $b) {
            $map["{$b->student_id}_{$b->academic_term_id}"] = (float) $b->net_balance;
        }

        return $map;
    }

    /**
     * Returns the record shape expected by the Add/Edit form (ID-based fields).
     */
    /** @return array<string, mixed> */
    private function recordForForm(GraduateLedger $r): array
    {
        return [
            'id' => $r->id,
            'student_id' => $r->student_id,
            'course_id' => $r->course_id,
            'academic_term_id' => $r->academic_term_id,
            'school_year' => $r->academicTerm->school_year ?? '',
            'semester' => $r->academicTerm->semester ?? 'First Semester',
            'entry_type' => $r->entry_type ?? 'ar',
            'units' => $r->units,
            'transaction_date' => $r->transaction_date ? (string) $r->transaction_date : '',
            'reference_or_jev_number' => $r->reference_or_jev_number ?? '',
            'particulars' => $r->particulars ?? 'Tuition',
            'tuition_per_unit_or_misc' => $r->tuition_per_unit_or_misc,
            'amount' => $r->amount,
            'remarks' => $r->remarks ?? '',
            'input_by' => $r->inputByDisplay(),
            'is_imported' => $r->imported_input_by !== null,
        ];
    }

    /**
     * List of students for the form picker.
     * Returns [{id, student_number, last_name, first_name, middle_name}].
     */
    /** @return list<array{id: int, student_number: string|null, last_name: string, first_name: string, middle_name: string|null, last_course_id: int|null}> */
    private function studentList(): array
    {
        $latestCourses = DB::table((new GraduateLedger)->getTable())
            ->select('student_id', DB::raw('MAX(course_id) as last_course_id'))
            ->whereNotNull('student_id')
            ->whereNotNull('course_id')
            ->groupBy('student_id')
            ->pluck('last_course_id', 'student_id')
            ->toArray();

        return array_values(Student::orderBy('last_name')
            ->get(['id', 'student_number', 'last_name', 'first_name', 'middle_name'])
            ->map(fn (Student $s): array => [
                'id' => $s->id,
                'student_number' => $s->student_number,
                'last_name' => $s->last_name,
                'first_name' => $s->first_name,
                'middle_name' => $s->middle_name,
                'last_course_id' => isset($latestCourses[$s->id]) ? (int) $latestCourses[$s->id] : null,
            ])
            ->all());
    }

    /**
     * List of courses for the form picker.
     * Returns [{id, code}].
     */
    /** @return list<array{id: int, code: string}> */
    private function courseList(): array
    {
        return array_values(Course::where('course_college', 'Graduate School')->orderBy('course_code')
            ->get(['id', 'course_code'])
            ->map(fn (Course $course): array => ['id' => $course->id, 'code' => $course->code])
            ->all());
    }

    /**
     * List of academic terms for the form picker.
     */
    /** @return list<array{id: int, school_year: string, semester: string}> */
    private function academicTermList(): array
    {
        return array_values(AcademicTerm::orderBy('school_year', 'desc')
            ->orderByRaw("CASE semester WHEN 'First Semester' THEN 1 WHEN 'Second Semester' THEN 2 ELSE 3 END")
            ->get(['id', 'school_year', 'semester'])
            ->map(fn (AcademicTerm $term): array => [
                'id' => $term->id,
                'school_year' => $term->school_year,
                'semester' => $term->semester,
            ])
            ->all());
    }

    /**
     * Pre-build student/course/term lookup maps from pre-collected distinct value arrays.
     * Mirrors the Law importer: courses and terms get unassigned fallbacks so imports
     * never fail on missing codes — only genuinely blank rows are skipped.
     * When $presetCourseId is provided, unknown courses use the preset instead of auto-creating.
     *
     * @param  array<string, mixed>  $distinctStudents
     * @param  array<string, true>  $distinctCourses  keys are raw trimmed course strings from the file
     * @param  array<string, array{school_year: string, semester: string}>  $distinctTerms
     * @return array{array<string, int>, array<string, int>, array<string, int>}
     */
    private function buildImportLookupMaps(
        array $distinctStudents,
        array $distinctCourses,
        array $distinctTerms,
        CarbonInterface $now,
        ?int $presetCourseId = null,
    ): array {
        // 1. Bulk resolve students
        $existingStudents = Student::query()->get(['id', 'student_number', 'email', 'last_name', 'first_name', 'middle_name']);
        $studentsByName = [];
        $studentsByNumber = [];
        $existingEmails = [];
        $existingStudentNumbers = [];

        foreach ($existingStudents as $student) {
            $id = (int) $student->id;
            $existingEmails[$id] = $student->email;
            $existingStudentNumbers[$id] = $student->student_number;

            if ($student->student_number !== null && $student->student_number !== '') {
                $studentsByNumber[$student->student_number] = $id;
            }

            $kFull = $this->studentImportKey($student->last_name, $student->first_name, $student->middle_name);
            $studentsByName[$kFull] = $id;

            if ($student->middle_name) {
                $kInitial = $this->studentImportKey($student->last_name, $student->first_name, substr($student->middle_name, 0, 1));
                $studentsByName[$kInitial] = $id;
            }

            $kNoMid = $this->studentImportKey($student->last_name, $student->first_name, null);
            if (! isset($studentsByName[$kNoMid])) {
                $studentsByName[$kNoMid] = $id;
            }
        }

        $studentMap = [];
        $newStudents = [];
        $studentNumberUpdates = [];
        $emailUpdates = [];

        foreach ($distinctStudents as $key => $studentInfo) {
            if (is_array($studentInfo)) {
                $rawName = (string) ($studentInfo['raw_name'] ?? $key);
                $studentNumber = $studentInfo['student_number'] ?? null;
                $email = $studentInfo['email'] ?? null;
                $parsed = [
                    'last_name' => (string) ($studentInfo['last_name'] ?? ''),
                    'first_name' => (string) ($studentInfo['first_name'] ?? ''),
                    'middle_name' => $studentInfo['middle_name'] ?? null,
                ];
            } else {
                $rawName = (string) $key;
                $studentNumber = null;
                $email = null;
                $parsed = Student::parseRawName($rawName);
            }

            $kFull = $this->studentImportKey($parsed['last_name'], $parsed['first_name'], $parsed['middle_name']);
            $kInitial = $parsed['middle_name'] ? $this->studentImportKey($parsed['last_name'], $parsed['first_name'], substr($parsed['middle_name'], 0, 1)) : $kFull;
            $kNoMid = $this->studentImportKey($parsed['last_name'], $parsed['first_name'], null);

            $matchedId = ($studentNumber !== null ? ($studentsByNumber[$studentNumber] ?? null) : null)
                ?? $studentsByName[$kFull]
                ?? $studentsByName[$kInitial]
                ?? $studentsByName[$kNoMid]
                ?? null;

            if ($matchedId !== null) {
                $studentMap[$rawName] = $matchedId;
                if ($studentNumber !== null && $studentNumber !== '') {
                    $studentMap['num:'.$studentNumber] = $matchedId;
                }

                if ($studentNumber !== null && blank($existingStudentNumbers[$matchedId] ?? null) && ! isset($studentNumberUpdates[$matchedId])) {
                    $studentNumberUpdates[$matchedId] = $studentNumber;
                    $studentsByNumber[$studentNumber] = $matchedId;
                    $existingStudentNumbers[$matchedId] = $studentNumber;
                }

                if ($email !== null && blank($existingEmails[$matchedId] ?? null) && ! isset($emailUpdates[$matchedId])) {
                    $emailUpdates[$matchedId] = $email;
                    $existingEmails[$matchedId] = $email;
                }
            } else {
                $newStudents[] = array_merge($parsed, [
                    'student_number' => $studentNumber,
                    'email' => $email,
                    'raw_name' => $rawName,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // Bulk-update student_number — one query instead of N
        if (! empty($studentNumberUpdates)) {
            $ids = array_keys($studentNumberUpdates);
            $cases = '';
            $bindings = [];
            foreach ($studentNumberUpdates as $id => $sNum) {
                $cases .= 'WHEN id = ? THEN ? ';
                $bindings[] = $id;
                $bindings[] = $sNum;
            }
            DB::statement(
                'UPDATE students SET student_number = CASE '.$cases.' ELSE student_number END, updated_at = ?'
                .' WHERE id IN ('.implode(',', array_fill(0, count($ids), '?')).')'
                ." AND (student_number IS NULL OR student_number = '')",
                array_merge($bindings, [$now, ...$ids])
            );
        }

        // Bulk-update email — one query instead of N
        if (! empty($emailUpdates)) {
            $ids = array_keys($emailUpdates);
            $cases = '';
            $bindings = [];
            foreach ($emailUpdates as $id => $eMail) {
                $cases .= 'WHEN id = ? THEN ? ';
                $bindings[] = $id;
                $bindings[] = $eMail;
            }
            DB::statement(
                'UPDATE students SET email = CASE '.$cases.' ELSE email END, updated_at = ?'
                .' WHERE id IN ('.implode(',', array_fill(0, count($ids), '?')).')'
                ." AND (email IS NULL OR email = '')",
                array_merge($bindings, [$now, ...$ids])
            );
        }

        if (! empty($newStudents)) {
            $insertRows = array_map(function ($row) {
                unset($row['raw_name']);

                return $row;
            }, $newStudents);

            foreach (array_chunk($insertRows, 500) as $chunk) {
                Student::insert($chunk);
            }

            // Fetch ONLY newly inserted students by last name — merge into existing maps,
            // no full-table scan.
            $newLastNames = array_unique(array_column($newStudents, 'last_name'));
            $freshStudents = Student::query()
                ->whereIn('last_name', $newLastNames)
                ->get(['id', 'student_number', 'last_name', 'first_name', 'middle_name']);

            foreach ($freshStudents as $student) {
                $id = (int) $student->id;
                if ($student->student_number !== null && $student->student_number !== '') {
                    $studentsByNumber[$student->student_number] = $id;
                }
                $kFull = $this->studentImportKey($student->last_name, $student->first_name, $student->middle_name);
                $studentsByName[$kFull] = $id;
                if ($student->middle_name) {
                    $kInitial = $this->studentImportKey($student->last_name, $student->first_name, substr($student->middle_name, 0, 1));
                    $studentsByName[$kInitial] = $id;
                }
                $kNoMid = $this->studentImportKey($student->last_name, $student->first_name, null);
                if (! isset($studentsByName[$kNoMid])) {
                    $studentsByName[$kNoMid] = $id;
                }
            }

            // Populate studentMap for new students only — existing matches already handled above.
            foreach ($newStudents as $ns) {
                $rawName = (string) $ns['raw_name'];
                $studentNumber = $ns['student_number'] ?? null;
                $kFull = $this->studentImportKey($ns['last_name'], $ns['first_name'], $ns['middle_name'] ?? null);
                $kInitial = ($ns['middle_name'] ?? null)
                    ? $this->studentImportKey($ns['last_name'], $ns['first_name'], substr($ns['middle_name'], 0, 1))
                    : $kFull;
                $kNoMid = $this->studentImportKey($ns['last_name'], $ns['first_name'], null);

                $matchedId = ($studentNumber !== null ? ($studentsByNumber[$studentNumber] ?? null) : null)
                    ?? $studentsByName[$kFull]
                    ?? $studentsByName[$kInitial]
                    ?? $studentsByName[$kNoMid]
                    ?? null;

                if ($matchedId !== null) {
                    $studentMap[$rawName] = $matchedId;
                    if ($studentNumber !== null && $studentNumber !== '') {
                        $studentMap['num:'.$studentNumber] = $matchedId;
                    }
                }
            }
        }

        // 2. Courses — create graduate fallback; auto-create unrecognized codes only when no preset is given.
        $courseMap = Course::query()
            ->where('course_college', 'Graduate School')
            ->get(['id', 'course_code'])
            ->mapWithKeys(fn (Course $course): array => [Str::lower(trim($course->course_code)) => (int) $course->id])
            ->all();

        $unassignedCourse = Course::firstOrCreate(
            ['course_code' => Course::GRADUATE_FALLBACK_CODE],
            ['course_college' => 'Graduate School', 'course_desc' => 'Unassigned / Pending Graduate Course Assignment', 'created_at' => $now, 'updated_at' => $now]
        );
        $courseMap['__DEFAULT__'] = (int) $unassignedCourse->id;

        $courseLookup = [];
        $newCourses = [];
        foreach (array_keys($distinctCourses) as $rawCode) {
            $code = trim($rawCode);
            if ($code === '') {
                continue;
            }
            $lookupKey = Str::lower($code);
            if (isset($courseMap[$lookupKey])) {
                $courseLookup[$lookupKey] = $courseMap[$lookupKey];
            } elseif ($presetCourseId === null) {
                // MySQL's unique course-code index is case-insensitive, so case-only
                // variants must share one record. Punctuation and spacing remain raw.
                $newCourses[$lookupKey] = [
                    'course_code' => $code,
                    'course_desc' => $code,
                    'course_college' => 'Graduate School',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        if (! empty($newCourses)) {
            foreach (array_chunk(array_values($newCourses), 500) as $chunk) {
                // Let the database collapse values its case-insensitive unique
                // index considers equivalent (for example ph.D. and PH.D.).
                Course::insertOrIgnore($chunk);
            }
            $courseMap = Course::query()
                ->where('course_college', 'Graduate School')
                ->get(['id', 'course_code'])
                ->mapWithKeys(fn (Course $course): array => [Str::lower(trim($course->course_code)) => (int) $course->id])
                ->all();
            $courseMap['__DEFAULT__'] = (int) $unassignedCourse->id;
            // Re-build courseLookup with refreshed map
            $courseLookup = [];
            foreach (array_keys($distinctCourses) as $rawCode) {
                $code = trim($rawCode);
                if ($code === '') {
                    continue;
                }
                $lookupKey = Str::lower($code);
                if (isset($courseMap[$lookupKey])) {
                    $courseLookup[$lookupKey] = $courseMap[$lookupKey];
                }
            }
        }

        // 3. Terms — create UNASSIGNED fallback; auto-create missing terms.
        $termsInDb = AcademicTerm::get(['id', 'school_year', 'semester'])->toArray();
        $termMap = [];
        foreach ($termsInDb as $t) {
            $termMap["{$t['school_year']}|||{$t['semester']}"] = (int) $t['id'];
        }

        $unassignedTerm = AcademicTerm::firstOrCreate(
            ['school_year' => 'Unassigned', 'semester' => 'First Semester'],
            ['created_at' => $now, 'updated_at' => $now]
        );
        $termMap['__DEFAULT__'] = (int) $unassignedTerm->id;

        $newTerms = [];
        foreach ($distinctTerms as $key => $pair) {
            if (! isset($termMap[$key])) {
                $newTerms[] = [
                    'school_year' => $pair['school_year'],
                    'semester' => $pair['semester'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        if (! empty($newTerms)) {
            AcademicTerm::insert($newTerms);
            $termsInDb = AcademicTerm::get(['id', 'school_year', 'semester'])->toArray();
            $termMap = [];
            foreach ($termsInDb as $t) {
                $termMap["{$t['school_year']}|||{$t['semester']}"] = (int) $t['id'];
            }
            $termMap['__DEFAULT__'] = (int) $unassignedTerm->id;
        }

        return [$studentMap, $courseLookup, $termMap];
    }

    /**
     * Maps a positional row to normalized (FK-based) DB columns.
     * Mirrors Law: uses unassigned fallbacks for course/term; never returns null
     * for resolvable rows — only genuinely blank rows return null.
     *
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $studentMap
     * @param  array<string, int>  $courseMap
     * @param  array<string, int>  $termMap
     * @param  WarningCounts  $warnings
     *
     * @param-out WarningCounts $warnings
     *
     * @return array<string, mixed>|null
     */
    private function mapImportRowNormalized(
        array $row,
        array $studentMap,
        array $courseMap,
        array $termMap,
        array &$warnings,
        ?int $presetCourseId = null,
        ?int $presetTermId = null,
        ?array $columnMap = null,
    ): ?array {
        $studentInfo = $this->extractStudentIdentity($row, $columnMap);
        if ($studentInfo === null) {
            return null;
        }

        $rawName = $studentInfo['raw_name'];
        $rawStudentNumber = $studentInfo['student_number'];

        $get = function (string $key, int $legacyIdx) use ($row, $columnMap): mixed {
            if ($columnMap !== null) {
                return isset($columnMap[$key]) ? ($row[$columnMap[$key]] ?? null) : null;
            }

            return $row[$legacyIdx] ?? null;
        };

        $rawAmount = (string) ($get('amount', 11) ?? '0');
        $rawTuition = (string) ($get('rate', 9) ?? '0');

        // ── Course resolution — fallback to UNASSIGNED_GRAD ──────────────────
        $rawCode = trim((string) ($get('course', 1) ?? ''));
        $courseId = $rawCode !== '' ? ($courseMap[Str::lower($rawCode)] ?? null) : null;
        if ($courseId === null) {
            $courseId = $presetCourseId ?? ($courseMap['__DEFAULT__'] ?? null);
        }

        // ── Term resolution — fallback to UNASSIGNED ─────────────────────────
        $rawSY = trim((string) ($get('school_year', 2) ?? ''));
        $sy = AcademicTerm::parseSchoolYear($rawSY);
        $rawSemE = trim((string) ($get('semester', 4) ?? ''));
        $rawSemD = trim((string) ($get('semester_short', 3) ?? ''));
        $rawSem = $rawSemE !== '' ? $rawSemE : $rawSemD;
        $sem = AcademicTerm::normalizeSemester($rawSem);
        $academicTermId = ($sy !== null && $sem !== null)
            ? ($termMap["{$sy}|||{$sem}"] ?? null)
            : null;
        if ($academicTermId === null) {
            $academicTermId = $presetTermId ?? ($termMap['__DEFAULT__'] ?? null);
        }

        // ── Amount ───────────────────────────────────────────────────────────
        $cleanedAmount = $this->cleanAmount($rawAmount);
        $cleanedRate = $this->cleanAmount($rawTuition);
        $rawUnits = $get('units', 5);
        $units = is_numeric($rawUnits) ? (float) $rawUnits : null;

        // ── Classify entry type ───────────────────────────────────────────────
        $rawType = (string) ($get('entry_type', 10) ?? '');
        $classification = $this->importClassifier->classify(
            $rawType,
            $get('amount', 11) ?? null,
        );

        // ── Student lookup ───────────────────────────────────────────────────
        $studentId = ($rawStudentNumber !== null ? ($studentMap['num:'.$rawStudentNumber] ?? null) : null)
            ?? ($studentMap[$rawName] ?? null);

        if ($studentId === null) {
            // Student was not created (entirely blank name parsed) — skip
            return null;
        }

        $particulars = trim((string) ($get('particulars', 8) ?? ''));
        if ($particulars === '') {
            $particulars = 'Tuition';
        }

        return [
            'student_id' => $studentId,
            'course_id' => $courseId,
            'academic_term_id' => $academicTermId,
            'units' => $units,
            'transaction_date' => $this->normalizeDate($get('date', 6) ?? null),
            'reference_number' => trim((string) ($get('reference', 7) ?? '')),
            'particulars' => $particulars,
            'rate' => $cleanedRate,
            'entry_type' => $classification['entry_type'],
            'amount' => abs($cleanedAmount),
            'remarks' => $this->cleanRemarks($get('remarks', 12) ?? null),
            'status' => 'posted',
            'input_by' => auth()->id(),
            'imported_input_by' => $this->normalizeImportedInputBy($get('input_by', 13)),
        ];
    }

    private function studentImportKey(?string $lastName, ?string $firstName, ?string $middleName): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', implode('|', [
            $lastName,
            $firstName,
            Student::normalizeMiddleInitial($middleName),
        ])) ?? '');
    }

    /** @return array{courses: list<string>, schoolYears: list<string>, semesters: list<string>} */
    private function getFilterOptions(): array
    {
        $currentYear = (int) date('Y');
        $defaultSchoolYears = [];
        for ($i = $currentYear - 5; $i <= $currentYear + 3; $i++) {
            $defaultSchoolYears[] = $i.'-'.($i + 1);
        }

        $schoolYears = AcademicTerm::distinct()
            ->orderBy('school_year', 'desc')
            ->pluck('school_year')
            ->filter()
            ->map(fn (mixed $year): string => (string) $year)
            ->values()
            ->all();

        return [
            'courses' => array_values(Course::where('course_college', 'Graduate School')->orderBy('course_code')->pluck('course_code')->filter()->map(fn (mixed $code): string => (string) $code)->all()),
            'schoolYears' => array_values($schoolYears ?: $defaultSchoolYears),
            'semesters' => ['First Semester', 'Second Semester', 'Summer'],
            'types' => ['AR', 'Payment', 'Adjustment'],
        ];
    }

    // ─── Small utilities ──────────────────────────────────────────────────────

    /** @return WarningCounts */
    /**
     * @param  array<int, string>  $headerRow
     * @return array<string, int>|null
     */
    private function resolveColumnMap(array $headerRow): ?array
    {
        $map = [];

        $studentNumberIdx = $this->headerIndex($headerRow, ['student_id_number', 'student_number', 'student_no', 'student_id_no', 'student_id']);
        if ($studentNumberIdx !== null) {
            $map['student_number'] = $studentNumberIdx - 1;
        }

        $lastIdx = $this->headerIndex($headerRow, ['last_name', 'lastname', 'surname']);
        if ($lastIdx !== null) {
            $map['last_name'] = $lastIdx - 1;
        }

        $firstIdx = $this->headerIndex($headerRow, ['first_name', 'firstname', 'given_name']);
        if ($firstIdx !== null) {
            $map['first_name'] = $firstIdx - 1;
        }

        $middleIdx = $this->headerIndex($headerRow, ['middle_name', 'middlename', 'middle_initial', 'mi', 'm_i']);
        if ($middleIdx !== null) {
            $map['middle_name'] = $middleIdx - 1;
        }

        $nameIdx = $this->headerIndex($headerRow, ['full_name', 'student_name', 'name', 'student', 'ff', 'name_last_name_first_name_m_i', 'name_last_name_first_name_mi']);
        if ($nameIdx !== null) {
            $map['name'] = $nameIdx - 1;
        }

        $emailIdx = $this->headerIndex($headerRow, ['email', 'email_address', 'student_email']);
        if ($emailIdx !== null) {
            $map['email'] = $emailIdx - 1;
        }

        $courseIdx = $this->headerIndex($headerRow, ['course', 'program']);
        if ($courseIdx !== null) {
            $map['course'] = $courseIdx - 1;
        }

        $syIdx = $this->headerIndex($headerRow, ['school_year', 'academic_year', 'sy']);
        if ($syIdx !== null) {
            $map['school_year'] = $syIdx - 1;
        }

        $semIdx = $this->headerIndex($headerRow, ['semester', 'term', 'semester_or_summer', 'semester_summer']);
        if ($semIdx !== null) {
            $map['semester'] = $semIdx - 1;
        }

        $semShortIdx = $this->headerIndex($headerRow, ['semester_short']);
        if ($semShortIdx !== null) {
            $map['semester_short'] = $semShortIdx - 1;
        }

        $unitsIdx = $this->headerIndex($headerRow, ['units']);
        if ($unitsIdx !== null) {
            $map['units'] = $unitsIdx - 1;
        }

        $dateIdx = $this->headerIndex($headerRow, ['transaction_date', 'date']);
        if ($dateIdx !== null) {
            $map['date'] = $dateIdx - 1;
        }

        $refIdx = $this->headerIndex($headerRow, ['reference_number', 'reference_or_jev_number', 'reference_jev_or_number', 'reference_jev_o_r_number', 'jev_no', 'or_no', 'ref_no']);
        if ($refIdx !== null) {
            $map['reference'] = $refIdx - 1;
        }

        $partIdx = $this->headerIndex($headerRow, ['particulars']);
        if ($partIdx !== null) {
            $map['particulars'] = $partIdx - 1;
        }

        $rateIdx = $this->headerIndex($headerRow, ['tuition_rate', 'tuition_per_unit_or_misc', 'tuition_per_unit_or_fee_per_semester', 'rate']);
        if ($rateIdx !== null) {
            $map['rate'] = $rateIdx - 1;
        }

        $typeIdx = $this->headerIndex($headerRow, ['ar_or_payment', 'ar_payment', 'type']);
        if ($typeIdx !== null) {
            $map['entry_type'] = $typeIdx - 1;
        }

        $amtIdx = $this->headerIndex($headerRow, ['amount']);
        if ($amtIdx !== null) {
            $map['amount'] = $amtIdx - 1;
        }

        $remIdx = $this->headerIndex($headerRow, ['remarks']);
        if ($remIdx !== null) {
            $map['remarks'] = $remIdx - 1;
        }

        $inpIdx = $this->headerIndex($headerRow, ['input_by', 'input_by_', 'encoded_by', 'prepared_by']);
        if ($inpIdx !== null) {
            $map['input_by'] = $inpIdx - 1;
        }

        // If neither name nor amount could be identified by header, assume unrecognized / absent header row
        if (! isset($map['name']) && ! isset($map['last_name']) && ! isset($map['first_name']) && ! isset($map['amount'])) {
            return null;
        }

        return $map;
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>|null  $columnMap
     * @return array{
     *     raw_name: string,
     *     student_number: ?string,
     *     email: ?string,
     *     last_name: string,
     *     first_name: string,
     *     middle_name: ?string,
     * }|null
     */
    private function extractStudentIdentity(array $row, ?array $columnMap = null): ?array
    {
        if ($columnMap === null) {
            $rawFullName = trim(str_replace(['−', '–', '—'], '-', (string) ($row[0] ?? '')));
            if ($rawFullName === '' || in_array(strtolower($rawFullName), ['student name', 'student', 'ff', 'name'])) {
                return null;
            }

            $parsed = Student::parseRawName($rawFullName);

            return array_merge($parsed, [
                'raw_name' => $rawFullName,
                'student_number' => null,
                'email' => null,
            ]);
        }

        $studentNumber = isset($columnMap['student_number'])
            ? $this->normalizeImportedStudentNumber($row[$columnMap['student_number']] ?? null)
            : null;

        $email = isset($columnMap['email'])
            ? $this->normalizeImportedEmail($row[$columnMap['email']] ?? null)
            : null;

        $rawLast = isset($columnMap['last_name'])
            ? trim((string) ($row[$columnMap['last_name']] ?? ''))
            : '';
        $rawFirst = isset($columnMap['first_name'])
            ? trim((string) ($row[$columnMap['first_name']] ?? ''))
            : '';
        $rawMiddle = isset($columnMap['middle_name'])
            ? trim((string) ($row[$columnMap['middle_name']] ?? ''))
            : '';

        $rawFullName = isset($columnMap['name'])
            ? trim(str_replace(['−', '–', '—'], '-', (string) ($row[$columnMap['name']] ?? '')))
            : '';

        // If split and full-name coexist, use split names when either last or first cell is populated;
        // if both split cells blank, fall back to full_name for that row.
        if ($rawLast !== '' || $rawFirst !== '') {
            $isHeader = in_array(strtolower($rawLast), ['last name', 'lastname', 'surname', 'last_name', 'name', 'student name'])
                && in_array(strtolower($rawFirst), ['', 'first name', 'firstname', 'given name', 'first_name']);

            if ($isHeader) {
                return null;
            }

            $lastName = $rawLast;
            $firstName = $rawFirst;
            $middleName = $rawMiddle !== '' ? rtrim($rawMiddle, '.') : null;

            $nameParts = array_filter([$lastName, $firstName], fn ($v) => $v !== '');
            $rawName = implode(', ', $nameParts);
            if ($middleName) {
                $rawName .= ' '.$middleName;
            }
            $rawName = trim($rawName);

            if ($rawName === '') {
                return null;
            }

            return [
                'raw_name' => $rawName,
                'student_number' => $studentNumber,
                'email' => $email,
                'last_name' => $lastName,
                'first_name' => $firstName,
                'middle_name' => $middleName,
            ];
        }

        if ($rawFullName !== '') {
            if (in_array(strtolower($rawFullName), ['student name', 'student', 'ff', 'name', 'full name', 'last name', 'first name'])) {
                return null;
            }

            $parsed = Student::parseRawName($rawFullName);

            return array_merge($parsed, [
                'raw_name' => $rawFullName,
                'student_number' => $studentNumber,
                'email' => $email,
            ]);
        }

        return null;
    }

    private function normalizeImportedEmail(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $email = trim((string) $value);
        if ($email === '' || str_starts_with($email, '=')) {
            return null;
        }

        $validated = filter_var($email, FILTER_VALIDATE_EMAIL);

        return is_string($validated) && $validated !== '' ? $validated : null;
    }

    private function normalizeImportedStudentNumber(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $studentNumber = trim((string) $value);
        if ($studentNumber === '') {
            return null;
        }

        if (str_starts_with($studentNumber, '=')) {
            return null;
        }

        if (preg_match('/^\d+\.0+$/', $studentNumber) === 1) {
            $studentNumber = preg_replace('/\.0+$/', '', $studentNumber) ?: '';
        }

        if ($studentNumber === '' || strlen($studentNumber) > 50) {
            return null;
        }

        if (preg_match('/\d/', $studentNumber) !== 1) {
            return null;
        }

        return $studentNumber;
    }

    private function normalizeImportedInputBy(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $inputBy = Str::squish(trim((string) $value));

        if ($inputBy === '' || str_starts_with($inputBy, '=') || strlen($inputBy) > 255) {
            return null;
        }

        return $inputBy;
    }

    /**
     * @param  array<int, string>  $headerRow
     * @param  array<int, string>  $candidates
     */
    private function headerIndex(array $headerRow, array $candidates): ?int
    {
        foreach ($candidates as $candidate) {
            $idx = array_search($candidate, $headerRow, true);
            if ($idx !== false) {
                return $idx + 1;
            }
        }

        return null;
    }

    private function emptyImportWarnings(): array
    {
        return [];
    }

    private function importSummary(int $imported, int $skipped): string
    {
        return "Import complete: {$imported} records imported, {$skipped} blank rows skipped.";
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_numeric($value)) {
            $num = (float) $value;
            if ($num > 10000 && $num < 100000) {
                try {
                    return Date::excelToDateTimeObject($num)->format('Y-m-d');
                } catch (\Exception $e) {
                    // Fallback to Ymd parsing if failed
                }
            }
            try {
                return Carbon::createFromFormat('Ymd', (string) $value)->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }
        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /** Converts entry_type enum value → UI display label */
    private function entryTypeToLabel(?string $entryType): string
    {
        return match ($entryType) {
            'ar' => 'AR',
            'adjustment' => 'Adjustment',
            default => 'Payment',
        };
    }

    private function cleanRemarks(mixed $val): ?string
    {
        $str = trim((string) ($val ?? ''));
        if (str_starts_with($str, '=')) {
            return null;
        }

        return $str !== '' ? $str : null;
    }

    private function cleanAmount(mixed $rawAmount): float
    {
        $str = trim((string) ($rawAmount ?? ''));

        // Handle spreadsheet error values (#VALUE!, #REF!, #DIV/0!, #NAME?, #N/A, etc.)
        if ($str === '' || str_starts_with($str, '#')) {
            return 0.0;
        }

        // If somehow a raw formula string still arrives (e.g. from CSV), strip the = and try to parse the number
        if (str_starts_with($str, '=')) {
            $str = ltrim($str, '=');
        }

        $cleaned = (float) preg_replace('/[^\d.]/', '', $str);

        // PostgreSQL decimal(10,2) allows max 99,999,999.99 (absolute value < 10^8)
        if ($cleaned >= 100000000.00) {
            return 99999999.99;
        }

        return abs($cleaned);
    }

    /**
     * Safely calculate a cell's formula value with fallback to raw value or null.
     */
    private function getCalculatedCellValue(Worksheet $sheet, string $coordinate): mixed
    {
        try {
            $cell = $sheet->getCell($coordinate);

            return $cell->isFormula() ? $cell->getCalculatedValue() : $cell->getValue();
        } catch (\Throwable) {
            try {
                return $sheet->getCell($coordinate)->getValue();
            } catch (\Throwable) {
                return null;
            }
        }
    }

    /**
     * @param  Collection<int, GraduateLedger>  $records
     * @return BalanceSummary
     */
    private function calculateStudentBalanceNormalized(Collection $records): array
    {
        $totalCharges = 0.0;
        $totalPayments = 0.0;
        $totalAdjustments = 0.0;

        foreach ($records as $record) {
            $cleanAmount = $this->cleanAmount($record->amount);
            $type = strtolower(trim((string) $record->entry_type));

            if ($type === 'ar') {
                $totalCharges += $cleanAmount;
            } elseif ($type === 'adjustment') {
                $totalAdjustments += $cleanAmount;
            } else {
                $totalPayments += $cleanAmount;
            }
        }

        return [
            'totalCharges' => $totalCharges,
            'totalPayments' => $totalPayments,
            'totalAdjustments' => $totalAdjustments,
            'outstandingBalance' => $totalCharges - $totalPayments - $totalAdjustments,
        ];
    }

    /**
     * Applies a membership scholarship discount to an AR transaction in the Graduate Ledger.
     * Leaves the original AR entry untouched and creates a corresponding adjustment entry.
     */
    public function applyMembership(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'membership' => ['required', 'in:NAPU,NORSUFFA'],
        ]);

        $record = GraduateLedger::findOrFail($id);

        // Only apply to AR entries
        if (strtolower(trim((string) $record->entry_type)) !== 'ar') {
            return back()->with('error', 'Membership scholarship discounts can only be applied to AR (Assessment) entries.');
        }

        $discountAmount = abs((float) $record->amount);
        if ($discountAmount <= 0) {
            return back()->with('error', 'Cannot apply a scholarship to an assessment with an amount of ₱0.00.');
        }

        $membership = $validated['membership'];

        $membershipFullName = $membership === 'NAPU'
            ? 'NORSU Administrative Personnel Union'
            : 'NORSU Federated Faculty Association';

        // Update original AR entry with membership and create corresponding adjustment entry
        DB::transaction(function () use ($record, $membership, $discountAmount): void {
            $record->update([
                'membership' => $membership,
                'discount_amount' => $discountAmount,
            ]);

            GraduateLedger::create([
                'student_id' => $record->student_id,
                'course_id' => $record->course_id,
                'academic_term_id' => $record->academic_term_id,
                'entry_type' => 'adjustment',
                'units' => $record->units,
                'transaction_date' => now()->toDateString(),
                'reference_number' => $record->reference_number,
                'particulars' => $record->particulars ?? 'Tuition',
                'rate' => $record->rate,
                'amount' => $discountAmount,
                'remarks' => $record->remarks,
                'status' => 'ADJUSTMENT',
                'membership' => $membership,
                'input_by' => auth()->id(),
            ]);
        });

        return back()->with('success', "{$membership} ({$membershipFullName}) 100% scholarship of ₱".number_format($discountAmount, 2).' applied successfully.');
    }
}
