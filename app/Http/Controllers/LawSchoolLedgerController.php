<?php

namespace App\Http\Controllers;

use App\Exports\LawSchoolLedgerExport;
use App\Http\Requests\StoreLawSchoolLedgerRequest;
use App\Http\Requests\UpdateLawSchoolLedgerRequest;
use App\Models\ActivityLog;
use App\Models\LawAcademicTerm;
use App\Models\LawCourse;
use App\Models\LawSchoolLedger;
use App\Models\LawStudent;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * @phpstan-type WarningCounts array{
 *     negative_blank_type: int,
 *     negative_labeled_ar: int,
 *     payment_missing_parentheses: int
 * }
 * @phpstan-type BalanceSummary array{totalCharges: float, totalPayments: float, outstandingBalance: float}
 */
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
            ->whereRaw("UPPER(TRIM(ar_or_payment)) IN ('PAYMENT', 'P')")
            ->sum(DB::raw('ABS(amount)'));

        $totalAdjustments = (float) (clone $query)
            ->whereRaw("UPPER(TRIM(ar_or_payment)) IN ('ADJUSTMENT', 'ADJ')")
            ->sum(DB::raw('ABS(amount)'));

        $outstandingBalance = $totalAssessments - $totalPayments - $totalAdjustments;

        $statusCounts = (clone $query)
            ->selectRaw('UPPER(TRIM(status)) as status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn (LawSchoolLedger $item): array => [
                ucfirst(strtolower((string) $item->status)) => (int) $item->getAttribute('count'),
            ]);

        // 2. Fetch paginated records
        $records = $query
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        // Transform each row into the shape Index.tsx expects
        $records->through(fn (LawSchoolLedger $r): array => $this->transformRecord($r));

        return Inertia::render('law-ledger/Index', [
            'records' => $records,
            'filters' => $request->only([
                'search', 'school_year', 'semester_or_summer', 'course', 'status', 'ar_or_payment', 'date_from', 'date_to',
            ]),
            'stats' => [
                'totalStudents' => $totalStudents,
                'totalAssessments' => $totalAssessments,
                'totalPayments' => $totalPayments,
                'totalAdjustments' => $totalAdjustments,
                'outstandingBalance' => $outstandingBalance,
                'statusCounts' => $statusCounts,
            ],
            'filterOptions' => $this->getFilterOptions(),
        ]);
    }

    public function searchStudents(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));
        $limit = (int) $request->query('limit', 50);

        $query = LawSchoolLedger::query()
            ->whereNotNull('last_name')
            ->where('last_name', '!=', '');

        if ($search !== '') {
            $searchLower = strtolower($search);
            $prefix = strtolower($search).'%';
            $query->where(function ($q) use ($searchLower) {
                $q->whereRaw('LOWER(last_name) LIKE ?', ["%{$searchLower}%"])
                    ->orWhereRaw('LOWER(first_name) LIKE ?', ["%{$searchLower}%"])
                    ->orWhereRaw('LOWER(middle_initial) LIKE ?', ["%{$searchLower}%"])
                    ->orWhereRaw("LOWER(TRIM(CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_initial, '')))) LIKE ?", ["%{$searchLower}%"]);
            })
            // Relevance sort: starts-with results float to top
                ->orderByRaw(
                    'CASE WHEN LOWER(last_name) LIKE ? OR LOWER(first_name) LIKE ? THEN 0 ELSE 1 END',
                    [$prefix, $prefix]
                );
        }

        $students = $query
            ->orderBy('last_name', 'asc')
            ->limit($limit)
            ->get(['last_name', 'first_name', 'middle_initial'])
            ->map(function (LawSchoolLedger $student): string {
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
    public function export(Request $request): BinaryFileResponse
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
        $statuses = $this->deduplicatedOptions('status');

        if ($statuses === []) {
            $statuses = ['Pending', 'Paid'];
        }

        return Inertia::render('law-ledger/AddTransaction', [
            'students' => $this->studentList(),
            'courses' => $this->courseList(),
            'academicTerms' => $this->academicTermList(),
            'statuses' => $statuses,
            'authUserName' => optional(auth()->user())->name ?? '',
        ]);
    }

    /**
     * Stores a new law ledger transaction.
     */
    public function store(StoreLawSchoolLedgerRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data): void {
            $studentId = $data['student_id'] ?? null;

            if (! $studentId && isset($data['new_student'])) {
                $newStudent = $data['new_student'];
                $studentAttributes = [
                    'student_number' => $newStudent['student_number'] ?? null,
                    'last_name' => $newStudent['last_name'],
                    'first_name' => $newStudent['first_name'],
                    'middle_name' => $newStudent['middle_name'] ?? null,
                    'raw_name_from_csv' => "{$newStudent['last_name']}, {$newStudent['first_name']}",
                ];

                $student = filled($studentAttributes['student_number'])
                    ? LawStudent::create($studentAttributes)
                    : LawStudent::firstOrCreate(
                        Arr::only($studentAttributes, ['last_name', 'first_name']),
                        Arr::except($studentAttributes, ['last_name', 'first_name']),
                    );
                $studentId = $student->id;
                $data['middle_initial'] = $data['middle_initial'] ?? $this->normalizeMiddleInitial($newStudent['middle_name'] ?? null);
            }

            $studentId = (int) $studentId;

            // Pull name columns from the chosen student when not provided
            if ($studentId && empty($data['last_name'])) {
                $student = LawStudent::find($studentId);
                if ($student) {
                    $data['last_name'] = $student->last_name;
                    $data['first_name'] = $student->first_name;
                    $data['middle_name'] = $data['middle_name'] ?? $student->middle_name;
                    $data['middle_initial'] = $data['middle_initial'] ?? $this->normalizeMiddleInitial($student->middle_name);
                }
            }

            $attributes = $this->buildLedgerRow($data, $studentId, $this->resolveAcademicTermId($data));
            $attributes['entry_type'] = $data['entry_type'] ?? 'ar';
            $attributes['ar_or_payment'] = $this->entryTypeToLabel($attributes['entry_type']);
            $attributes['status'] = $this->determineStatus(
                (float) ($attributes['amount'] ?? 0),
                $data['status'] ?? null,
            );

            LawSchoolLedger::create($attributes);
        });

        return redirect()->route('law-ledger.index')->with('success', 'Transaction created successfully.');
    }

    /**
     * Renders the form for editing an existing law ledger transaction.
     */
    public function edit(int $id): Response
    {
        $record = LawSchoolLedger::with(['lawStudent', 'lawCourse', 'lawAcademicTerm'])->findOrFail($id);

        return Inertia::render('law-ledger/EditTransaction', [
            'record' => $this->recordForForm($record),
            'students' => $this->studentList(),
            'courses' => $this->courseList(),
            'academicTerms' => $this->academicTermList(),
            'filterOptions' => $this->getFilterOptions(),
        ]);
    }

    /**
     * Updates an existing law ledger transaction.
     */
    public function update(UpdateLawSchoolLedgerRequest $request, int $id): RedirectResponse
    {
        $record = LawSchoolLedger::findOrFail($id);
        $data = $request->validated();

        DB::transaction(function () use ($data, $record): void {
            $data = array_replace([
                'student_id' => $record->student_id_fk,
                'course_id' => $record->course_id,
                'academic_term_id' => $record->academic_term_id,
                'school_year' => $record->school_year,
                'semester_or_summer' => $record->semester_or_summer,
                'last_name' => $record->last_name,
                'first_name' => $record->first_name,
                'middle_initial' => $record->middle_initial,
                'middle_name' => $record->middle_name,
                'course' => $record->course,
                'units' => $record->units,
                'transaction_date' => $record->transaction_date,
                'reference_jev_or_number' => $record->reference_jev_or_number,
                'particulars' => $record->particulars,
                'tuition_per_unit_or_fee_per_semester' => $record->tuition_per_unit_or_fee_per_semester,
                'amount' => $record->amount,
                'remarks' => $record->remarks,
                'input_by' => $record->input_by,
            ], $data);

            $studentId = isset($data['student_id'])
                ? (int) $data['student_id']
                : null;

            // Sync name columns from the chosen student
            if ($studentId) {
                $student = LawStudent::find($studentId);
                if ($student) {
                    $data['last_name'] = $data['last_name'] ?? $student->last_name;
                    $data['first_name'] = $data['first_name'] ?? $student->first_name;
                    $data['middle_name'] = $data['middle_name'] ?? $student->middle_name;
                    $data['middle_initial'] = $data['middle_initial'] ?? $this->normalizeMiddleInitial($student->middle_name);
                }
            }

            $attributes = $this->buildLedgerRow($data, $studentId, $this->resolveAcademicTermId($data));
            $attributes['entry_type'] = $data['entry_type'] ?? ($record->entry_type ?? 'ar');
            $attributes['ar_or_payment'] = $data['ar_or_payment'] ?? $this->entryTypeToLabel($attributes['entry_type']);
            $attributes['status'] = $this->determineStatus(
                (float) ($attributes['amount'] ?? 0),
                $data['status'] ?? null,
            );

            $record->update($attributes);
        });

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

    public function destroyStudent(int $id): RedirectResponse
    {
        LawStudent::findOrFail($id)->delete();

        return back()->with('success', 'Student deleted successfully.');
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
        if (! $uploadedFile instanceof UploadedFile) {
            return back()->with('error', 'The uploaded ledger file is invalid.');
        }

        $extension = strtolower($uploadedFile->getClientOriginalExtension());
        $path = $uploadedFile->getRealPath();
        if ($path === false) {
            return back()->with('error', 'Could not read the uploaded ledger file.');
        }

        $imported = 0;
        $skipped = 0;
        $warnings = $this->emptyImportWarnings();
        $now = now();

        if ($extension === 'csv') {
            return $this->importCsv($path, $imported, $skipped, $warnings, $now);
        }

        return $this->importExcel($path, $imported, $skipped, $warnings, $now);
    }

    /**
     * Two-pass memory-efficient CSV importer.
     * Pass 1: stream rows, collect distinct course codes + (school_year, semester) pairs,
     *         and bulk-resolve FK lookup maps.
     * Pass 2: stream again, map each row to normalized columns, chunk-insert 1000 at a time.
     *
     * @param  WarningCounts  $warnings
     *
     * @param-out WarningCounts $warnings
     */
    private function importCsv(string $path, int &$imported, int &$skipped, array &$warnings, CarbonInterface $now): RedirectResponse
    {
        // ── Pass 1: collect distinct courses + terms + students ───────────────
        $handle = fopen($path, 'r');
        if (! is_resource($handle)) {
            return redirect()->route('law-ledger.index')->with('error', 'Could not open CSV file.');
        }

        $headerRow = fgetcsv($handle);
        $headers = $headerRow ? array_map(fn ($h) => Str::slug((string) $h, '_'), $headerRow) : [];

        $distinctCourses = [];
        $distinctTerms = [];
        $distinctStudents = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rowData = [];
            foreach ($row as $index => $value) {
                $key = $headers[$index] ?? 'column_'.$index;
                $rowData[$key] = $value;
            }

            $code = trim((string) Arr::get($rowData, 'course', ''));
            if ($code !== '') {
                $distinctCourses[$code] = true;
            }

            $sy = trim((string) (Arr::get($rowData, 'school_year') ?? Arr::get($rowData, 'academic_year') ?? ''));
            $semRaw = trim((string) (
                Arr::get($rowData, 'semester_or_summer')
                ?? Arr::get($rowData, 'semester_summer')
                ?? Arr::get($rowData, 'semester')
                ?? ''
            ));
            if ($sy !== '' && $semRaw !== '') {
                $sem = LawAcademicTerm::normalizeSemester($semRaw);
                $distinctTerms["{$sy}|||{$sem}"] = ['school_year' => $sy, 'semester' => $sem];
            }

            $last = trim((string) Arr::get($rowData, 'last_name', ''));
            $first = trim((string) Arr::get($rowData, 'first_name', ''));
            $mi = trim((string) Arr::get($rowData, 'middle_initial', ''));
            if ($last !== '' && $first !== '') {
                $distinctStudents["{$last}|||{$first}|||{$mi}"] = true;
            }
        }
        fclose($handle);

        // Bulk-resolve lookup maps for courses, terms, and students
        [$courseMap, $termMap, $studentMap] = $this->buildImportLookupMaps(
            $distinctCourses,
            $distinctTerms,
            $distinctStudents,
            $now,
        );

        // ── Pass 2: stream again, map rows, chunk-insert ───────────────────────
        $handle = fopen($path, 'r');
        if (! is_resource($handle)) {
            return redirect()->route('law-ledger.index')->with('error', 'Could not reopen CSV file.');
        }

        $headerRow = fgetcsv($handle);
        $headers = $headerRow ? array_map(fn ($h) => Str::slug((string) $h, '_'), $headerRow) : [];

        $insertData = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rowData = [];
            foreach ($row as $index => $value) {
                $key = $headers[$index] ?? 'column_'.$index;
                $rowData[$key] = $value;
            }

            $data = $this->mapImportRow($rowData, $warnings);

            if ($data !== null) {
                $data = $this->resolveImportRowFks($data, $courseMap, $termMap, $studentMap);
                $data['created_at'] = $now;
                $data['updated_at'] = $now;
                $insertData[] = $data;
            } else {
                $skipped++;
            }

            if (count($insertData) >= 1000) {
                DB::transaction(fn () => LawSchoolLedger::insert($insertData));
                $imported += count($insertData);
                $insertData = [];
            }
        }

        if (! empty($insertData)) {
            DB::transaction(fn () => LawSchoolLedger::insert($insertData));
            $imported += count($insertData);
        }
        fclose($handle);

        ActivityLog::recordImport(LawSchoolLedger::class, $imported, 'Law School Ledger');

        return redirect()->route('law-ledger.index')
            ->with('success', $this->importSummary($imported, $skipped, $warnings));
    }

    /**
     * Two-pass memory-efficient Excel importer (xlsx/xls) using PhpSpreadsheet.
     * Same pattern as importCsv() but reads from the in-memory sheet.
     *
     * @param  WarningCounts  $warnings
     *
     * @param-out WarningCounts $warnings
     */
    private function importExcel(string $path, int &$imported, int &$skipped, array &$warnings, CarbonInterface $now): RedirectResponse
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(false);

        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();
        $highestColIndex = Coordinate::columnIndexFromString($highestCol);

        if ($highestRow <= 1) {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            return redirect()->route('law-ledger.index')
                ->with('success', 'No rows found in the uploaded file.');
        }

        $headerRow = [];
        for ($c = 1; $c <= $highestColIndex; $c++) {
            $colLetter = Coordinate::stringFromColumnIndex($c);
            $headerRow[] = Str::slug((string) $sheet->getCell($colLetter.'1')->getValue(), '_');
        }

        // ── Pass 1: collect distinct values (rows 2..highestRow) ───────────────
        $distinctCourses = [];
        $distinctTerms = [];
        $distinctStudents = [];

        for ($r = 2; $r <= $highestRow; $r++) {
            $code = trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($this->headerIndex($headerRow, ['course', 'program'])).$r)->getValue());
            if ($code !== '') {
                $distinctCourses[$code] = true;
            }

            $sy = trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($this->headerIndex($headerRow, ['school_year', 'academic_year', 'sy'])).$r)->getValue());
            $semRaw = trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($this->headerIndex($headerRow, ['semester_or_summer', 'semester_summer', 'semester', 'term'])).$r)->getValue());
            if ($sy !== '' && $semRaw !== '') {
                $sem = LawAcademicTerm::normalizeSemester($semRaw);
                $distinctTerms["{$sy}|||{$sem}"] = ['school_year' => $sy, 'semester' => $sem];
            }

            $lastIdx = $this->headerIndex($headerRow, ['last_name']);
            $firstIdx = $this->headerIndex($headerRow, ['first_name']);
            $miIdx = $this->headerIndex($headerRow, ['middle_initial']);
            $last = $lastIdx ? trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($lastIdx).$r)->getValue()) : '';
            $first = $firstIdx ? trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($firstIdx).$r)->getValue()) : '';
            $mi = $miIdx ? trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($miIdx).$r)->getValue()) : '';
            if ($last !== '' && $first !== '') {
                $distinctStudents["{$last}|||{$first}|||{$mi}"] = true;
            }
        }

        [$courseMap, $termMap, $studentMap] = $this->buildImportLookupMaps(
            $distinctCourses,
            $distinctTerms,
            $distinctStudents,
            $now,
        );

        // ── Pass 2: stream rows, chunk-insert ──────────────────────────────────
        $insertData = [];

        for ($r = 2; $r <= $highestRow; $r++) {
            $rowData = [];
            for ($c = 1; $c <= $highestColIndex; $c++) {
                $colLetter = Coordinate::stringFromColumnIndex($c);
                $key = $headerRow[$c - 1] ?? 'column_'.($c - 1);
                $cell = $sheet->getCell($colLetter.$r);
                $rowData[$key] = $cell->isFormula()
                    ? ($key === 'amount' ? $cell->getCalculatedValue() : null)
                    : $cell->getValue();
            }

            $data = $this->mapImportRow($rowData, $warnings);

            if ($data !== null) {
                $data = $this->resolveImportRowFks($data, $courseMap, $termMap, $studentMap);
                $data['created_at'] = $now;
                $data['updated_at'] = $now;
                $insertData[] = $data;
            } else {
                $skipped++;
            }

            if (count($insertData) >= 1000) {
                DB::transaction(fn () => LawSchoolLedger::insert($insertData));
                $imported += count($insertData);
                $insertData = [];
            }
        }

        if (! empty($insertData)) {
            DB::transaction(fn () => LawSchoolLedger::insert($insertData));
            $imported += count($insertData);
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        ActivityLog::recordImport(LawSchoolLedger::class, $imported, 'Law School Ledger');

        return redirect()->route('law-ledger.index')
            ->with('success', $this->importSummary($imported, $skipped, $warnings));
    }

    /**
     * Returns the 1-based column index of the first matching header slug, or null.
     *
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

    /**
     * Bulk-resolves lookup maps for courses, academic terms, and students.
     * Avoids N+1 INSERTs by chunking new rows at 500 per insert.
     *
     * @param  array<string, true>  $distinctCourses
     * @param  array<string, array{school_year:string, semester:string}>  $distinctTerms
     * @param  array<string, true>  $distinctStudents
     * @return array{0: array<string, int>, 1: array<string, int>, 2: array<string, int>}
     */
    private function buildImportLookupMaps(
        array $distinctCourses,
        array $distinctTerms,
        array $distinctStudents,
        CarbonInterface $now,
    ): array {
        // Courses
        $courseMap = LawCourse::pluck('id', 'code')->toArray();
        $newCourses = [];
        foreach (array_keys($distinctCourses) as $code) {
            if (! isset($courseMap[$code])) {
                $newCourses[] = [
                    'code' => $code,
                    'title' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        if (! empty($newCourses)) {
            foreach (array_chunk($newCourses, 500) as $chunk) {
                LawCourse::insert($chunk);
            }
            $courseMap = LawCourse::pluck('id', 'code')->toArray();
        }

        // Academic terms
        $termsInDb = LawAcademicTerm::get(['id', 'school_year', 'semester'])->toArray();
        $termMap = [];
        foreach ($termsInDb as $t) {
            $termMap["{$t['school_year']}|||{$t['semester']}"] = $t['id'];
        }
        $newTerms = [];
        foreach ($distinctTerms as $key => $pair) {
            if (! isset($termMap[$key])) {
                $newTerms[] = [
                    'school_year' => $pair['school_year'],
                    'semester_short' => $this->semesterShort($pair['semester']),
                    'semester' => $pair['semester'],
                    'sort_order' => LawAcademicTerm::sortOrder($pair['semester']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        if (! empty($newTerms)) {
            LawAcademicTerm::insert($newTerms);
            $termsInDb = LawAcademicTerm::get(['id', 'school_year', 'semester'])->toArray();
            $termMap = [];
            foreach ($termsInDb as $t) {
                $termMap["{$t['school_year']}|||{$t['semester']}"] = $t['id'];
            }
        }

        // Students
        $studentMap = LawStudent::get(['id', 'last_name', 'first_name', 'middle_name'])
            ->mapWithKeys(fn ($s) => ["{$s->last_name}|||{$s->first_name}|||{$s->middle_name}" => $s->id])
            ->toArray();

        $newStudents = [];
        foreach (array_keys($distinctStudents) as $key) {
            if (! isset($studentMap[$key])) {
                [$last, $first, $mi] = explode('|||', $key, 3);
                $newStudents[] = [
                    'last_name' => $last,
                    'first_name' => $first,
                    'middle_name' => $mi !== '' ? $mi : null,
                    'raw_name_from_csv' => "{$last}, {$first}",
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        if (! empty($newStudents)) {
            foreach (array_chunk($newStudents, 500) as $chunk) {
                LawStudent::insert($chunk);
            }
            $studentMap = LawStudent::get(['id', 'last_name', 'first_name', 'middle_name'])
                ->mapWithKeys(fn ($s) => ["{$s->last_name}|||{$s->first_name}|||{$s->middle_name}" => $s->id])
                ->toArray();
        }

        return [$courseMap, $termMap, $studentMap];
    }

    /**
     * Resolves course_id / academic_term_id / student_id_fk on an import row
     * using the bulk-built lookup maps. The legacy raw columns are preserved.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, int>  $courseMap
     * @param  array<string, int>  $termMap
     * @param  array<string, int>  $studentMap
     * @return array<string, mixed>
     */
    private function resolveImportRowFks(array $data, array $courseMap, array $termMap, array $studentMap): array
    {
        $code = trim((string) ($data['course'] ?? ''));
        if ($code !== '' && isset($courseMap[$code])) {
            $data['course_id'] = $courseMap[$code];
        }

        $sy = trim((string) ($data['school_year'] ?? ''));
        $semRaw = (string) ($data['semester_or_summer'] ?? '');
        if ($sy !== '' && $semRaw !== '') {
            $sem = LawAcademicTerm::normalizeSemester($semRaw);
            $key = "{$sy}|||{$sem}";
            if (isset($termMap[$key])) {
                $data['academic_term_id'] = $termMap[$key];
                // Promote semester_or_summer to canonical form for consistent filtering
                $data['semester_or_summer'] = $sem;
            }
        }

        $last = trim((string) ($data['last_name'] ?? ''));
        $first = trim((string) ($data['first_name'] ?? ''));
        $mi = trim((string) ($data['middle_name'] ?? ($data['middle_initial'] ?? '')));
        if ($last !== '' && $first !== '') {
            $key = "{$last}|||{$first}|||{$mi}";
            if (isset($studentMap[$key])) {
                $data['student_id_fk'] = $studentMap[$key];
            }
        }

        return $data;
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
            ->values()
            ->map(fn ($name) => ['id' => $name, 'full_name' => $name])
            ->all();

        $selectedStudent = $request->input('student') ?? $request->input('student_id');
        $studentRecords = collect();
        $balanceSummary = [
            'totalCharges' => 0,
            'totalPayments' => 0,
            'outstandingBalance' => 0,
        ];

        if ($selectedStudent) {
            $rawStudentRecords = $this->queryStudentByName($selectedStudent)
                ->orderBy('id', 'asc')
                ->get();

            $balanceSummary = $this->calculateStudentBalanceNormalized($rawStudentRecords);
            $studentRecords = $rawStudentRecords
                ->map(fn ($r) => $this->transformRecord($r));
        }

        return Inertia::render('law-ledger/PrintSelect', [
            'students' => $students,
            'selectedStudent' => $selectedStudent,
            'records' => $studentRecords,
            'summary' => $balanceSummary,
            'filterOptions' => $this->getFilterOptions(),
        ]);
    }

    /**
     * Generates and streams the PDF statement.
     */
    public function generatePdf(Request $request): HttpResponse
    {
        set_time_limit(300);

        $validated = $request->validate([
            'student' => ['required_without:student_id', 'string'],
            'student_id' => ['required_without:student', 'string'],
            'school_year' => ['nullable', 'string', 'max:20'],
            'semester' => ['nullable', 'in:First Semester,Second Semester,Summer'],
        ]);

        $studentName = str_replace(['−', '–', '—'], '-', (string) ($validated['student'] ?? $validated['student_id']));
        $recordsQuery = isset($validated['student_id'])
            ? LawSchoolLedger::query()->where('student_id_fk', $validated['student_id'])
            : $this->queryStudentByName($studentName);

        $records = $recordsQuery
            ->when(
                $validated['school_year'] ?? null,
                fn ($query, $schoolYear) => $query->where('school_year', $schoolYear),
            )
            ->orderBy('id', 'asc')
            ->get()
            ->when(
                $validated['semester'] ?? null,
                fn ($records, $semester) => $records->filter(
                    fn (LawSchoolLedger $record) => LawAcademicTerm::normalizeSemester(
                        (string) $record->semester_or_summer,
                    ) === $semester,
                )->values(),
            );

        if (isset($validated['student_id']) && $records->isNotEmpty()) {
            $student = $records->first();
            $studentName = trim("{$student->last_name}, {$student->first_name} ".($student->middle_initial ?: ''));
        }

        $summary = $this->calculateStudentBalanceNormalized($records);

        $logoPath = public_path('norsu.png');
        $logoContents = file_exists($logoPath) ? file_get_contents($logoPath) : false;
        $logoDataUri = is_string($logoContents)
            ? 'data:image/png;base64,'.base64_encode($logoContents)
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
     *
     * @param  array<string, mixed>  $row
     * @param  WarningCounts  $warnings
     *
     * @param-out WarningCounts $warnings
     *
     * @return array<string, mixed>|null
     */
    private function mapImportRow(array $row, array &$warnings): ?array
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

        $rawAmount = Arr::get($normalized, 'amount');
        $amount = is_numeric($rawAmount) ? (float) $rawAmount : 0;

        $units = (float) (Arr::get($normalized, 'units', 0) ?? 0);
        $tuition = (float) (
            Arr::get($normalized, 'tuition_per_unit_registration_and_misc_fee_per_semester')
            ?? Arr::get($normalized, 'tuition_per_unit_reg_and_miscellaneous_per_semester')
            ?? Arr::get($normalized, 'tuition_per_unit_or_fee_per_semester')
            ?? Arr::get($normalized, 'tuition_per_unit_or_misc', 0)
            ?? 0
        );

        $arOrPayment = Arr::get($normalized, 'ar_or_payment')
            ?? Arr::get($normalized, 'ar_payment')
            ?? Arr::get($normalized, 'arpayment')
            ?? Arr::get($normalized, 'transaction_type')
            ?? Arr::get($normalized, 'type')
            ?? 'AR';

        // ── Import warning detection ─────────────────────────────────────────
        $normalizedType = strtoupper(trim((string) $arOrPayment));

        // Warning: negative amount with blank or unknown type
        if ($amount < 0 && ($normalizedType === '' || $normalizedType === 'AR')) {
            $warnings[self::WARNING_NEGATIVE_BLANK_TYPE]++;
        }

        // Warning: negative amount labeled as AR (should be payment)
        if ($amount < 0 && $normalizedType === 'AR') {
            $warnings[self::WARNING_NEGATIVE_LABELED_AR]++;
        }

        // Warning: positive amount labeled as payment (may be formatting issue)
        if ($amount > 0 && in_array($normalizedType, ['PAYMENT', 'P'])) {
            $warnings[self::WARNING_PAYMENT_MISSING_PARENTHESES]++;
        }

        if ($amount === 0.0 && $normalizedType === 'AR' && $units > 0 && $tuition > 0) {
            $amount = $units * $tuition;
        }

        $rawRemarks = Arr::get($normalized, 'remarks') ?? Arr::get($normalized, 'remark');
        $remarks = is_string($rawRemarks) ? trim($rawRemarks) : null;
        if ($remarks !== null && str_starts_with($remarks, '=')) {
            $remarks = null;
        }

        $rawStatus = Arr::get($normalized, 'status');

        return [
            'last_name' => $nameParts['last_name'],
            'first_name' => $nameParts['first_name'],
            'middle_initial' => $nameParts['middle_initial'],
            'student_id' => Arr::get($normalized, 'student_id'),
            'student_id_fk' => null,
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
            'tuition_per_unit_or_fee_per_semester' => $tuition,
            'ar_or_payment' => $arOrPayment,
            'amount' => $amount,
            'status' => $this->determineStatus($amount, is_string($rawStatus) ? $rawStatus : null),
            'remarks' => $remarks,
            'input_by' => Arr::get($normalized, 'input_by'),
        ];
    }

    /**
     * Builds the Law School Ledger query with the filters shared by the index
     * and export methods.
     */
    /** @return Builder<LawSchoolLedger> */
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
            ->with('lawStudent')
            ->when($request->input('search'), function ($query, $search) {
                // Lowercase the search term to match the LOWER() applied to columns.
                // PostgreSQL's LIKE is case-sensitive, so "Juan" won't match "juan"
                // unless the search value is also lowercased. This mirrors the
                // pattern used in searchStudents() and StaffInputController::index().
                $search = strtolower((string) $search);
                $query->where(function ($query) use ($search) {
                    $query->whereRaw('LOWER(first_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(middle_initial) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(student_id) LIKE ?', ["%{$search}%"])
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
                    foreach ($this->semesterAliases((string) $semester) as $alias) {
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
                $query->whereRaw('UPPER(TRIM(status)) = ?', [strtoupper(trim((string) $status))]);
            })
            ->when($type, function ($query, $type) {
                $query->whereRaw('UPPER(TRIM(ar_or_payment)) = ?', [strtoupper(trim((string) $type))]);
            })
            ->when($dateFrom, function ($query, $dateFrom) {
                $query->whereDate('transaction_date', '>=', $dateFrom);
            })
            ->when($dateTo, function ($query, $dateTo) {
                $query->whereDate('transaction_date', '<=', $dateTo);
            });
    }

    /** @return Builder<LawSchoolLedger> */
    private function queryStudentByName(string $studentName): Builder
    {
        $cleanName = trim((string) str_replace(['−', '–', '—'], '-', $studentName));

        return LawSchoolLedger::query()
            ->with('lawStudent')
            ->where(function ($q) use ($cleanName) {
                $q->whereRaw("TRIM(CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_initial, ''))) = ?", [$cleanName])
                    ->orWhereRaw("TRIM(CONCAT(last_name, ', ', first_name)) = ?", [$cleanName])
                    ->orWhere('last_name', 'like', "%{$cleanName}%");
            });
    }

    /** @return array{last_name: string, first_name: string, middle_initial: string|null}|null */
    private function parseStudentName(string $name): ?array
    {
        $name = trim((string) str_replace(['−', '–', '—'], '-', $name));

        if (str_contains($name, ',')) {
            [$lastName, $rest] = explode(',', $name, 2);
            $rest = trim($rest);

            // If there's a middle initial as the last single character / word
            $parts = preg_split('/\s+/', $rest);
            if ($parts === false) {
                $parts = [];
            }
            if (count($parts) > 1) {
                $lastPart = $parts[array_key_last($parts)];
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

    private function determineStatus(float $amount, ?string $rawStatus): string
    {
        if ($rawStatus) {
            return $rawStatus;
        }

        return $amount > 0 ? 'Pending' : 'Paid';
    }

    /** @return array<string, mixed> */
    private function transformRecord(LawSchoolLedger $r): array
    {
        return [
            'id' => $r->id,
            'studentId' => $r->student_id_fk,
            'studentNumber' => $r->lawStudent?->student_number,
            'lastName' => $r->last_name,
            'firstName' => $r->first_name,
            'middleInitial' => $this->normalizeMiddleInitial($r->middle_initial),
            'name' => optional($r->lawStudent)->full_name ?? trim("$r->last_name, $r->first_name ".($r->middle_initial ? "$r->middle_initial" : '')),
            'course' => $r->course,
            'schoolYear' => $r->school_year,
            'semesterOrSummer' => $r->semester_or_summer,
            'units' => (float) $r->units,
            'transactionDate' => $r->transaction_date,
            'referenceNo' => $r->reference_jev_or_number,
            'particulars' => $r->particulars,
            'tuitionPerUnitOrFeePerSemester' => (float) ($r->tuition_per_unit_or_fee_per_semester ?? 0),
            'arOrPayment' => $r->ar_or_payment,
            'arPayment' => $this->entryTypeToLabel($r->entry_type),
            'entryType' => $r->entry_type,
            'amount' => $this->cleanAmount($r->amount),
            'status' => $r->status,
            'remark' => $r->remarks,
            'inputBy' => $r->input_by,
        ];
    }

    // ─── Form List Helpers ────────────────────────────────────────────────────

    /**
     * List of students for the form picker.
     * Returns [{id, student_number, last_name, first_name, middle_name, last_course_id}].
     *
     * @return list<array{id: int, student_number: string|null, last_name: string, first_name: string, middle_name: string|null, last_course_id: null}>
     */
    private function studentList(): array
    {
        return array_values(LawStudent::orderBy('last_name')
            ->get(['id', 'student_number', 'last_name', 'first_name', 'middle_name'])
            ->map(fn (LawStudent $s): array => [
                'id' => (int) $s->id,
                'student_number' => $s->student_number,
                'last_name' => $s->last_name,
                'first_name' => $s->first_name,
                'middle_name' => $s->middle_name,
                'last_course_id' => null,
            ])
            ->all());
    }

    /**
     * List of courses for the form picker.
     * Returns [{id, code}].
     *
     * @return list<array{id: int, code: string}>
     */
    private function courseList(): array
    {
        return array_values(LawCourse::orderBy('code')
            ->get(['id', 'code'])
            ->map(fn (LawCourse $course): array => [
                'id' => (int) $course->id,
                'code' => $course->code,
            ])
            ->all());
    }

    /**
     * List of academic terms for the form picker.
     *
     * @return list<array{id: int, school_year: string, semester: string}>
     */
    private function academicTermList(): array
    {
        return array_values(LawAcademicTerm::orderBy('school_year', 'desc')
            ->orderBy('sort_order')
            ->get(['id', 'school_year', 'semester'])
            ->map(fn (LawAcademicTerm $term): array => [
                'id' => (int) $term->id,
                'school_year' => $term->school_year,
                'semester' => $term->semester,
            ])
            ->all());
    }

    /**
     * Resolves a LawAcademicTerm id from either an explicit academic_term_id or
     * from the school_year/semester pair (auto-creates a new term when missing).
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveAcademicTermId(array $data): ?int
    {
        if (! empty($data['academic_term_id'])) {
            return (int) $data['academic_term_id'];
        }

        if (empty($data['school_year']) || empty($data['semester'])) {
            return null;
        }

        $semester = LawAcademicTerm::normalizeSemester((string) $data['semester']);

        $term = LawAcademicTerm::firstOrCreate(
            [
                'school_year' => $data['school_year'],
                'semester_short' => $this->semesterShort($semester),
            ],
            [
                'semester' => $semester,
                'sort_order' => LawAcademicTerm::sortOrder($semester),
            ]
        );

        return $term->id;
    }

    private function semesterShort(string $semester): string
    {
        return match ($semester) {
            'First Semester' => '1st Sem',
            'Second Semester' => '2nd Sem',
            default => 'Summer',
        };
    }

    /**
     * Builds the row payload for LawSchoolLedger::create() / update().
     * Maps validated input → actual `law_school_ledgers` columns and auto-computes
     * `amount = units × tuition_per_unit_or_fee_per_semester` when entry_type is `ar`
     * and the user did not supply an explicit amount.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildLedgerRow(array $data, ?int $studentId, ?int $academicTermId): array
    {
        $courseCode = null;
        if (isset($data['course_id']) && is_numeric($data['course_id'])) {
            $courseCode = LawCourse::query()->find((int) $data['course_id'])?->code;
        }
        $middleInitialSource = $data['middle_initial']
            ?? $data['middle_name']
            ?? (is_array($data['new_student'] ?? null) ? ($data['new_student']['middle_name'] ?? null) : null);

        $attributes = [
            'student_id_fk' => $studentId,
            'course_id' => $data['course_id'] ?? null,
            'academic_term_id' => $academicTermId,
            'last_name' => $data['last_name'] ?? null,
            'first_name' => $data['first_name'] ?? null,
            'middle_initial' => $this->normalizeMiddleInitial($middleInitialSource),
            'middle_name' => $data['middle_name'] ?? null,
            'course' => $data['course'] ?? $courseCode,
            'school_year' => $data['school_year'] ?? null,
            'semester_or_summer' => $data['semester_or_summer']
                ?? ($data['semester'] ?? null),
            'units' => $data['units'] ?? null,
            'transaction_date' => $data['transaction_date'] ?? null,
            'reference_jev_or_number' => $data['reference_jev_or_number'] ?? null,
            'particulars' => $data['particulars'] ?? 'Tuition',
            'tuition_per_unit_or_fee_per_semester' => $data['tuition_per_unit_or_fee_per_semester'] ?? '0.00',
            'entry_type' => $data['entry_type'] ?? 'ar',
            'ar_or_payment' => $data['ar_or_payment'] ?? 'AR',
            'amount' => $data['amount'] ?? null,
            'remarks' => $data['remarks'] ?? null,
            'status' => $data['status'] ?? 'Active',
            'input_by' => $data['input_by'] ?? null,
        ];

        $entryType = $attributes['entry_type'];

        // Auto-compute amount when AR and no amount supplied (mirrors Graduate behavior)
        if ($entryType === 'ar' && blank($attributes['amount'])) {
            $attributes['amount'] = round(
                (float) ($attributes['units'] ?? 0) * (float) $attributes['tuition_per_unit_or_fee_per_semester'],
                2,
            );
        }

        $attributes['amount'] = $this->cleanAmount($attributes['amount'] ?? 0);

        return $attributes;
    }

    /**
     * Returns the record shape expected by the Add/Edit form (ID-based fields
     * plus resolved school_year/semester pulled from the academic term relation).
     *
     * @return array<string, mixed>
     */
    private function recordForForm(LawSchoolLedger $r): array
    {
        $middleInitial = $this->normalizeMiddleInitial($r->middle_initial);

        return [
            'id' => $r->id,
            'student_id' => $r->student_id_fk,
            'course_id' => $r->course_id,
            'academic_term_id' => $r->academic_term_id,
            'last_name' => $r->last_name,
            'first_name' => $r->first_name,
            'middle_initial' => $middleInitial,
            'middle_name' => $r->middle_name,
            'course' => $r->course,
            'school_year' => optional($r->lawAcademicTerm)->school_year ?? ($r->school_year ?? ''),
            'semester' => optional($r->lawAcademicTerm)->semester ?? $this->normalizeSemester((string) ($r->semester_or_summer ?? '')),
            'semester_or_summer' => $r->semester_or_summer,
            'entry_type' => $r->entry_type ?? 'ar',
            'units' => $r->units,
            'transaction_date' => $r->transaction_date ? (string) $r->transaction_date : '',
            'reference_jev_or_number' => $r->reference_jev_or_number ?? '',
            'particulars' => $r->particulars ?? 'Tuition',
            'tuition_per_unit_or_fee_per_semester' => $r->tuition_per_unit_or_fee_per_semester,
            'ar_or_payment' => $r->ar_or_payment,
            'amount' => $r->amount,
            'status' => $r->status,
            'remarks' => $r->remarks ?? '',
            'input_by' => $r->input_by ?? '',
        ];
    }

    /**
     * Converts entry_type enum value → UI display label.
     */
    private function entryTypeToLabel(?string $entryType): string
    {
        return match ($entryType) {
            'ar' => 'AR',
            'adjustment' => 'Adjustment',
            default => 'Payment',
        };
    }

    /**
     * Strips trailing dots/whitespace from a middle initial.
     */
    private function normalizeMiddleInitial(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $clean = rtrim(trim($value), '.');
        if ($clean === '') {
            return null;
        }
        // Middle initial is conventionally a single letter; preserve longer inputs as-is.
        if (mb_strlen($clean) === 1) {
            return strtoupper($clean);
        }

        return strtoupper($clean);
    }

    /**
     * Clamps an amount value to the PostgreSQL decimal(10,2) max (99,999,999.99)
     * and strips any stray formula prefixes. Mirrors Graduate::cleanAmount().
     */
    private function cleanAmount(mixed $rawAmount): float
    {
        $str = trim((string) ($rawAmount ?? ''));

        if (str_starts_with($str, '=')) {
            $str = ltrim($str, '=');
        }

        $cleaned = (float) preg_replace('/[^\d.\-]/', '', $str);

        if ($cleaned >= 100000000.00) {
            return 99999999.99;
        }
        if ($cleaned <= -100000000.00) {
            return -99999999.99;
        }

        return $cleaned;
    }

    /**
     * Computes charges/payments/outstanding balance from normalized records.
     * Mirrors GraduateLedgerController::calculateStudentBalanceNormalized() — uses
     * the `entry_type` column (positive magnitudes) so callers do not need to know
     * whether payments were stored with a sign.
     *
     * @param  iterable<LawSchoolLedger>  $records
     * @return BalanceSummary
     */
    private function calculateStudentBalanceNormalized(iterable $records): array
    {
        $totalCharges = 0.0;
        $totalPayments = 0.0;

        foreach ($records as $record) {
            $entryType = strtolower((string) ($record->entry_type ?? ''));
            $amount = $this->cleanAmount($record->amount ?? 0);

            if ($entryType === 'ar') {
                $totalCharges += abs($amount);
            } elseif (in_array($entryType, ['payment', 'adjustment'], true)) {
                $totalPayments += abs($amount);
            } else {
                // Legacy rows without an entry_type: fall back to the text label
                $label = strtoupper(trim((string) ($record->ar_or_payment ?? '')));
                if ($label === 'AR' || $label === 'ASSESSMENT') {
                    $totalCharges += abs($amount);
                } else {
                    $totalPayments += abs($amount);
                }
            }
        }

        return [
            'totalCharges' => $totalCharges,
            'totalPayments' => $totalPayments,
            'outstandingBalance' => $totalCharges - $totalPayments,
        ];
    }

    /**
     * @return array{
     *     courses: list<string>,
     *     schoolYears: list<string>,
     *     semesters: list<string>,
     *     statuses: list<string>,
     *     types: list<string>
     * }
     */
    private function getFilterOptions(): array
    {
        $currentYear = (int) date('Y');
        $defaultSchoolYears = [];
        for ($i = $currentYear - 5; $i <= $currentYear + 3; $i++) {
            $defaultSchoolYears[] = $i.'-'.($i + 1);
        }

        $schoolYears = array_values(LawSchoolLedger::distinct()
            ->orderBy('school_year', 'desc')
            ->pluck('school_year')
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->map(fn (mixed $value): string => (string) $value)
            ->values()
            ->all());

        if (empty($schoolYears)) {
            $schoolYears = $defaultSchoolYears;
        }

        return [
            'courses' => array_values(LawSchoolLedger::distinct()
                ->orderBy('course')
                ->pluck('course')
                ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
                ->map(fn (mixed $value): string => (string) $value)
                ->values()
                ->all()),
            'schoolYears' => $schoolYears,
            // Normalize semester labels to a canonical set ("1st Sem", "2nd Sem",
            // "Summer") so equivalent values such as "First Semester" do not appear
            // as separate dropdown options. The canonical labels are always offered
            // even when no records exist yet for that term (e.g. Summer).
            'semesters' => array_values(collect(['1st Sem', '2nd Sem', 'Summer'])
                ->merge(
                    collect($this->deduplicatedOptions('semester_or_summer'))
                        ->map(fn (string $value): string => $this->normalizeSemester($value))
                )
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all()),
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
    /**
     * @param  'ar_or_payment'|'semester_or_summer'|'status'  $column
     * @return list<string>
     */
    private function deduplicatedOptions(string $column): array
    {
        $select = match ($column) {
            'ar_or_payment' => 'ar_or_payment as value, UPPER(TRIM(ar_or_payment)) as option_key, COUNT(*) as option_count',
            'semester_or_summer' => 'semester_or_summer as value, UPPER(TRIM(semester_or_summer)) as option_key, COUNT(*) as option_count',
            'status' => 'status as value, UPPER(TRIM(status)) as option_key, COUNT(*) as option_count',
        };

        return array_values(LawSchoolLedger::query()
            ->selectRaw($select)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->groupBy('value', 'option_key')
            ->get()
            ->groupBy('option_key')
            ->map(function (Collection $variants): string {
                $variant = $variants
                    ->sortByDesc(fn (LawSchoolLedger $record): int => (int) $record->getAttribute('option_count'))
                    ->first();

                return $variant instanceof LawSchoolLedger
                    ? (string) $variant->getAttribute('value')
                    : '';
            })
            ->filter(fn (string $value): bool => $value !== '')
            ->sort()
            ->values()
            ->all());
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
    /** @return list<string> */
    private function semesterAliases(string $semester): array
    {
        return match ($this->normalizeSemester($semester)) {
            '1st Sem' => ['1st Sem', 'First Semester', '1st Semester', 'First Sem'],
            '2nd Sem' => ['2nd Sem', 'Second Semester', '2nd Semester', 'Second Sem'],
            'Summer' => ['Summer', 'Summer Term', 'Summer Semester'],
            default => [trim($semester)],
        };
    }

    // ─── Import Warning Constants ─────────────────────────────────────────────

    const WARNING_NEGATIVE_BLANK_TYPE = 'negative_blank_type';

    const WARNING_NEGATIVE_LABELED_AR = 'negative_labeled_ar';

    const WARNING_PAYMENT_MISSING_PARENTHESES = 'payment_missing_parentheses';

    /** @return WarningCounts */
    private function emptyImportWarnings(): array
    {
        return [
            self::WARNING_NEGATIVE_BLANK_TYPE => 0,
            self::WARNING_NEGATIVE_LABELED_AR => 0,
            self::WARNING_PAYMENT_MISSING_PARENTHESES => 0,
        ];
    }

    /** @param  WarningCounts  $warnings */
    private function importSummary(int $imported, int $skipped, array $warnings): string
    {
        $summary = "Import complete: {$imported} records imported, {$skipped} blank rows skipped.";
        $details = [];

        if ($warnings[self::WARNING_NEGATIVE_BLANK_TYPE] > 0) {
            $details[] = $warnings[self::WARNING_NEGATIVE_BLANK_TYPE]
                .' negative amount(s) with a blank or unknown type were imported as payments';
        }

        if ($warnings[self::WARNING_NEGATIVE_LABELED_AR] > 0) {
            $details[] = $warnings[self::WARNING_NEGATIVE_LABELED_AR]
                .' negative amount(s) labeled AR were imported as payments';
        }

        if ($warnings[self::WARNING_PAYMENT_MISSING_PARENTHESES] > 0) {
            $details[] = $warnings[self::WARNING_PAYMENT_MISSING_PARENTHESES]
                .' positive amount(s) labeled PAYMENT were kept as payments; review their Excel formatting';
        }

        return $details === []
            ? $summary
            : $summary.' Warnings: '.implode('; ', $details).'.';
    }
}
