<?php

namespace App\Http\Controllers;

use App\Exports\GraduateLedgerExport;
use App\Http\Requests\StoreGraduateLedgerRequest;
use App\Http\Requests\UpdateGraduateLedgerRequest;
use App\Models\AcademicTerm;
use App\Models\ActivityLog;
use App\Models\Course;
use App\Models\GraduateLedger;
use App\Models\Student;
use App\Services\GraduateLedgerImportClassifier;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

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
            ->distinct('student_id')
            ->count('student_id');

        $totalAssessments = (float) (clone $query)
            ->where('entry_type', 'ar')
            ->sum('amount');

        $totalPayments = (float) (clone $query)
            ->where('entry_type', 'payment')
            ->sum('amount');

        $totalAdjustments = (float) (clone $query)
            ->where('entry_type', 'adjustment')
            ->sum('amount');

        $outstandingBalance = $totalAssessments - $totalPayments - $totalAdjustments;

        $records = $query->paginate(15)->withQueryString();
        $records->through(fn ($r) => $this->transformRecord($r));

        return Inertia::render('graduate-ledger/Index', [
            'records' => $records,
            'filters' => $request->only(['search', 'school_year', 'semester', 'course', 'date_from', 'date_to']),
            'stats' => [
                'totalStudents' => $totalStudents,
                'totalAssessments' => $totalAssessments,
                'totalPayments' => $totalPayments,
                'totalAdjustments' => $totalAdjustments,
                'outstandingBalance' => $outstandingBalance,
            ],
            'filterOptions' => $this->getFilterOptions(),
        ]);
    }

    /**
     * Exports filtered records to an Excel file (.xlsx).
     */
    public function export(Request $request)
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
    private function buildFilteredQuery(Request $request)
    {
        $schoolYear = $request->input('school_year');
        $semester = $request->input('semester');
        $course = $request->input('course');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $search = $request->input('search');

        $query = GraduateLedger::query();

        $query->with(['student', 'course', 'academicTerm']);

        if ($search) {
            $term = '%'.strtolower($search).'%';
            $query->where(function ($q) use ($term) {
                $q->whereHas('student', fn ($sq) => $sq
                    ->whereRaw('LOWER(last_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(first_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(raw_name_from_csv) LIKE ?', [$term])
                    ->orWhereRaw("LOWER(CONCAT(last_name, ', ', first_name)) LIKE ?", [$term])
                    ->orWhereRaw("LOWER(CONCAT(first_name, ' ', last_name)) LIKE ?", [$term]))
                    ->orWhereHas('course', fn ($sq) => $sq
                        ->whereRaw('LOWER(code) LIKE ?', [$term]))
                    ->orWhereRaw('LOWER(reference_or_jev_number) LIKE ?', [$term]);
            });
        }

        if ($schoolYear) {
            $query->whereHas('academicTerm', fn ($q) => $q->where('school_year', $schoolYear));
        }

        if ($semester) {
            $query->whereHas('academicTerm', fn ($q) => $q->where('semester', $semester));
        }

        if ($course) {
            $query->whereHas('course', fn ($q) => $q->where('code', $course));
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
                    SELECT 1 FROM graduate_student gs
                    WHERE gs.id = graduate_ledgers.student_id
                    AND (LOWER(gs.last_name) LIKE ? OR LOWER(gs.first_name) LIKE ?)
                ) THEN 0 ELSE 1 END',
                [$prefix, $prefix]
            );
        } else {
            $query->latest('id');
        }

        return $query;
    }

    // ─── Create / Store ───────────────────────────────────────────────────────

    /**
     * Renders the form for creating a new ledger transaction.
     */
    public function create(): Response
    {
        return Inertia::render('graduate-ledger/AddTransaction', [
            'students' => $this->studentList(),
            'courses' => $this->courseList(),
            'academicTerms' => $this->academicTermList(),
            'authUserName' => auth()->user()?->name ?? '',
        ]);
    }

    /**
     * Stores a new ledger transaction.
     */
    public function store(StoreGraduateLedgerRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data): void {
            $studentId = $data['student_id'] ?? null;

            if (! $studentId) {
                $newStudent = $data['new_student'];
                $studentAttributes = [
                    'student_number' => $newStudent['student_number'] ?? null,
                    'last_name' => $newStudent['last_name'],
                    'first_name' => $newStudent['first_name'],
                    'middle_name' => $newStudent['middle_name'] ?? null,
                    'raw_name_from_csv' => "{$newStudent['last_name']}, {$newStudent['first_name']}",
                ];

                $student = filled($studentAttributes['student_number'])
                    ? Student::create($studentAttributes)
                    : Student::firstOrCreate(
                        Arr::only($studentAttributes, ['last_name', 'first_name']),
                        Arr::except($studentAttributes, ['last_name', 'first_name']),
                    );
                $studentId = $student->id;
            }

            GraduateLedger::create($this->ledgerAttributes(
                $data,
                (int) $studentId,
                $this->resolveAcademicTermId($data),
            ));
        });

        return redirect()->route('graduate-ledger.index')->with('success', 'Transaction created successfully.');
    }

    // ─── Edit / Update ────────────────────────────────────────────────────────

    /**
     * Shows the edit form for an existing ledger record.
     */
    public function edit(int $id): Response
    {
        $record = GraduateLedger::with(['student', 'course', 'academicTerm'])->findOrFail($id);

        return Inertia::render('graduate-ledger/EditTransaction', [
            'record' => $this->recordForForm($record),
            'students' => $this->studentList(),
            'courses' => $this->courseList(),
            'academicTerms' => $this->academicTermList(),
            'authUserName' => auth()->user()?->name ?? '',
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
            $record->update($this->ledgerAttributes(
                $data,
                (int) $data['student_id'],
                $this->resolveAcademicTermId($data),
            ));
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
            ['sort_order' => AcademicTerm::sortOrder($data['semester'])]
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
            'reference_or_jev_number',
            'particulars',
            'tuition_per_unit_or_misc',
            'amount',
            'remarks',
            'input_by',
        ]);

        $attributes['student_id'] = $studentId;
        $attributes['academic_term_id'] = $academicTermId;
        $attributes['tuition_per_unit_or_misc'] = $data['tuition_per_unit_or_misc'] ?? '0.00';

        if ($data['entry_type'] === 'ar' && empty($data['amount'])) {
            $attributes['amount'] = round(
                (float) ($data['units'] ?? 0) * (float) $attributes['tuition_per_unit_or_misc'],
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
        ]);

        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        $uploadedFile = $request->file('file');
        $extension = strtolower($uploadedFile->getClientOriginalExtension());
        $imported = 0;
        $skipped = 0;
        $warnings = $this->emptyImportWarnings();
        $now = now();

        if ($extension === 'csv') {
            return $this->importCsv($uploadedFile->getRealPath(), $imported, $skipped, $warnings, $now);
        }

        return $this->importExcel($uploadedFile, $imported, $skipped, $warnings, $now);
    }

    /**
     * Memory-efficient two-pass CSV importer.
     * Pass 1: stream once to collect distinct students/courses/terms, then bulk-resolve lookup maps.
     * Pass 2: stream again row-by-row, map to FK IDs, bulk insert in chunks.
     *
     * @param  array<string, int>  $warnings
     */
    private function importCsv(string $path, int &$imported, int &$skipped, array &$warnings, $now): RedirectResponse
    {
        // Pass 1: collect distinct values without holding rows in memory
        $handle = fopen($path, 'r');
        if (! $handle) {
            return redirect()->route('graduate-ledger.index')
                ->with('error', 'Could not open the uploaded CSV file.');
        }

        fgetcsv($handle); // skip header

        $distinctStudents = [];
        $distinctCourses = [];
        $distinctTerms = [];

        while (($row = fgetcsv($handle)) !== false) {
            $name = trim(str_replace(['−', '–', '—'], '-', (string) ($row[0] ?? '')));
            if ($name !== '' && ! in_array(strtolower($name), ['student name', 'student', 'ff', 'name'])) {
                $distinctStudents[$name] = true;
            }
            $code = trim((string) ($row[1] ?? ''));
            if ($code !== '') {
                $distinctCourses[$code] = true;
            }
            $sy = trim((string) ($row[2] ?? ''));
            $rawSemester = trim((string) ($row[4] ?? '')) ?: trim((string) ($row[3] ?? ''));
            $sem = AcademicTerm::normalizeSemester($rawSemester);
            if ($sy !== '' && $sem !== '') {
                $distinctTerms["{$sy}|||{$sem}"] = ['school_year' => $sy, 'semester' => $sem];
            }
        }
        fclose($handle);

        // Resolve lookup maps from the distinct sets
        [$studentMap, $courseMap, $termMap] = $this->buildImportLookupMaps($distinctStudents, $distinctCourses, $distinctTerms, $now);

        // Pass 2: stream rows again, map to insert array, chunk-insert
        $handle = fopen($path, 'r');
        fgetcsv($handle); // skip header

        $insertData = [];

        while (($row = fgetcsv($handle)) !== false) {
            $data = $this->mapImportRowNormalized($row, $studentMap, $courseMap, $termMap, $warnings);

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

        if (! empty($insertData)) {
            DB::transaction(function () use ($insertData) {
                GraduateLedger::insert($insertData);
            });
            $imported += count($insertData);
        }
        fclose($handle);

        ActivityLog::recordImport(GraduateLedger::class, $imported, 'Graduate Ledger');

        return redirect()->route('graduate-ledger.index')
            ->with('success', $this->importSummary($imported, $skipped, $warnings));
    }

    /**
     * Memory-efficient Excel importer (xlsx/xls) using PhpSpreadsheet DataOnly Reader.
     *
     * @param  array<string, int>  $warnings
     */
    private function importExcel($uploadedFile, int &$imported, int &$skipped, array &$warnings, $now): RedirectResponse
    {
        $path = $uploadedFile->getRealPath();
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

        $distinctStudents = [];
        $distinctCourses = [];
        $distinctTerms = [];

        // Pass 1: Collect distinct values (rows 2 to highestRow)
        for ($r = 2; $r <= $highestRow; $r++) {
            $name = trim(str_replace(['−', '–', '—'], '-', (string) $sheet->getCell("A{$r}")->getValue()));
            if ($name !== '' && ! in_array(strtolower($name), ['student name', 'student', 'ff', 'name'])) {
                $distinctStudents[$name] = true;
            }
            $code = trim((string) $sheet->getCell("B{$r}")->getValue());
            if ($code !== '') {
                $distinctCourses[$code] = true;
            }
            $sy = trim((string) $sheet->getCell("C{$r}")->getValue());
            // Always read Col E (SEMESTER/SUMMER) — the labeled, visible column
            $sem = AcademicTerm::normalizeSemester(
                trim((string) $sheet->getCell("E{$r}")->getValue())
            );
            if ($sy !== '' && $sem !== '') {
                $distinctTerms["{$sy}|||{$sem}"] = ['school_year' => $sy, 'semester' => $sem];
            }
        }

        [$studentMap, $courseMap, $termMap] = $this->buildImportLookupMaps(
            $distinctStudents, $distinctCourses, $distinctTerms, $now
        );

        $insertData = [];

        // Pass 2: Map and chunk-insert rows
        for ($r = 2; $r <= $highestRow; $r++) {
            $row = [
                $sheet->getCell("A{$r}")->getValue(),
                $sheet->getCell("B{$r}")->getValue(),
                $sheet->getCell("C{$r}")->getValue(),
                $sheet->getCell("D{$r}")->getValue(),
                $sheet->getCell("E{$r}")->getValue(),
                $sheet->getCell("F{$r}")->getValue(),
                $sheet->getCell("G{$r}")->getValue(),
                $sheet->getCell("H{$r}")->getValue(),
                $sheet->getCell("I{$r}")->getValue(),
                $sheet->getCell("J{$r}")->getValue(),
                $sheet->getCell("K{$r}")->getValue(),
                $sheet->getCell("L{$r}")->getCalculatedValue(),
                $sheet->getCell("M{$r}")->getValue(),
                $sheet->getCell("N{$r}")->getValue(),
            ];

            $data = $this->mapImportRowNormalized($row, $studentMap, $courseMap, $termMap, $warnings);

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

        if (! empty($insertData)) {
            DB::transaction(function () use ($insertData) {
                GraduateLedger::insert($insertData);
            });
            $imported += count($insertData);
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        ActivityLog::recordImport(GraduateLedger::class, $imported, 'Graduate Ledger');

        return redirect()->route('graduate-ledger.index')
            ->with('success', $this->importSummary($imported, $skipped, $warnings));
    }

    // ─── Print Select / PDF ───────────────────────────────────────────────────

    /**
     * Renders the React UI for choosing a student and previewing their balance.
     */
    public function printSelect(Request $request): Response
    {
        $students = Student::orderBy('last_name')
            ->get(['id', 'last_name', 'first_name', 'middle_name'])
            ->map(fn ($s) => ['id' => $s->id, 'full_name' => $s->full_name]);

        $selectedStudentId = $request->input('student_id') ? (int) $request->input('student_id') : null;

        // Fallback: if a name string was passed instead, resolve to ID
        if (! $selectedStudentId && $request->input('student')) {
            $raw = $request->input('student');
            $selectedStudentId = Student::where('raw_name_from_csv', $raw)->value('id');
        }

        $studentRecords = collect();
        $balanceSummary = ['totalCharges' => 0, 'totalPayments' => 0, 'outstandingBalance' => 0];

        if ($selectedStudentId) {
            $studentRecords = GraduateLedger::with(['student', 'course', 'academicTerm'])
                ->where('student_id', $selectedStudentId)
                ->orderBy('id', 'asc')
                ->get();

            $balanceSummary = $this->calculateStudentBalanceNormalized($studentRecords);
        }

        return Inertia::render('graduate-ledger/PrintSelect', [
            'students' => $students,
            'selectedStudent' => $selectedStudentId,
            'records' => $studentRecords->map(fn ($r) => $this->transformRecord($r)),
            'summary' => $balanceSummary,
        ]);
    }

    /**
     * Generates and streams the PDF statement.
     */
    public function generatePdf(Request $request)
    {
        $request->validate(['student_id' => 'required|integer|exists:graduate_student,id']);
        $studentId = (int) $request->input('student_id');
        $student = Student::findOrFail($studentId);

        $rawRecords = GraduateLedger::with(['student', 'course', 'academicTerm'])
            ->where('student_id', $studentId)
            ->orderBy('id', 'asc')
            ->get();

        $summary = $this->calculateStudentBalanceNormalized($rawRecords);
        $studentName = $student->full_name;
        $records = $rawRecords->map(fn ($r) => (object) $this->transformRecord($r));

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

        $filename = 'Statement_of_Account_'.str_replace(['/', '\\', ' '], '_', $studentName).'.pdf';

        return $pdf->stream($filename);
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    /**
     * Transforms a GraduateLedger row to the frontend LedgerRecord shape.
     * Index.tsx consumes this shape.
     */
    private function transformRecord(GraduateLedger $r): array
    {
        $name = $r->student?->full_name ?? '';
        $courseCode = $r->course?->code ?? '';
        $schoolYear = $r->academicTerm?->school_year ?? '';
        $semester = $r->academicTerm?->semester ?? '';
        $arPayment = $this->entryTypeToLabel($r->entry_type);

        return [
            'id' => $r->id,
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
            'remark' => $r->remarks,
            'inputBy' => $r->input_by,
        ];
    }

    /**
     * Returns the record shape expected by the Add/Edit form (ID-based fields).
     */
    private function recordForForm(GraduateLedger $r): array
    {
        return [
            'id' => $r->id,
            'student_id' => $r->student_id,
            'course_id' => $r->course_id,
            'academic_term_id' => $r->academic_term_id,
            'school_year' => $r->academicTerm?->school_year ?? '',
            'semester' => $r->academicTerm?->semester ?? 'First Semester',
            'entry_type' => $r->entry_type ?? 'ar',
            'units' => $r->units,
            'transaction_date' => $r->transaction_date ? (string) $r->transaction_date : '',
            'reference_or_jev_number' => $r->reference_or_jev_number ?? '',
            'particulars' => $r->particulars ?? 'Tuition',
            'tuition_per_unit_or_misc' => $r->tuition_per_unit_or_misc,
            'amount' => $r->amount,
            'remarks' => $r->remarks ?? '',
            'input_by' => $r->input_by ?? '',
        ];
    }

    /**
     * List of students for the form picker.
     * Returns [{id, student_number, last_name, first_name, middle_name}].
     */
    private function studentList(): array
    {
        $latestCourses = DB::table('graduate_ledgers')
            ->select('student_id', DB::raw('MAX(course_id) as last_course_id'))
            ->whereNotNull('student_id')
            ->whereNotNull('course_id')
            ->groupBy('student_id')
            ->pluck('last_course_id', 'student_id')
            ->toArray();

        return Student::orderBy('last_name')
            ->get(['id', 'student_number', 'last_name', 'first_name', 'middle_name'])
            ->map(fn ($s) => [
                'id' => $s->id,
                'student_number' => $s->student_number,
                'last_name' => $s->last_name,
                'first_name' => $s->first_name,
                'middle_name' => $s->middle_name,
                'last_course_id' => $latestCourses[$s->id] ?? null,
            ])
            ->toArray();
    }

    /**
     * List of courses for the form picker.
     * Returns [{id, code}].
     */
    private function courseList(): array
    {
        return Course::orderBy('code')->get(['id', 'code'])->toArray();
    }

    /**
     * List of academic terms for the form picker.
     */
    private function academicTermList(): array
    {
        return AcademicTerm::orderBy('school_year', 'desc')
            ->orderBy('sort_order')
            ->get(['id', 'school_year', 'semester'])
            ->toArray();
    }

    /**
     * Pre-build student/course/term lookup maps from pre-collected distinct value arrays.
     * Accepts the already-collected distinct names/codes/terms to avoid re-iterating raw rows.
     */
    private function buildImportLookupMaps(
        array $distinctStudents,
        array $distinctCourses,
        array $distinctTerms,
        $now
    ): array {
        // 1. Bulk resolve students (2 queries total instead of N+1)
        $studentMap = Student::pluck('id', 'raw_name_from_csv')->toArray();
        $newStudents = [];
        foreach (array_keys($distinctStudents) as $rawName) {
            if (! isset($studentMap[$rawName])) {
                $parsed = Student::parseRawName($rawName);
                $newStudents[] = array_merge($parsed, [
                    'raw_name_from_csv' => $rawName,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
        if (! empty($newStudents)) {
            foreach (array_chunk($newStudents, 500) as $chunk) {
                Student::insert($chunk);
            }
            $studentMap = Student::pluck('id', 'raw_name_from_csv')->toArray();
        }

        // 2. Bulk resolve courses (2 queries total instead of N+1)
        $courseMap = Course::pluck('id', 'code')->toArray();
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
                Course::insert($chunk);
            }
            $courseMap = Course::pluck('id', 'code')->toArray();
        }

        // 3. Bulk resolve terms (2 queries total instead of N+1)
        $termsInDb = AcademicTerm::get(['id', 'school_year', 'semester'])->toArray();
        $termMap = [];
        foreach ($termsInDb as $t) {
            $termMap["{$t['school_year']}|||{$t['semester']}"] = $t['id'];
        }

        $newTerms = [];
        foreach ($distinctTerms as $key => $pair) {
            if (! isset($termMap[$key])) {
                $newTerms[] = [
                    'school_year' => $pair['school_year'],
                    'semester' => $pair['semester'],
                    'sort_order' => AcademicTerm::sortOrder($pair['semester']),
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
                $termMap["{$t['school_year']}|||{$t['semester']}"] = $t['id'];
            }
        }

        return [$studentMap, $courseMap, $termMap];
    }

    /**
     * Maps a positional row to normalized (FK-based) DB columns.
     */
    private function mapImportRowNormalized(
        array $row,
        array $studentMap,
        array $courseMap,
        array $termMap,
        array &$warnings,
    ): ?array
    {
        $rawName = trim(str_replace(['−', '–', '—'], '-', (string) ($row[0] ?? '')));
        if ($rawName === '' || in_array(strtolower($rawName), ['student name', 'student', 'ff', 'name'])) {
            return null;
        }

        $rawAmount = (string) ($row[11] ?? '0');
        $rawTuition = (string) ($row[9] ?? '0');
        $code = trim((string) ($row[1] ?? ''));
        $sy = trim((string) ($row[2] ?? ''));
        // Col E (index 4) = SEMESTER/SUMMER — the labeled, visible column
        $sem = AcademicTerm::normalizeSemester(trim((string) ($row[4] ?? '')));
        $classification = $this->importClassifier->classify(
            (string) ($row[10] ?? ''),
            $row[11] ?? null,
        );

        if ($classification['warning'] !== null) {
            $warnings[$classification['warning']]++;
        }

        return [
            'student_id' => $studentMap[$rawName] ?? null,
            'course_id' => $code !== '' ? ($courseMap[$code] ?? null) : null,
            'academic_term_id' => ($sy !== '' && $sem !== '' && $this->isValidSemester($sem))
                ? ($termMap["{$sy}|||{$sem}"] ?? null)
                : null,
            'units' => is_numeric($row[5] ?? null) ? (float) $row[5] : null,
            'transaction_date' => $this->normalizeDate($row[6] ?? null),
            'reference_or_jev_number' => trim((string) ($row[7] ?? '')),
            'particulars' => trim((string) ($row[8] ?? '')),
            'tuition_per_unit_or_misc' => $this->cleanAmount($rawTuition),
            'entry_type' => $classification['entry_type'],
            'amount' => $this->cleanAmount($rawAmount),
            'remarks' => $this->cleanRemarks($row[12] ?? null),
            'input_by' => trim((string) ($row[13] ?? '')),
        ];
    }

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
            ->values()
            ->all();

        return [
            'courses' => Course::orderBy('code')->pluck('code')->filter()->values()->all(),
            'schoolYears' => $schoolYears ?: $defaultSchoolYears,
            'semesters' => ['First Semester', 'Second Semester', 'Summer'],
        ];
    }

    // ─── Small utilities ──────────────────────────────────────────────────────

    /** @return array<string, int> */
    private function emptyImportWarnings(): array
    {
        return [
            GraduateLedgerImportClassifier::WARNING_NEGATIVE_BLANK_TYPE => 0,
            GraduateLedgerImportClassifier::WARNING_NEGATIVE_LABELED_AR => 0,
            GraduateLedgerImportClassifier::WARNING_PAYMENT_MISSING_PARENTHESES => 0,
        ];
    }

    /** @param  array<string, int>  $warnings */
    private function importSummary(int $imported, int $skipped, array $warnings): string
    {
        $summary = "Import complete: {$imported} records imported, {$skipped} blank rows skipped.";
        $details = [];

        if ($warnings[GraduateLedgerImportClassifier::WARNING_NEGATIVE_BLANK_TYPE] > 0) {
            $details[] = $warnings[GraduateLedgerImportClassifier::WARNING_NEGATIVE_BLANK_TYPE]
                .' negative amount(s) with a blank or unknown type were imported as payments';
        }

        if ($warnings[GraduateLedgerImportClassifier::WARNING_NEGATIVE_LABELED_AR] > 0) {
            $details[] = $warnings[GraduateLedgerImportClassifier::WARNING_NEGATIVE_LABELED_AR]
                .' negative amount(s) labeled AR were imported as payments';
        }

        if ($warnings[GraduateLedgerImportClassifier::WARNING_PAYMENT_MISSING_PARENTHESES] > 0) {
            $details[] = $warnings[GraduateLedgerImportClassifier::WARNING_PAYMENT_MISSING_PARENTHESES]
                .' positive amount(s) labeled PAYMENT were kept as payments; review their Excel formatting';
        }

        return $details === []
            ? $summary
            : $summary.' Warnings: '.implode('; ', $details).'.';
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

    /**
     * Returns true if the given string looks like a real semester/summer value.
     * Rejects names, numbers-only, and other garbage that occasionally appears
     * in Column D of the Excel due to data-entry errors.
     */
    private function isValidSemester(string $value): bool
    {
        $lower = strtolower(trim($value));
        // Must be short enough to be a semester label (names are typically long)
        if (mb_strlen($value) > 30) {
            return false;
        }
        // Must contain a known semester keyword
        $keywords = ['1st', '2nd', '3rd', 'sem', 'summer', 'summer/intersession', 'intersession'];
        foreach ($keywords as $kw) {
            if (str_contains($lower, $kw)) {
                return true;
            }
        }

        return false;
    }

    private function cleanRemarks($val): ?string
    {
        $str = trim((string) ($val ?? ''));
        if (str_starts_with($str, '=')) {
            return null;
        }

        return $str !== '' ? $str : null;
    }

    private function cleanAmount($rawAmount): float
    {
        $str = trim((string) ($rawAmount ?? ''));

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

    private function calculateStudentBalanceNormalized($records): array
    {
        $totalCharges = 0;
        $totalPayments = 0;

        foreach ($records as $record) {
            $cleanAmount = $this->cleanAmount($record->amount);

            if ($record->entry_type === 'ar') {
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
