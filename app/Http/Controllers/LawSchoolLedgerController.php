<?php

namespace App\Http\Controllers;

use App\Exports\LawSchoolLedgerExport;
use App\Http\Requests\StoreLawSchoolLedgerRequest;
use App\Http\Requests\UpdateLawSchoolLedgerRequest;
use App\Mail\LawSchoolLedgerStatementMail;
use App\Models\AcademicTerm as LawAcademicTerm;
use App\Models\ActivityLog;
use App\Models\Course as LawCourse;
use App\Models\LawSchoolLedger;
use App\Models\Student as LawStudent;
use App\Models\User;
use App\Services\LawLedgerImportClassifier;
// use Barryvdh\DomPDF\Facade\Pdf;
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
// use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
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
    private LawLedgerImportClassifier $classifier;

    public function __construct(LawLedgerImportClassifier $classifier)
    {
        $this->classifier = $classifier;
    }

    /**
     * Display the Law School Ledger overview index page.
     */
    public function index(Request $request): Response
    {
        set_time_limit(300);

        $query = $this->buildFilteredQuery($request);

        // 1. Calculate overall metrics using a cloned query BEFORE pagination
        $totalStudents = (clone $query)->reorder()->distinct()->count('student_id');

        // Payments/credits are stored as negative amounts, so compare the transaction
        // type case-insensitively and accumulate payment magnitudes (positive) to keep
        // the overview figures readable. Adjustments are treated as credits.
        $totalAssessments = (float) (clone $query)
            ->where('entry_type', 'ar')
            ->sum('amount');

        $totalPayments = (float) (clone $query)
            ->where('entry_type', 'payment')
            ->sum(DB::raw('ABS(amount)'));

        $totalAdjustments = (float) (clone $query)
            ->where('entry_type', 'adjustment')
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

        $query = LawStudent::query()->whereHas('lawSchoolLedgers');

        if ($search !== '') {
            $searchLower = strtolower($search);
            $prefix = strtolower($search).'%';
            $query->where(function ($q) use ($searchLower) {
                $q->whereRaw('LOWER(last_name) LIKE ?', ["%{$searchLower}%"])
                    ->orWhereRaw('LOWER(first_name) LIKE ?', ["%{$searchLower}%"])
                    ->orWhereRaw('LOWER(middle_name) LIKE ?', ["%{$searchLower}%"])
                    ->orWhereRaw("LOWER(TRIM(CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_name, '')))) LIKE ?", ["%{$searchLower}%"]);
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
            ->get(['last_name', 'first_name', 'middle_name'])
            ->map(function (LawStudent $student): string {
                return trim("$student->last_name, $student->first_name ".($student->middle_name ? substr($student->middle_name, 0, 1) : ''));
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
    public function create(Request $request): Response
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
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'selectedStudentId' => $request->integer('student_id') ?: null,
            'defaultEntryType' => in_array($request->input('entry_type'), ['ar', 'payment', 'adjustment'], true)
                ? $request->input('entry_type')
                : 'ar',
        ]);
    }

    /**
     * Return a student's complete law-ledger history and balance summary.
     */
    public function studentBalance(LawStudent $student): JsonResponse
    {
        $records = LawSchoolLedger::query()
            ->with(['lawStudent', 'lawCourse', 'lawAcademicTerm', 'inputByUser:id,name'])
            ->where('student_id', $student->id)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $latestRecord = $records->last();

        return response()->json([
            'student' => [
                'id' => $student->id,
                'studentNumber' => $student->student_number,
                'name' => $student->full_name,
                'email' => $student->email,
                'contactNumber' => $student->contact_num,
                'course' => $latestRecord?->lawCourse?->code,
            ],
            'summary' => $this->calculateStudentBalanceNormalized($records),
            'transactions' => $records
                ->map(fn (LawSchoolLedger $record) => $this->transformRecord($record))
                ->values(),
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

            $studentId = $studentId !== null ? (int) $studentId : null;

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

            $attribution = $this->resolveInputBy($data['input_by'] ?? null);
            $attributes['input_by'] = $attribution['input_by'];
            $attributes['imported_input_by'] = $attribution['imported_input_by'];

            LawSchoolLedger::create($attributes);
        });

        return redirect()->route('law-ledger.index')->with('success', 'Transaction created successfully.');
    }

    /**
     * Renders the form for editing an existing law ledger transaction.
     */
    public function edit(int $id): Response
    {
        $record = LawSchoolLedger::with(['lawStudent', 'lawCourse', 'lawAcademicTerm', 'inputByUser'])->findOrFail($id);

        return Inertia::render('law-ledger/EditTransaction', [
            'record' => $this->recordForForm($record),
            'students' => $this->studentList(),
            'courses' => $this->courseList(),
            'academicTerms' => $this->academicTermList(),
            'filterOptions' => $this->getFilterOptions(),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
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
                'input_by' => $record->inputByDisplay(),
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

            // Resolve input_by and imported_input_by from user input
            $attribution = $this->resolveInputBy($data['input_by'] ?? null);
            $attributes['input_by'] = $attribution['input_by'];
            $attributes['imported_input_by'] = $attribution['imported_input_by'];

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
            'preset_course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'preset_academic_term_id' => ['nullable', 'integer', 'exists:academic_terms,id'],
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
        $duplicates = 0;
        $warnings = $this->emptyImportWarnings();
        $now = now();

        $presetCourseId = $request->input('preset_course_id') ? (int) $request->input('preset_course_id') : null;
        $presetTermId = $request->input('preset_academic_term_id') ? (int) $request->input('preset_academic_term_id') : null;

        if ($extension === 'csv') {
            return $this->importCsv($path, $imported, $skipped, $warnings, $now, $presetCourseId, $presetTermId, $duplicates);
        }

        return $this->importExcel($path, $imported, $skipped, $warnings, $now, $presetCourseId, $presetTermId, $duplicates);
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
    private function importCsv(
        string $path,
        int &$imported,
        int &$skipped,
        array &$warnings,
        CarbonInterface $now,
        ?int $presetCourseId,
        ?int $presetTermId,
        int &$duplicates
    ): RedirectResponse {
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

            $code = trim((string) (Arr::get($rowData, 'course') ?? Arr::get($rowData, 'program') ?? ''));
            if ($code !== '') {
                $distinctCourses[$code] = true;
            }

            $sy = trim((string) (Arr::get($rowData, 'school_year') ?? Arr::get($rowData, 'academic_year') ?? Arr::get($rowData, 'sy') ?? ''));
            $semRaw = trim((string) (
                Arr::get($rowData, 'semester_or_summer')
                ?? Arr::get($rowData, 'semester_summer')
                ?? Arr::get($rowData, 'semester')
                ?? Arr::get($rowData, 'term')
                ?? ''
            ));
            if ($sy !== '' && $semRaw !== '') {
                $sem = LawAcademicTerm::normalizeSemester($semRaw);
                $distinctTerms["{$sy}|||{$sem}"] = ['school_year' => $sy, 'semester' => $sem];
            }

            $lastName = trim((string) Arr::get($rowData, 'last_name', ''));
            $firstName = trim((string) Arr::get($rowData, 'first_name', ''));
            $middleInitial = trim((string) (Arr::get($rowData, 'middle_initial') ?? Arr::get($rowData, 'middle_name') ?? ''));
            $studentNumber = $this->extractImportStudentNumber($rowData);

            if ($lastName !== '' || $firstName !== '') {
                $parsed = [
                    'student_number' => $studentNumber,
                    'last_name' => $lastName,
                    'first_name' => $firstName,
                    'middle_name' => $middleInitial !== '' ? rtrim($middleInitial, '.') : null,
                ];
            } else {
                $rawName = Arr::get($rowData, 'name_last_name_first_name_m_i')
                    ?? Arr::get($rowData, 'name_last_name_first_name_mi')
                    ?? Arr::get($rowData, 'student_name')
                    ?? Arr::get($rowData, 'student')
                    ?? Arr::get($rowData, 'name')
                    ?? Arr::get($rowData, 'full_name');
                $rawName = is_string($rawName) ? trim(str_replace(['−', '–', '—'], '-', $rawName)) : '';
                if ($rawName !== '' && ! in_array(strtolower($rawName), ['name (last name, first name, m.i.)', 'student name', 'student', 'name', 'last name', 'first name'])) {
                    $parsed = LawStudent::parseRawName($rawName);
                    $parsed['student_number'] = $studentNumber;
                } else {
                    $parsed = null;
                }
            }

            if ($parsed !== null && ! empty($parsed['last_name'])) {
                $k = $this->studentImportKey($parsed['last_name'], $parsed['first_name'], $parsed['middle_name']);
                $distinctStudents[$k] = $parsed;
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
                $resolved = $this->resolveImportRowFks($data, $courseMap, $termMap, $studentMap, $presetCourseId, $presetTermId);
                $resolved['created_at'] = $now;
                $resolved['updated_at'] = $now;
                $insertData[] = $resolved;
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
            ->with('success', $this->importSummary($imported, $skipped, $warnings, $duplicates));
    }

    /**
     * Two-pass memory-efficient Excel importer (xlsx/xls) using PhpSpreadsheet.
     * Same pattern as importCsv() but reads from the in-memory sheet.
     *
     * @param  WarningCounts  $warnings
     *
     * @param-out WarningCounts $warnings
     */
    private function importExcel(
        string $path,
        int &$imported,
        int &$skipped,
        array &$warnings,
        CarbonInterface $now,
        ?int $presetCourseId,
        ?int $presetTermId,
        int &$duplicates
    ): RedirectResponse {
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

        $courseIdx = $this->headerIndex($headerRow, ['course', 'program']);
        $syIdx = $this->headerIndex($headerRow, ['school_year', 'academic_year', 'sy']);
        $semIdx = $this->headerIndex($headerRow, ['semester_or_summer', 'semester_summer', 'semester', 'term']);
        $studentNumberIdx = $this->headerIndex($headerRow, ['student_id_number', 'student_number', 'student_no', 'student_id_no', 'student_id']);
        $lastIdx = $this->headerIndex($headerRow, ['last_name']);
        $firstIdx = $this->headerIndex($headerRow, ['first_name']);
        $miIdx = $this->headerIndex($headerRow, ['middle_initial', 'middle_name']);
        $nameIdx = $this->headerIndex($headerRow, ['name_last_name_first_name_m_i', 'name_last_name_first_name_mi', 'student_name', 'student', 'name', 'full_name']);

        // ── Pass 1: collect distinct values (rows 2..highestRow) ───────────────
        $distinctCourses = [];
        $distinctTerms = [];
        $distinctStudents = [];

        for ($r = 2; $r <= $highestRow; $r++) {
            $code = $courseIdx ? trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($courseIdx).$r)->getValue()) : '';
            if ($code !== '') {
                $distinctCourses[$code] = true;
            }

            $sy = $syIdx ? trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($syIdx).$r)->getValue()) : '';
            $semRaw = $semIdx ? trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($semIdx).$r)->getValue()) : '';
            if ($sy !== '' && $semRaw !== '') {
                $sem = LawAcademicTerm::normalizeSemester($semRaw);
                $distinctTerms["{$sy}|||{$sem}"] = ['school_year' => $sy, 'semester' => $sem];
            }

            $last = $lastIdx ? trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($lastIdx).$r)->getValue()) : '';
            $first = $firstIdx ? trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($firstIdx).$r)->getValue()) : '';
            $mi = $miIdx ? trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($miIdx).$r)->getValue()) : '';
            $studentNumber = $studentNumberIdx
                ? $this->normalizeImportedStudentNumber($this->spreadsheetCellValue($sheet->getCell(Coordinate::stringFromColumnIndex($studentNumberIdx).$r)))
                : null;

            if ($last !== '' || $first !== '') {
                $parsed = [
                    'student_number' => $studentNumber,
                    'last_name' => $last,
                    'first_name' => $first,
                    'middle_name' => $mi !== '' ? rtrim($mi, '.') : null,
                ];
            } elseif ($nameIdx) {
                $rawName = trim(str_replace(['−', '–', '—'], '-', (string) $sheet->getCell(Coordinate::stringFromColumnIndex($nameIdx).$r)->getValue()));
                if ($rawName !== '' && ! in_array(strtolower($rawName), ['name (last name, first name, m.i.)', 'student name', 'student', 'name', 'last name', 'first name'])) {
                    $parsed = LawStudent::parseRawName($rawName);
                    $parsed['student_number'] = $studentNumber;
                } else {
                    $parsed = null;
                }
            } else {
                $parsed = null;
            }

            if ($parsed !== null && ! empty($parsed['last_name'])) {
                $k = $this->studentImportKey($parsed['last_name'], $parsed['first_name'], $parsed['middle_name']);
                $distinctStudents[$k] = $parsed;
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
                if ($cell->isFormula()) {
                    if ($key === 'amount') {
                        try {
                            $rowData[$key] = $cell->getCalculatedValue();
                        } catch (\Throwable) {
                            $rowData[$key] = null;
                        }
                    } else {
                        $rowData[$key] = null;
                    }
                } else {
                    $rowData[$key] = $cell->getValue();
                }
            }

            $data = $this->mapImportRow($rowData, $warnings);

            if ($data !== null) {
                $resolved = $this->resolveImportRowFks($data, $courseMap, $termMap, $studentMap, $presetCourseId, $presetTermId);
                $resolved['created_at'] = $now;
                $resolved['updated_at'] = $now;
                $insertData[] = $resolved;
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
            ->with('success', $this->importSummary($imported, $skipped, $warnings, $duplicates));
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
     * Creates guaranteed fallback records (UNASSIGNED course and term) so imports never fail.
     *
     * @param  array<string, true>  $distinctCourses
     * @param  array<string, array{school_year:string, semester:string}>  $distinctTerms
     * @param  array<string, array{student_number?:string|null, last_name:string, first_name:string, middle_name:string|null}>  $distinctStudents
     * @return array{0: array<string, int>, 1: array<string, int>, 2: array<string, int>}
     */
    private function buildImportLookupMaps(
        array $distinctCourses,
        array $distinctTerms,
        array $distinctStudents,
        CarbonInterface $now,
    ): array {
        // Courses - ensure UNASSIGNED exists as fallback
        $courseMap = LawCourse::query()
            ->where('course_college', 'School of Law')
            ->pluck('id', 'course_code')
            ->all();

        // Create UNASSIGNED fallback course if it doesn't exist
        $unassignedCourse = LawCourse::firstOrCreate(
            [
                'course_code' => 'UNASSIGNED',
                'course_college' => 'School of Law',
            ],
            [
                'course_desc' => 'Unassigned / Pending Course Assignment',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
        $courseMap['__DEFAULT__'] = (int) $unassignedCourse->id;

        $newCourses = [];
        foreach (array_keys($distinctCourses) as $code) {
            if (! isset($courseMap[$code])) {
                $newCourses[] = [
                    'course_code' => $code,
                    'course_desc' => $code,
                    'course_college' => 'School of Law',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        if (! empty($newCourses)) {
            foreach (array_chunk($newCourses, 500) as $chunk) {
                LawCourse::insert($chunk);
            }
            $courseMap = LawCourse::query()
                ->where('course_college', 'School of Law')
                ->pluck('id', 'course_code')
                ->all();
            $courseMap['__DEFAULT__'] = (int) $unassignedCourse->id;
        }

        // Academic terms - ensure fallback exists
        $termsInDb = LawAcademicTerm::get(['id', 'school_year', 'semester'])->toArray();
        $termMap = [];
        foreach ($termsInDb as $t) {
            $termMap["{$t['school_year']}|||{$t['semester']}"] = (int) $t['id'];
        }

        // Create UNASSIGNED fallback term if it doesn't exist
        $unassignedTerm = LawAcademicTerm::firstOrCreate(
            [
                'school_year' => 'Unassigned',
                'semester' => 'First Semester',
            ],
            [
                'created_at' => $now,
                'updated_at' => $now,
            ]
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
            LawAcademicTerm::insert($newTerms);
            $termsInDb = LawAcademicTerm::get(['id', 'school_year', 'semester'])->toArray();
            $termMap = [];
            foreach ($termsInDb as $t) {
                $termMap["{$t['school_year']}|||{$t['semester']}"] = (int) $t['id'];
            }
            $termMap['__DEFAULT__'] = (int) $unassignedTerm->id;
        }

        // Students
        $existingStudents = LawStudent::get(['id', 'student_number', 'last_name', 'first_name', 'middle_name']);
        $studentMap = [];
        $studentsByName = [];
        $studentsByNumber = [];
        foreach ($existingStudents as $s) {
            $this->indexImportStudent($s, $studentsByName, $studentsByNumber);
        }

        $newStudents = [];
        $studentNumberUpdates = [];
        foreach ($distinctStudents as $parsed) {
            $studentNumber = $parsed['student_number'] ?? null;
            $kFull = $this->studentImportKey($parsed['last_name'], $parsed['first_name'], $parsed['middle_name']);
            $kInitial = $parsed['middle_name'] ? $this->studentImportKey($parsed['last_name'], $parsed['first_name'], substr($parsed['middle_name'], 0, 1)) : $kFull;
            $kNoMid = $this->studentImportKey($parsed['last_name'], $parsed['first_name'], null);

            $matchedId = ($studentNumber !== null ? ($studentsByNumber[$studentNumber] ?? null) : null)
                ?? $studentsByName[$kFull]
                ?? $studentsByName[$kInitial]
                ?? $studentsByName[$kNoMid]
                ?? null;

            if ($matchedId !== null) {
                $studentMap[$kFull] = $matchedId;
                $studentMap[$kInitial] = $matchedId;
                $studentMap[$kNoMid] = $matchedId;

                if ($studentNumber !== null && ! isset($studentsByNumber[$studentNumber])) {
                    $studentNumberUpdates[$matchedId] = $studentNumber;
                    $studentsByNumber[$studentNumber] = $matchedId;
                }
            } else {
                $newStudents[] = [
                    'student_number' => $studentNumber,
                    'last_name' => $parsed['last_name'],
                    'first_name' => $parsed['first_name'],
                    'middle_name' => $parsed['middle_name'] ?: null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach ($studentNumberUpdates as $id => $studentNumber) {
            LawStudent::query()
                ->whereKey($id)
                ->whereNull('student_number')
                ->update(['student_number' => $studentNumber, 'updated_at' => $now]);
        }

        if (! empty($newStudents)) {
            foreach (array_chunk($newStudents, 500) as $chunk) {
                LawStudent::insert($chunk);
            }
            $existingStudents = LawStudent::get(['id', 'student_number', 'last_name', 'first_name', 'middle_name']);
            $studentsByName = [];
            $studentsByNumber = [];
            foreach ($existingStudents as $s) {
                $this->indexImportStudent($s, $studentsByName, $studentsByNumber);
            }

            foreach ($distinctStudents as $parsed) {
                $studentNumber = $parsed['student_number'] ?? null;
                $kFull = $this->studentImportKey($parsed['last_name'], $parsed['first_name'], $parsed['middle_name']);
                $kInitial = $parsed['middle_name'] ? $this->studentImportKey($parsed['last_name'], $parsed['first_name'], substr($parsed['middle_name'], 0, 1)) : $kFull;
                $kNoMid = $this->studentImportKey($parsed['last_name'], $parsed['first_name'], null);

                $matchedId = ($studentNumber !== null ? ($studentsByNumber[$studentNumber] ?? null) : null)
                    ?? $studentsByName[$kFull]
                    ?? $studentsByName[$kInitial]
                    ?? $studentsByName[$kNoMid]
                    ?? null;
                if ($matchedId !== null) {
                    $studentMap[$kFull] = $matchedId;
                    $studentMap[$kInitial] = $matchedId;
                    $studentMap[$kNoMid] = $matchedId;
                }
            }
        }

        return [$courseMap, $termMap, $studentMap];
    }

    /**
     * Resolves course_id / academic_term_id / student_id on an import row
     * using the bulk-built lookup maps.
     * Supports preset fields and guaranteed fallback records - never returns null.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, int>  $courseMap
     * @param  array<string, int>  $termMap
     * @param  array<string, int>  $studentMap
     * @return array<string, mixed>
     */
    private function resolveImportRowFks(
        array $data,
        array $courseMap,
        array $termMap,
        array $studentMap,
        ?int $presetCourseId = null,
        ?int $presetTermId = null
    ): array {
        // Course resolution with preset fallback
        $code = trim((string) ($data['course'] ?? ''));
        $courseId = null;

        if ($code !== '' && isset($courseMap[$code])) {
            $courseId = $courseMap[$code];
        } elseif ($presetCourseId !== null) {
            $courseId = $presetCourseId;
        } else {
            // Guaranteed fallback
            $courseId = $courseMap['__DEFAULT__'] ?? null;
        }

        // Term resolution with preset fallback
        $sy = trim((string) ($data['school_year'] ?? ''));
        $semRaw = (string) ($data['semester_or_summer'] ?? '');
        $academicTermId = null;

        if ($sy !== '' && $semRaw !== '') {
            $sem = LawAcademicTerm::normalizeSemester($semRaw);
            $key = "{$sy}|||{$sem}";
            if (isset($termMap[$key])) {
                $academicTermId = $termMap[$key];
                // Promote semester_or_summer to canonical form for consistent filtering
                $data['semester_or_summer'] = $sem;
            }
        }

        if ($academicTermId === null && $presetTermId !== null) {
            $academicTermId = $presetTermId;
        } elseif ($academicTermId === null) {
            // Guaranteed fallback
            $academicTermId = $termMap['__DEFAULT__'] ?? null;
        }

        // Student resolution
        $last = trim((string) ($data['last_name'] ?? ''));
        $first = trim((string) ($data['first_name'] ?? ''));
        $mi = trim((string) ($data['middle_name'] ?? ($data['middle_initial'] ?? '')));

        $studentId = null;

        if ($last !== '' || $first !== '') {
            $kFull = $this->studentImportKey($last, $first, $mi);
            $kInitial = $mi !== '' ? $this->studentImportKey($last, $first, substr($mi, 0, 1)) : $kFull;
            $kNoMid = $this->studentImportKey($last, $first, null);

            $studentId = $studentMap[$kFull] ?? $studentMap[$kInitial] ?? $studentMap[$kNoMid] ?? null;
        }

        // Use classifier for entry_type determination
        $arOrPaymentRaw = $data['ar_or_payment'] ?? 'AR';
        $classification = $this->classifier->classify((string) $arOrPaymentRaw, $data['amount'] ?? 0);
        $entryType = $classification['entry_type'];

        $particulars = trim((string) ($data['particulars'] ?? ''));
        if ($particulars === '') {
            $particulars = 'Tuition';
        }

        $importedInputBy = $this->normalizeImportedInputBy($data['input_by'] ?? null);

        return [
            'student_id' => $studentId,
            'course_id' => $courseId,
            'academic_term_id' => $academicTermId,
            'units' => ($data['units'] ?? 0) > 0 ? (float) $data['units'] : null,
            'rate' => ($data['tuition_per_unit_or_fee_per_semester'] ?? 0) > 0 ? (float) $data['tuition_per_unit_or_fee_per_semester'] : 0,
            'entry_type' => $entryType,
            'amount' => abs((float) ($data['amount'] ?? 0)),
            'transaction_date' => $data['transaction_date'] ?? null,
            'reference_number' => $data['reference_jev_or_number'] ?? null,
            'particulars' => $particulars,
            'remarks' => $data['remarks'] ?? null,
            'status' => $data['status'] ?? 'Pending',
            'input_by' => auth()->id(),
            'imported_input_by' => $importedInputBy ?? auth()->user()?->name ?? 'System Import',
        ];
    }

    /**
     * Renders the React UI for choosing a student and previewing their balance.
     */
    public function printSelect(Request $request): Response
    {
        $students = LawStudent::query()
            ->whereHas('lawSchoolLedgers')
            ->orderBy('last_name', 'asc')
            ->get(['id', 'last_name', 'first_name', 'middle_name'])
            ->map(function ($student) {
                return trim("$student->last_name, $student->first_name ".($student->middle_name ? substr($student->middle_name, 0, 1) : ''));
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
    public function generatePdf(Request $request): PdfBuilder
    {
        set_time_limit(300);

        $validated = $request->validate([
            'student' => ['required_without:student_id', 'string'],
            'student_id' => ['required_without:student', 'integer', 'exists:students,id'],
            'school_year' => ['nullable', 'string', 'max:20'],
            'semester' => ['nullable', 'in:First Semester,Second Semester,Summer'],
            'type' => ['nullable', 'string', 'max:100'],
            'order' => ['nullable', 'in:latest,oldest'],
        ]);

        $sortDirection = ($validated['order'] ?? 'latest') === 'oldest' ? 'asc' : 'desc';
        $studentName = str_replace(['−', '–', '—'], '-', (string) ($validated['student'] ?? $validated['student_id']));
        $recordsQuery = isset($validated['student_id'])
            ? LawSchoolLedger::query()->where('student_id', $validated['student_id'])
            : $this->queryStudentByName($studentName);

        $records = $recordsQuery
            ->when(
                $validated['school_year'] ?? null,
                fn ($query, $schoolYear) => $query->whereHas(
                    'lawAcademicTerm',
                    fn ($termQuery) => $termQuery->where('school_year', $schoolYear),
                ),
            )
            ->orderBy('transaction_date', $sortDirection)
            ->orderBy('id', $sortDirection)
            ->get()
            ->when(
                $validated['semester'] ?? null,
                fn ($records, $semester) => $records->filter(
                    fn (LawSchoolLedger $record) => LawAcademicTerm::normalizeSemester(
                        (string) $record->semester_or_summer,
                    ) === $semester,
                )->values(),
            );

        if (filled($validated['type'] ?? null)) {
            $records = $records
                ->filter(fn (LawSchoolLedger $record) => $record->ar_or_payment === $validated['type'])
                ->values();
        }

        $studentObj = null;
        if (isset($validated['student_id'])) {
            $studentObj = LawStudent::query()->find($validated['student_id']);
        }

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

        $pdf = Pdf::view('pdf.law-student-ledger-statement', [
            'student' => $studentObj,
            'studentName' => $studentName,
            'records' => $records,
            'summary' => $summary,
            'semesterLabel' => $validated['semester'] ?? 'All Terms',
            'generatedAt' => now()->timezone('Asia/Manila')->format('Y-m-d h:i A'),
            // 'logoDataUri' => $logoDataUri,
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

        // $filename = $pdf->stream("Statement_of_Account_{$studentName}.pdf");
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

        $balances = DB::table('law_school_ledgers')
            ->whereIn('student_id', $studentIds)
            ->select('student_id')
            ->selectRaw("SUM(CASE WHEN LOWER(TRIM(entry_type)) = 'ar' THEN amount WHEN LOWER(TRIM(entry_type)) IN ('payment','adjustment') THEN -amount ELSE 0 END) as balance")
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        $students = LawStudent::whereIn('id', $studentIds)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('last_name')
            ->get();

        return response()->json(
            $students->map(function (LawStudent $student) use ($balances): array {
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

        $students = LawStudent::whereIn('id', $studentIds)
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
                new LawSchoolLedgerStatementMail(
                    $student,
                    $pdfContent,
                    $validated['subject'] ?? null,
                    $validated['note'] ?? null,
                    $validated['exam_period'] ?? null,
                    $validated['exam_deadline'] ?? null,
                )
            );

            $sent++;
        }

        return back()->with('success', "Emailed SOA to {$sent} student(s).".($skipped > 0 ? " {$skipped} student(s) skipped (no email)." : ''));
    }

    /**
     * Generates raw PDF content bytes for a single law student's
     * statement of account, reusing the same view and data as generatePdf().
     */
    private function generateStudentPdfContent(int $studentId, ?string $schoolYear, ?string $semester): string
    {
        $recordsQuery = LawSchoolLedger::query()
            ->with(['lawStudent', 'lawCourse', 'lawAcademicTerm'])
            ->where('student_id', $studentId);

        $records = $recordsQuery
            ->when(
                $schoolYear,
                fn ($query, $sy) => $query->whereHas(
                    'lawAcademicTerm',
                    fn ($termQuery) => $termQuery->where('school_year', $sy),
                ),
            )
            ->orderBy('id', 'asc')
            ->get()
            ->when(
                $semester,
                fn ($records, $sem) => $records->filter(
                    fn (LawSchoolLedger $record) => LawAcademicTerm::normalizeSemester(
                        (string) $record->semester_or_summer,
                    ) === $sem,
                )->values(),
            );

        $student = LawStudent::query()->findOrFail($studentId);
        $studentName = trim("{$student->last_name}, {$student->first_name} ".($student->middle_name ? substr($student->middle_name, 0, 1).'.' : ''));

        $summary = $this->calculateStudentBalanceNormalized($records);

        return Pdf::view('pdf.law-student-ledger-statement', [
            'student' => $student,
            'studentName' => $studentName,
            'records' => $records,
            'summary' => $summary,
            'semesterLabel' => $semester ?? 'All Terms',
            'generatedAt' => now()->timezone('Asia/Manila')->format('Y-m-d h:i A'),
        ])
            ->driver('dompdf')
            ->format('a4')
            ->generatePdfContent();
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
        $middleInitial = Arr::get($normalized, 'middle_initial') ?? Arr::get($normalized, 'middle_name');

        if (filled($lastName) || filled($firstName)) {
            $nameParts = [
                'last_name' => is_string($lastName) ? trim($lastName) : (string) $lastName,
                'first_name' => is_string($firstName) ? trim($firstName) : (string) $firstName,
                'middle_name' => is_string($middleInitial) ? rtrim(trim($middleInitial), '.') : null,
                'middle_initial' => is_string($middleInitial) ? rtrim(trim($middleInitial), '.') : null,
            ];
        } else {
            $studentName = Arr::get($normalized, 'name_last_name_first_name_m_i')
                ?? Arr::get($normalized, 'name_last_name_first_name_mi')
                ?? Arr::get($normalized, 'student_name')
                ?? Arr::get($normalized, 'student')
                ?? Arr::get($normalized, 'name')
                ?? Arr::get($normalized, 'full_name');

            $studentName = is_string($studentName) ? trim(str_replace(['−', '–', '—'], '-', $studentName)) : null;

            if (blank($studentName) || in_array(strtolower($studentName), ['name (last name, first name, m.i.)', 'student name', 'student', 'name', 'last name', 'first name'])) {
                return null;
            }

            $parsed = LawStudent::parseRawName($studentName);
            $nameParts = [
                'last_name' => $parsed['last_name'],
                'first_name' => $parsed['first_name'],
                'middle_name' => $parsed['middle_name'],
                'middle_initial' => $parsed['middle_name'],
            ];
        }

        if (blank($nameParts['last_name'])) {
            return null;
        }

        $rawAmount = Arr::get($normalized, 'amount');
        $amount = is_numeric($rawAmount) ? (float) $rawAmount : 0.0;

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

        if (abs($amount) < 0.0001 && $normalizedType === 'AR' && $units > 0 && $tuition > 0) {
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
            'middle_name' => $nameParts['middle_name'],
            'student_number' => $this->extractImportStudentNumber($normalized),
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
            'status' => $this->determineStatus($amount, is_string($rawStatus) && filled($rawStatus) ? trim($rawStatus) : null),
            'remarks' => $remarks,
            'input_by' => $this->extractImportedInputBy($normalized),
        ];
    }

    private function studentImportKey(?string $lastName, ?string $firstName, ?string $middleName): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', implode('|', [
            $lastName,
            $firstName,
            $middleName,
        ])) ?? '');
    }

    /**
     * Spreadsheet apps often export identifier columns as numeric-looking values
     * (for example "202600001.0"). Student numbers are identifiers, not numbers,
     * so normalize only the spreadsheet artifact while preserving meaningful text.
     */
    private function normalizeImportedStudentNumber(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $studentNumber = trim((string) $value);
        if ($studentNumber === '') {
            return null;
        }

        // Broken Excel formulas are not valid identifiers and can exceed the
        // database column length (example: =IFERROR(INDEX(#REF!,...)).
        if (str_starts_with($studentNumber, '=')) {
            return null;
        }

        if (preg_match('/^\d+\.0+$/', $studentNumber) === 1) {
            $studentNumber = preg_replace('/\.0+$/', '', $studentNumber) ?: '';
        }

        if ($studentNumber === '' || strlen($studentNumber) > 50) {
            return null;
        }

        // Student numbers should contain at least one digit. This prevents
        // headers, formula errors, and arbitrary cell text from being persisted.
        if (preg_match('/\d/', $studentNumber) !== 1) {
            return null;
        }

        return $studentNumber;
    }

    private function spreadsheetCellValue(\PhpOffice\PhpSpreadsheet\Cell\Cell $cell): mixed
    {
        if (! $cell->isFormula()) {
            return $cell->getValue();
        }

        try {
            return $cell->getCalculatedValue();
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $rowData */
    private function extractImportStudentNumber(array $rowData): ?string
    {
        return $this->normalizeImportedStudentNumber(
            Arr::get($rowData, 'student_id_number')
            ?? Arr::get($rowData, 'student_number')
            ?? Arr::get($rowData, 'student_no')
            ?? Arr::get($rowData, 'student_id_no')
            ?? Arr::get($rowData, 'student_id')
        );
    }

    /** @param array<string, mixed> $rowData */
    private function extractImportedInputBy(array $rowData): ?string
    {
        return $this->normalizeImportedInputBy(
            Arr::get($rowData, 'input_by')
            ?? Arr::get($rowData, 'input_by_')
            ?? Arr::get($rowData, 'encoded_by')
            ?? Arr::get($rowData, 'prepared_by')
        );
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
     * @param  array<string, int>  $studentsByName
     * @param  array<string, int>  $studentsByNumber
     */
    private function indexImportStudent(LawStudent $student, array &$studentsByName, array &$studentsByNumber): void
    {
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
            ->with(['lawStudent', 'lawCourse', 'lawAcademicTerm', 'inputByUser'])
            ->when($request->input('search'), function ($query, $search) {
                // Lowercase the search term to match the LOWER() applied to columns.
                // PostgreSQL's LIKE is case-sensitive, so "Juan" won't match "juan"
                // unless the search value is also lowercased. This mirrors the
                // pattern used in searchStudents() and StaffInputController::index().
                $search = strtolower((string) $search);
                $query->where(function ($query) use ($search) {
                    $query->whereHas('lawStudent', function ($studentQuery) use ($search) {
                        $studentQuery->whereRaw('LOWER(first_name) LIKE ?', ["%{$search}%"])
                            ->orWhereRaw('LOWER(last_name) LIKE ?', ["%{$search}%"])
                            ->orWhereRaw('LOWER(middle_name) LIKE ?', ["%{$search}%"])
                            ->orWhereRaw('LOWER(student_number) LIKE ?', ["%{$search}%"]);
                    })->orWhereRaw('LOWER(reference_number) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(particulars) LIKE ?', ["%{$search}%"]);
                });
            })
            ->when($schoolYear, function ($query, $schoolYear) {
                $query->whereHas('lawAcademicTerm', fn ($termQuery) => $termQuery->where('school_year', $schoolYear));
            })
            ->when($semester, function ($query, $semester) {
                // Match every stored variant that maps to the selected semester label
                // (e.g. "1st Sem" also matches "First Semester").
                $query->whereHas('lawAcademicTerm', fn ($termQuery) => $termQuery
                    ->where('semester', LawAcademicTerm::normalizeSemester((string) $semester)));
            })
            ->when($course, function ($query, $course) {
                $query->whereHas('lawCourse', fn ($courseQuery) => $courseQuery->where('course_code', $course));
            })
            ->when($status, function ($query, $status) {
                // Trim + case-insensitive match so "DROP" also finds rows stored as
                // " DROP" (the dropdown shows the deduplicated modal label).
                $query->whereRaw('UPPER(TRIM(status)) = ?', [strtoupper(trim((string) $status))]);
            })
            ->when($type, function ($query, $type) {
                $normalizedType = match (strtoupper(trim((string) $type))) {
                    'AR', 'ASSESSMENT' => 'ar',
                    'ADJ', 'ADJUSTMENT' => 'adjustment',
                    default => 'payment',
                };
                $query->where('entry_type', $normalizedType);
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
            ->with(['lawStudent', 'lawCourse', 'lawAcademicTerm'])
            ->whereHas('lawStudent', function ($q) use ($cleanName) {
                $q->whereRaw("TRIM(CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_name, ''))) = ?", [$cleanName])
                    ->orWhereRaw("TRIM(CONCAT(last_name, ', ', first_name)) = ?", [$cleanName])
                    ->orWhere('last_name', 'like', "%{$cleanName}%");
            });
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

    /**
     * Applies the shared Browsershot hardening used by the Chrome-rendered
     * statements. The statement templates are styled entirely with Tailwind via
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

    /** @return array<string, mixed> */
     private function transformRecord(LawSchoolLedger $r): array
     {
         return [
             'id' => $r->id,
             'studentId' => $r->student_id,
             'studentNumber' => $r->lawStudent?->student_number,
            'lastName' => $r->last_name,
            'firstName' => $r->first_name,
            'middleInitial' => $this->normalizeMiddleInitial($r->middle_initial),
            'name' => optional($r->lawStudent)->full_name ?? trim("$r->last_name, $r->first_name ".($r->middle_initial ? "$r->middle_initial" : '')),
            'course' => $r->lawCourse?->code,
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
            'amount' => (float) ($r->amount ?? 0),
            'status' => $r->status,
            'remark' => $r->remarks,
            'inputBy' => $r->inputByDisplay() ?? '',
            'latinHonor' => $r->latin_honor,
            'discountAmount' => (float) ($r->discount_amount ?? 0),
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
        return array_values(LawCourse::where('course_college', 'School of Law')->orderBy('course_code')
            ->get(['id', 'course_code'])
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
            ->orderByRaw("CASE semester WHEN 'First Semester' THEN 1 WHEN 'Second Semester' THEN 2 ELSE 3 END")
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

        $term = LawAcademicTerm::firstOrCreate([
            'school_year' => $data['school_year'],
            'semester' => $semester,
        ]);

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
        $attributes = [
            'student_id' => $studentId,
            'course_id' => $data['course_id'] ?? null,
            'academic_term_id' => $academicTermId,
            'units' => $data['units'] ?? null,
            'transaction_date' => $data['transaction_date'] ?? null,
            'reference_number' => $data['reference_number'] ?? null,
            'particulars' => $data['particulars'] ?? 'Tuition',
            'rate' => $data['rate'] ?? '0.00',
            'entry_type' => $data['entry_type'] ?? 'ar',
            'amount' => $data['amount'] ?? null,
            'remarks' => $data['remarks'] ?? null,
            'status' => $data['status'] ?? 'Active',
            'input_by' => $data['input_by'] ?? null,
        ];

        $entryType = $attributes['entry_type'];

        // Auto-compute amount when AR and no amount supplied (mirrors Graduate behavior)
        if ($entryType === 'ar' && blank($attributes['amount'])) {
            $attributes['amount'] = round(
                (float) ($attributes['units'] ?? 0) * (float) $attributes['rate'],
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
            'course' => $r->lawCourse?->code,
            'school_year' => optional($r->lawAcademicTerm)->school_year ?? ($r->school_year ?? ''),
            'semester' => optional($r->lawAcademicTerm)->semester ?? $this->normalizeSemester((string) ($r->semester_or_summer ?? '')),
            'semester_or_summer' => $r->semester_or_summer,
            'entry_type' => $r->entry_type ?? 'ar',
            'units' => $r->units,
            'transaction_date' => $r->transaction_date ? $r->transaction_date->format('Y-m-d') : '',
            'reference_jev_or_number' => $r->reference_jev_or_number ?? '',
            'particulars' => $r->particulars ?? 'Tuition',
            'tuition_per_unit_or_fee_per_semester' => $r->tuition_per_unit_or_fee_per_semester,
            'ar_or_payment' => $r->ar_or_payment,
            'amount' => $r->amount,
            'status' => $r->status,
            'remarks' => $r->remarks ?? '',
            'input_by' => $r->inputByDisplay() ?? '',
        ];
    }

    /**
     * Resolves user attribution from form input into [input_by (FK), imported_input_by (string)].
     *
     * @return array{input_by: int|null, imported_input_by: string|null}
     */
    private function resolveInputBy(?string $inputBy): array
    {
        $input = trim((string) $inputBy);

        if ($input === '') {
            return [
                'input_by' => null,
                'imported_input_by' => null,
            ];
        }

        // If numeric ID given, check if User exists with that ID
        if (ctype_digit($input)) {
            $user = User::find((int) $input);
            if ($user) {
                return [
                    'input_by' => $user->id,
                    'imported_input_by' => null,
                ];
            }
        }

        // Check if a User exists with this exact name (case-insensitive)
        $user = User::query()
            ->whereRaw('LOWER(name) = ?', [strtolower($input)])
            ->first();

        if ($user) {
            return [
                'input_by' => $user->id,
                'imported_input_by' => null,
            ];
        }

        // If no matching User found, store as text attribution
        return [
            'input_by' => null,
            'imported_input_by' => $input,
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

        $schoolYears = array_values(LawAcademicTerm::distinct()
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
            'courses' => array_values(LawCourse::where('course_college', 'School of Law')
                ->orderBy('course_code')
                ->pluck('course_code')
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
            'types' => LawSchoolLedger::query()->distinct()->pluck('entry_type')
                ->map(fn (string $type): string => $this->entryTypeToLabel($type))->values()->all(),
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
        if ($column === 'semester_or_summer') {
            return LawAcademicTerm::query()->distinct()->pluck('semester')->filter()->values()->all();
        }

        if ($column === 'ar_or_payment') {
            return LawSchoolLedger::query()->distinct()->pluck('entry_type')
                ->map(fn (string $type): string => $this->entryTypeToLabel($type))->values()->all();
        }

        $select = match ($column) {
            'status' => 'status as value, UPPER(TRIM(status)) as option_key, COUNT(*) as option_count',
            default => throw new \InvalidArgumentException("Unsupported option column: {$column}"),
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
    private function importSummary(int $imported, int $skipped, array $warnings, int $duplicates = 0): string
    {
        $parts = ["{$imported} records imported"];

        if ($skipped > 0) {
            $parts[] = "{$skipped} blank rows skipped";
        }

        if ($duplicates > 0) {
            $parts[] = "{$duplicates} duplicates skipped";
        }

        $summary = 'Import complete: '.implode(', ', $parts).'.';
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

    /**
     * Create a file-side fingerprint for a raw import row (before FK resolution).
     * Uses unit separator (\x1F) to avoid collisions from names containing pipes.
     * Returns null if the row lacks essential identifying data.
     *
     * @param  array<string, mixed>  $row
     */
    private function headerlessIdentity(array $row): ?string
    {
        $studentId = $row['student_id'] ?? null;
        $lastName = trim((string) ($row['last_name'] ?? ''));
        $firstName = trim((string) ($row['first_name'] ?? ''));
        $course = trim((string) ($row['course'] ?? ''));
        $schoolYear = trim((string) ($row['school_year'] ?? ''));
        $semester = trim((string) ($row['semester_or_summer'] ?? ''));
        $amount = $row['amount'] ?? null;
        $date = $row['transaction_date'] ?? null;

        // Must have student identifier
        if ($studentId === null && $lastName === '' && $firstName === '') {
            return null;
        }

        // Must have core transaction data
        if ($amount === null || $schoolYear === '' || $semester === '') {
            return null;
        }

        $parts = [
            $studentId ?? '',
            strtolower($lastName),
            strtolower($firstName),
            strtolower($course),
            $schoolYear,
            $semester,
            (string) $amount,
            (string) $date,
        ];

        return implode("\x1F", $parts);
    }

    /**
     * Create a DB-side fingerprint for a resolved ledger row.
     * Must match the structure of headerlessIdentity().
     *
     * @param  array<string, mixed>  $data
     */
    private function ledgerFingerprint(array $data): string
    {
        $parts = [
            $data['student_id'] ?? '',
            '', // last_name not stored in resolved data
            '', // first_name not stored in resolved data
            '', // course code not directly in resolved data
            '', // school_year pulled from academic_term_id
            '', // semester pulled from academic_term_id
            (string) ($data['amount'] ?? ''),
            (string) ($data['transaction_date'] ?? ''),
        ];

        return implode("\x1F", $parts);
    }

    /**
     * Query existing ledger fingerprints in a single round-trip.
     * Returns a map of fingerprint => count.
     *
     * @param  array<string>  $fileIdentities
     * @param  array<string, int>  $studentMap
     * @return array<string, int>
     */
    private function existingLedgerFingerprints(array $fileIdentities, array $studentMap): array
    {
        if (empty($fileIdentities)) {
            return [];
        }

        // Parse file identities to build WHERE conditions
        $conditions = [];
        foreach ($fileIdentities as $identity) {
            $parts = explode("\x1F", $identity);
            if (count($parts) >= 8) {
                $conditions[] = [
                    'student_id' => $parts[0] !== '' ? (int) $parts[0] : null,
                    'amount' => (float) $parts[6],
                    'transaction_date' => $parts[7] !== '' ? $parts[7] : null,
                ];
            }
        }

        if (empty($conditions)) {
            return [];
        }

        // Query for potential duplicates
        $existing = LawSchoolLedger::query()
            ->select(['student_id', 'academic_term_id', 'amount', 'transaction_date'])
            ->where(function ($query) use ($conditions) {
                foreach ($conditions as $condition) {
                    $query->orWhere(function ($q) use ($condition) {
                        if ($condition['student_id'] !== null) {
                            $q->where('student_id', $condition['student_id']);
                        }
                        $q->where('amount', $condition['amount']);
                        if ($condition['transaction_date'] !== null) {
                            $q->where('transaction_date', $condition['transaction_date']);
                        }
                    });
                }
            })
            ->with('academicTerm')
            ->get();

        // Build fingerprint map
        $fingerprintMap = [];
        foreach ($existing as $record) {
            // Reconstruct fingerprint from DB record
            $fp = implode("\x1F", [
                $record->student_id ?? '',
                '', // last_name
                '', // first_name
                '', // course
                $record->academicTerm?->school_year ?? '',
                $record->academicTerm?->semester ?? '',
                (string) $record->amount,
                (string) $record->transaction_date,
            ]);

            $fingerprintMap[$fp] = ($fingerprintMap[$fp] ?? 0) + 1;
        }

        return $fingerprintMap;
    }

    /**
     * Applies a Latin honor discount to an AR transaction.
     */
    public function applyLatinHonor(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'latin_honor' => ['required', 'in:SUMMA,MAGNA,CUM_LAUDE'],
        ]);

        $record = LawSchoolLedger::findOrFail($id);

        // Only apply to AR entries
        if ($record->entry_type !== 'ar') {
            return back()->with('error', 'Latin honor discounts can only be applied to AR (Assessment) entries.');
        }

        $latinHonor = $validated['latin_honor'];
        $originalAmount = abs((float) $record->amount);

        if ($originalAmount <= 0) {
            return back()->with('error', 'Cannot apply a Latin honor discount to a zero-amount assessment.');
        }

        // Prevent duplicate discount credits for the same assessment. The honor
        // adjustment is the accounting entry that reduces the balance; applying
        // it twice would double-count the discount.
        $existingHonorAdjustment = LawSchoolLedger::query()
            ->where('entry_type', 'adjustment')
            ->where('reference_number', 'like', 'HONOR-%-'.$record->id)
            ->exists();

        if ($existingHonorAdjustment) {
            return back()->with('error', 'A Latin honor discount has already been applied to this assessment.');
        }

        // Calculate discount based on honor type
        $discountPercentage = match ($latinHonor) {
            'SUMMA' => 100,      // 100% discount
            'MAGNA' => 100,      // 100% discount
            'CUM_LAUDE' => 50,   // 50% discount
            default => 0,
        };

        $discountAmount = round(($originalAmount * $discountPercentage) / 100, 2);

        // Keep the original AR amount unchanged for auditability. The separate
        // adjustment transaction is what reduces the student's outstanding balance.
        DB::transaction(function () use ($record, $latinHonor, $discountAmount): void {
            $record->update([
                'latin_honor' => $latinHonor,
                'discount_amount' => $discountAmount,
            ]);

            // Create a corresponding adjustment entry for the discount
            $particulars = match ($latinHonor) {
                'SUMMA' => 'Summa Cum Laude Scholarship (100%)',
                'MAGNA' => 'Magna Cum Laude Scholarship (100%)',
                'CUM_LAUDE' => 'Cum Laude Scholarship (50%)',
                default => 'Latin Honor Scholarship',
            };

            $referencePrefix = match ($latinHonor) {
                'SUMMA' => 'SUM',
                'MAGNA' => 'MAG',
                'CUM_LAUDE' => 'CUM',
                default => 'HON',
            };

            LawSchoolLedger::create([
                'student_id' => $record->student_id,
                'course_id' => $record->course_id,
                'academic_term_id' => $record->academic_term_id,
                'entry_type' => 'adjustment',
                'units' => null,
                'transaction_date' => now()->toDateString(),
                'reference_number' => 'HONOR-'.$referencePrefix.'-'.$record->id,
                'particulars' => $particulars,
                'rate' => 0,
                'amount' => $discountAmount,
                'remarks' => 'Latin honor discount applied to AR #'.$record->id,
                'status' => 'Applied',
                'latin_honor' => $latinHonor,
                'discount_amount' => 0,
                'input_by' => auth()->id(),
            ]);
        });

        $honorName = match ($latinHonor) {
            'SUMMA' => 'Summa Cum Laude',
            'MAGNA' => 'Magna Cum Laude',
            'CUM_LAUDE' => 'Cum Laude',
            default => 'Latin Honor',
        };

        return back()->with('success', "{$honorName} discount of ₱".number_format($discountAmount, 2).' applied successfully.');
    }
}
