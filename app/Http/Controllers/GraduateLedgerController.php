<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\GraduateLedger;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;

class GraduateLedgerController extends Controller
{
    /**
     * Whether the database has been migrated to the normalized schema.
     * Checked once per request using Schema::hasColumn().
     */
    private bool $isNormalized;

    public function __construct()
    {
        $this->isNormalized = Schema::hasColumn('graduate_ledgers', 'student_id');
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    /**
     * Display the main Graduate Ledger overview index page.
     */
    public function index(Request $request): Response
    {
        $schoolYear = $request->input('school_year');
        $semester   = $request->input('semester');
        $course     = $request->input('course');
        $dateFrom   = $request->input('date_from');
        $dateTo     = $request->input('date_to');
        $search     = $request->input('search');

        $query = GraduateLedger::query();

        if ($this->isNormalized) {
            $query->with(['student', 'course', 'academicTerm']);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('student', fn ($sq) => $sq
                        ->where('last_name', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%"))
                      ->orWhereHas('course', fn ($sq) => $sq
                        ->where('code', 'like', "%{$search}%"))
                      ->orWhere('reference_or_jev_number', 'like', "%{$search}%");
                });
            }

            if ($schoolYear) {
                $query->whereHas('academicTerm', fn ($q) => $q->where('school_year', $schoolYear));
            }

            if ($semester) {
                $query->whereHas('academicTerm', fn ($q) => $q
                    ->where('semester_short', $semester)
                    ->orWhere('semester', $semester));
            }

            if ($course) {
                $query->whereHas('course', fn ($q) => $q->where('code', $course));
            }
        } else {
            // Legacy string-column path
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('student_name', 'like', "%{$search}%")
                      ->orWhere('course', 'like', "%{$search}%")
                      ->orWhere('reference_or_jev_number', 'like', "%{$search}%");
                });
            }

            if ($schoolYear) {
                $query->where('school_year', $schoolYear);
            }

            if ($semester) {
                $query->where(function ($q) use ($semester) {
                    $q->where('semester_short', $semester)->orWhere('semester', $semester);
                });
            }

            if ($course) {
                $query->where('course', $course);
            }
        }

        $query
            ->when($dateFrom, fn ($q, $v) => $q->whereDate('transaction_date', '>=', $v))
            ->when($dateTo,   fn ($q, $v) => $q->whereDate('transaction_date', '<=', $v));

        // ── Stats ──────────────────────────────────────────────────────────
        if ($this->isNormalized) {
            $totalStudents = (clone $query)
                ->whereNotNull('student_id')
                ->distinct('student_id')
                ->count('student_id');

            $totalAssessments = (float) (clone $query)
                ->where('entry_type', 'ar')
                ->sum('amount');

            $totalPayments = (float) (clone $query)
                ->whereIn('entry_type', ['payment', 'adjustment'])
                ->sum('amount');
        } else {
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
        }

        $outstandingBalance = $totalAssessments - $totalPayments;

        $records = $query->latest('id')->paginate(15)->withQueryString();
        $records->through(fn ($r) => $this->transformRecord($r));

        return Inertia::render('graduate-ledger/Index', [
            'records'       => $records,
            'filters'       => $request->only(['search', 'school_year', 'semester', 'course', 'date_from', 'date_to']),
            'stats'         => [
                'totalStudents'      => $totalStudents,
                'totalAssessments'   => $totalAssessments,
                'totalPayments'      => $totalPayments,
                'outstandingBalance' => $outstandingBalance,
            ],
            'filterOptions' => $this->getFilterOptions(),
        ]);
    }

    // ─── Create / Store ───────────────────────────────────────────────────────

    /**
     * Renders the form for creating a new ledger transaction.
     */
    public function create(): Response
    {
        return Inertia::render('graduate-ledger/AddTransaction', [
            'students'      => $this->studentList(),
            'courses'       => $this->courseList(),
            'academicTerms' => $this->academicTermList(),
            'authUserName'  => auth()->user()?->name ?? '',
        ]);
    }

    /**
     * Stores a new ledger transaction.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($this->isNormalized) {
            $data = $request->validate([
                'student_id'               => ['required', 'exists:graduate_student,id'],
                'new_student'              => ['nullable', 'array'],
                'new_student.last_name'    => ['required_with:new_student', 'string', 'max:255'],
                'new_student.first_name'   => ['required_with:new_student', 'string', 'max:255'],
                'new_student.middle_name'  => ['nullable', 'string', 'max:255'],
                'course_id'                => ['nullable', 'exists:graduate_course,id'],
                'academic_term_id'         => ['nullable', 'exists:graduate_academic_term,id'],
                'school_year'              => ['nullable', 'string', 'max:20'],
                'semester_short'           => ['nullable', 'string', 'max:20'],
                'entry_type'               => ['nullable', 'in:ar,payment,adjustment'],
                'units'                    => ['nullable', 'numeric', 'min:0'],
                'transaction_date'         => ['nullable', 'date'],
                'reference_or_jev_number'  => ['nullable', 'string', 'max:255'],
                'particulars'              => ['nullable', 'string'],
                'tuition_per_unit_or_misc' => ['nullable', 'numeric', 'min:0'],
                'amount'                   => ['nullable', 'numeric', 'min:0'],
                'remarks'                  => ['nullable', 'string'],
                'input_by'                 => ['nullable', 'string', 'max:255'],
            ]);

            // Inline new-student creation
            if (empty($data['student_id']) && !empty($data['new_student'])) {
                $ns = $data['new_student'];
                $student = Student::firstOrCreate(
                    ['last_name' => $ns['last_name'], 'first_name' => $ns['first_name']],
                    ['middle_name' => $ns['middle_name'] ?? null, 'raw_name_from_csv' => "{$ns['last_name']}, {$ns['first_name']}"]
                );
                $data['student_id'] = $student->id;
            }

            // Resolve academic_term_id from school_year + semester_short if not provided directly
            if (empty($data['academic_term_id']) && !empty($data['school_year']) && !empty($data['semester_short'])) {
                $term = AcademicTerm::firstOrCreate(
                    ['school_year' => $data['school_year'], 'semester_short' => $data['semester_short']],
                    [
                        'semester'   => AcademicTerm::semesterLabel($data['semester_short']),
                        'sort_order' => AcademicTerm::sortOrder($data['semester_short']),
                    ]
                );
                $data['academic_term_id'] = $term->id;
            }

            if (($data['entry_type'] ?? null) === 'ar' && ($data['amount'] === null || $data['amount'] === '')) {
                $data['amount'] = ($data['units'] ?? 0) * ($data['tuition_per_unit_or_misc'] ?? 0);
            }

            unset($data['new_student'], $data['school_year'], $data['semester_short']);
            GraduateLedger::create($data);
        } else {
            // Legacy path — keep writing to string columns
            $data = $request->validate([
                'student_name'             => ['required', 'string', 'max:255'],
                'course'                   => ['nullable', 'string', 'max:255'],
                'school_year'              => ['nullable', 'string', 'max:50'],
                'semester_short'           => ['nullable', 'string', 'max:50'],
                'semester'                 => ['nullable', 'string', 'max:100'],
                'units'                    => ['nullable', 'integer'],
                'transaction_date'         => ['nullable', 'date'],
                'reference_or_jev_number'  => ['nullable', 'string', 'max:255'],
                'particulars'              => ['nullable', 'string'],
                'tuition_per_unit_or_misc' => ['nullable', 'numeric'],
                'ar_payment'               => ['nullable', 'string', 'max:50'],
                'amount'                   => ['nullable', 'numeric'],
                'remarks'                  => ['nullable', 'string'],
                'input_by'                 => ['nullable', 'string', 'max:255'],
            ]);

            if (($data['ar_payment'] ?? null) === 'AR' && ($data['amount'] === null || $data['amount'] === '')) {
                $data['amount'] = ($data['units'] ?? 0) * ($data['tuition_per_unit_or_misc'] ?? 0);
            }

            GraduateLedger::create($data);
        }

        return redirect()->route('graduate-ledger.index');
    }

    // ─── Edit / Update ────────────────────────────────────────────────────────

    /**
     * Shows the edit form for an existing ledger record.
     */
    public function edit(int $id): Response
    {
        $record = $this->isNormalized
            ? GraduateLedger::with(['student', 'course', 'academicTerm'])->findOrFail($id)
            : GraduateLedger::findOrFail($id);

        return Inertia::render('graduate-ledger/EditTransaction', [
            'record'        => $this->recordForForm($record),
            'students'      => $this->studentList(),
            'courses'       => $this->courseList(),
            'academicTerms' => $this->academicTermList(),
            'authUserName'  => auth()->user()?->name ?? '',
        ]);
    }

    /**
     * Updates an existing ledger record.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $record = GraduateLedger::findOrFail($id);

        if ($this->isNormalized) {
            $data = $request->validate([
                'student_id'               => ['required', 'exists:graduate_student,id'],
                'course_id'                => ['nullable', 'exists:graduate_course,id'],
                'academic_term_id'         => ['nullable', 'exists:graduate_academic_term,id'],
                'school_year'              => ['nullable', 'string', 'max:20'],
                'semester_short'           => ['nullable', 'string', 'max:20'],
                'entry_type'               => ['nullable', 'in:ar,payment,adjustment'],
                'units'                    => ['nullable', 'numeric', 'min:0'],
                'transaction_date'         => ['nullable', 'date'],
                'reference_or_jev_number'  => ['nullable', 'string', 'max:255'],
                'particulars'              => ['nullable', 'string'],
                'tuition_per_unit_or_misc' => ['nullable', 'numeric', 'min:0'],
                'amount'                   => ['nullable', 'numeric', 'min:0'],
                'remarks'                  => ['nullable', 'string'],
                'input_by'                 => ['nullable', 'string', 'max:255'],
            ]);

            // Resolve academic_term_id from school_year + semester_short if not provided directly
            if (empty($data['academic_term_id']) && !empty($data['school_year']) && !empty($data['semester_short'])) {
                $term = AcademicTerm::firstOrCreate(
                    ['school_year' => $data['school_year'], 'semester_short' => $data['semester_short']],
                    [
                        'semester'   => AcademicTerm::semesterLabel($data['semester_short']),
                        'sort_order' => AcademicTerm::sortOrder($data['semester_short']),
                    ]
                );
                $data['academic_term_id'] = $term->id;
            }

            if (($data['entry_type'] ?? null) === 'ar' && ($data['amount'] === null || $data['amount'] === '')) {
                $data['amount'] = ($data['units'] ?? 0) * ($data['tuition_per_unit_or_misc'] ?? 0);
            }

            unset($data['school_year'], $data['semester_short']);
        } else {
            $data = $request->validate([
                'student_name'             => ['required', 'string', 'max:255'],
                'course'                   => ['nullable', 'string', 'max:255'],
                'school_year'              => ['nullable', 'string', 'max:50'],
                'semester'                 => ['nullable', 'string', 'max:100'],
                'semester_short'           => ['nullable', 'string', 'max:50'],
                'units'                    => ['nullable', 'integer', 'min:0'],
                'transaction_date'         => ['nullable', 'date'],
                'reference_or_jev_number'  => ['nullable', 'string', 'max:255'],
                'particulars'              => ['nullable', 'string'],
                'tuition_per_unit_or_misc' => ['nullable', 'numeric', 'min:0'],
                'ar_payment'               => ['nullable', 'string', 'max:50'],
                'amount'                   => ['nullable', 'numeric', 'min:0'],
                'remarks'                  => ['nullable', 'string'],
                'input_by'                 => ['nullable', 'string', 'max:255'],
            ]);

            if (($data['ar_payment'] ?? null) === 'AR' && ($data['amount'] === null || $data['amount'] === '')) {
                $data['amount'] = ($data['units'] ?? 0) * ($data['tuition_per_unit_or_misc'] ?? 0);
            }
        }

        $record->update($data);

        return redirect()->route('graduate-ledger.index')->with('success', 'Transaction updated successfully.');
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
                    if (!in_array($extension, ['csv', 'xlsx', 'xls'])) {
                        $fail('The file must be a file of type: csv, xlsx, xls.');
                    }
                },
            ],
        ]);

        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $uploadedFile = $request->file('file');
        $extension    = strtolower($uploadedFile->getClientOriginalExtension());

        $imported   = 0;
        $skipped    = 0;
        $insertData = [];
        $now        = now();

        // Pre-build lookup maps for normalized mode to avoid N+1 per row
        $studentMap  = [];
        $courseMap   = [];
        $termMap     = [];

        if ($extension === 'csv') {
            $path   = $uploadedFile->getRealPath();
            $handle = fopen($path, 'r');
            if (!$handle) {
                return redirect()->route('graduate-ledger.index')
                    ->with('error', 'Could not open the uploaded CSV file.');
            }

            fgetcsv($handle); // skip header

            $rawRows = [];
            while (($row = fgetcsv($handle)) !== false) {
                $rawRows[] = $row;
            }
            fclose($handle);
        } else {
            HeadingRowFormatter::default('none');
            $rows    = Excel::toCollection(null, $uploadedFile)->first() ?? collect();
            $rawRows = $rows->slice(1)->map(fn ($r) => $r->values()->all())->all();
        }

        if ($this->isNormalized) {
            // Build in-memory lookup maps (one pass)
            [$studentMap, $courseMap, $termMap] = $this->buildImportLookupMaps($rawRows);
        }

        foreach ($rawRows as $row) {
            $data = $this->isNormalized
                ? $this->mapImportRowNormalized($row, $studentMap, $courseMap, $termMap)
                : $this->mapImportRow($row);

            if ($data === null) {
                $skipped++;
                continue;
            }

            $data['created_at'] = $now;
            $data['updated_at'] = $now;
            $insertData[]       = $data;

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

        return redirect()->route('graduate-ledger.index')
            ->with('success', "Import complete: {$imported} records imported, {$skipped} blank rows skipped.");
    }

    // ─── Print Select / PDF ───────────────────────────────────────────────────

    /**
     * Renders the React UI for choosing a student and previewing their balance.
     */
    public function printSelect(Request $request): Response
    {
        if ($this->isNormalized) {
            $students = Student::orderBy('last_name')
                ->get(['id', 'last_name', 'first_name', 'middle_name'])
                ->map(fn ($s) => ['id' => $s->id, 'full_name' => $s->full_name]);

            $selectedStudentId = $request->input('student_id') ? (int) $request->input('student_id') : null;

            // Fallback: if a legacy name string was passed instead, resolve to ID
            if (!$selectedStudentId && $request->input('student')) {
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
                'students'        => $students,
                'selectedStudent' => $selectedStudentId,
                'records'         => $studentRecords->map(fn ($r) => $this->transformRecord($r)),
                'summary'         => $balanceSummary,
            ]);
        }

        // Legacy path
        $students = GraduateLedger::query()
            ->whereNotNull('student_name')
            ->where('student_name', '!=', '')
            ->distinct()
            ->orderBy('student_name', 'asc')
            ->pluck('student_name')
            ->map(fn ($name) => ['id' => $name, 'full_name' => $name]);

        $selectedStudent = $request->input('student');
        $studentRecords  = collect();
        $balanceSummary  = ['totalCharges' => 0, 'totalPayments' => 0, 'outstandingBalance' => 0];

        if ($selectedStudent) {
            $studentRecords = GraduateLedger::query()
                ->where('student_name', $selectedStudent)
                ->orderBy('id', 'asc')
                ->get();

            $balanceSummary = $this->calculateStudentBalance($studentRecords);
        }

        return Inertia::render('graduate-ledger/PrintSelect', [
            'students'        => $students,
            'selectedStudent' => $selectedStudent,
            'records'         => $studentRecords->map(fn ($r) => $this->transformRecord($r)),
            'summary'         => $balanceSummary,
        ]);
    }

    /**
     * Generates and streams the PDF statement.
     */
    public function generatePdf(Request $request)
    {
        if ($this->isNormalized) {
            $request->validate(['student_id' => 'required|integer|exists:graduate_student,id']);
            $studentId = (int) $request->input('student_id');
            $student   = Student::findOrFail($studentId);

            $records = GraduateLedger::with(['student', 'course', 'academicTerm'])
                ->where('student_id', $studentId)
                ->orderBy('id', 'asc')
                ->get();

            $summary     = $this->calculateStudentBalanceNormalized($records);
            $studentName = $student->full_name;
        } else {
            $request->validate(['student' => 'required|string']);
            $studentName = str_replace(['−', '–', '—'], '-', (string) $request->input('student'));

            $records     = GraduateLedger::where('student_name', $studentName)->orderBy('id', 'asc')->get();
            $summary     = $this->calculateStudentBalance($records);
        }

        $pdf = Pdf::loadView('pdf.student-ledger-statement', [
            'studentName' => $studentName,
            'records'     => $records,
            'summary'     => $summary,
            'generatedAt' => now()->format('Y-m-d'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);

        $filename = 'Statement_of_Account_' . str_replace(['/', '\\', ' '], '_', $studentName) . '.pdf';

        return $pdf->stream($filename);
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    /**
     * Transforms a GraduateLedger row to the frontend LedgerRecord shape.
     * Works in both legacy and normalized modes — Index.tsx sees the same shape.
     */
    private function transformRecord(GraduateLedger $r): array
    {
        if ($this->isNormalized && $r->relationLoaded('student')) {
            $name       = $r->student?->full_name ?? '';
            $courseCode = $r->course?->code ?? '';
            $schoolYear = $r->academicTerm?->school_year ?? '';
            $semester   = $r->academicTerm?->semester_short ?? $r->academicTerm?->semester ?? '';
            $arPayment  = $this->entryTypeToLabel($r->entry_type);
        } else {
            $rawAmount  = (string) $r->amount;
            $isParens   = str_contains($rawAmount, '(') && str_contains($rawAmount, ')');
            $name       = $r->student_name ?? '';
            $courseCode = $r->course ?? '';
            $schoolYear = $r->school_year ?? '';
            $semester   = $r->semester_short ?: ($r->semester ?? '');
            $arPayment  = $this->normalizeArPaymentType($r->ar_payment, $isParens);
        }

        return [
            'id'                            => $r->id,
            'name'                          => $name,
            'course'                        => $courseCode,
            'schoolYear'                    => $schoolYear,
            'semester'                      => $semester,
            'units'                         => (float) ($r->units ?? 0),
            'transactionDate'               => $r->transaction_date,
            'referenceNo'                   => $r->reference_or_jev_number,
            'particulars'                   => $r->particulars,
            'tuitionPerUnitOrFeePerSemester' => (float) ($r->tuition_per_unit_or_misc ?? 0),
            'arPayment'                     => $arPayment,
            'amount'                        => $this->cleanAmount($r->amount),
            'remark'                        => $r->remarks,
            'inputBy'                       => $r->input_by,
        ];
    }

    /**
     * Returns the record shape expected by the Add/Edit form (ID-based fields).
     */
    private function recordForForm(GraduateLedger $r): array
    {
        if ($this->isNormalized) {
            return [
                'id'                       => $r->id,
                'student_id'               => $r->student_id,
                'course_id'                => $r->course_id,
                'academic_term_id'         => $r->academic_term_id,
                'school_year'              => $r->academicTerm?->school_year ?? '',
                'semester_short'           => $r->academicTerm?->semester_short ?? '1st Sem.',
                'entry_type'               => $r->entry_type ?? 'ar',
                'units'                    => $r->units,
                'transaction_date'         => $r->transaction_date ? (string) $r->transaction_date : '',
                'reference_or_jev_number'  => $r->reference_or_jev_number ?? '',
                'particulars'              => $r->particulars ?? 'Tuition',
                'tuition_per_unit_or_misc' => $r->tuition_per_unit_or_misc,
                'amount'                   => $r->amount,
                'remarks'                  => $r->remarks ?? '',
                'input_by'                 => $r->input_by ?? '',
            ];
        }

        return [
            'id'                       => $r->id,
            'student_name'             => $r->student_name ?? '',
            'course'                   => $r->course ?? '',
            'school_year'              => $r->school_year ?? '',
            'semester_short'           => $r->semester_short ?? '1st Sem.',
            'semester'                 => $r->semester ?? 'First Semester',
            'entry_type'               => $this->arPaymentToEntryType($r->ar_payment),
            'ar_payment'               => $r->ar_payment ?? 'AR',
            'units'                    => $r->units,
            'transaction_date'         => $r->transaction_date ? (string) $r->transaction_date : '',
            'reference_or_jev_number'  => $r->reference_or_jev_number ?? '',
            'particulars'              => $r->particulars ?? 'Tuition',
            'tuition_per_unit_or_misc' => $r->tuition_per_unit_or_misc,
            'amount'                   => $r->amount,
            'remarks'                  => $r->remarks ?? '',
            'input_by'                 => $r->input_by ?? '',
        ];
    }

    /**
     * List of students for the form picker.
     * Returns [{id, last_name, first_name, middle_name}] when normalized,
     * or [{id: name, last_name: name, first_name: '', middle_name: null}] in legacy mode.
     */
    private function studentList(): array
    {
        if ($this->isNormalized) {
            return Student::orderBy('last_name')
                ->get(['id', 'last_name', 'first_name', 'middle_name'])
                ->toArray();
        }

        return GraduateLedger::query()
            ->whereNotNull('student_name')
            ->where('student_name', '!=', '')
            ->distinct()
            ->orderBy('student_name')
            ->pluck('student_name')
            ->map(fn ($n) => ['id' => $n, 'last_name' => $n, 'first_name' => '', 'middle_name' => null])
            ->all();
    }

    /**
     * List of courses for the form picker.
     * Returns [{id, code}].
     */
    private function courseList(): array
    {
        if ($this->isNormalized && Course::exists()) {
            return Course::orderBy('code')->get(['id', 'code'])->toArray();
        }

        // Fallback to hardcoded list
        return collect(Course::allCodes())
            ->map(fn ($code, $i) => ['id' => $code, 'code' => $code])
            ->values()
            ->all();
    }

    /**
     * List of academic terms for the form picker.
     */
    private function academicTermList(): array
    {
        if ($this->isNormalized && AcademicTerm::exists()) {
            return AcademicTerm::orderBy('school_year', 'desc')
                ->orderBy('sort_order')
                ->get(['id', 'school_year', 'semester_short', 'semester'])
                ->toArray();
        }

        return [];
    }

    /**
     * Pre-build student/course/term lookup maps from raw import rows.
     * This avoids one DB query per row on large imports.
     */
    private function buildImportLookupMaps(array $rawRows): array
    {
        $distinctStudents = [];
        $distinctCourses  = [];
        $distinctTerms    = [];

        foreach ($rawRows as $row) {
            $name = trim(str_replace(['−', '–', '—'], '-', (string) ($row[0] ?? '')));
            if ($name !== '') {
                $distinctStudents[$name] = true;
            }
            $code = trim((string) ($row[1] ?? ''));
            if ($code !== '') {
                $distinctCourses[$code] = true;
            }
            $sy  = trim((string) ($row[2] ?? ''));
            $sem = trim((string) ($row[3] ?? ''));
            if ($sy !== '' && $sem !== '') {
                $distinctTerms["{$sy}|||{$sem}"] = ['school_year' => $sy, 'semester_short' => $sem];
            }
        }

        // Bulk resolve students
        $studentMap = [];
        foreach (array_keys($distinctStudents) as $rawName) {
            $parsed  = Student::parseRawName($rawName);
            $student = Student::firstOrCreate(
                ['raw_name_from_csv' => $rawName],
                $parsed
            );
            $studentMap[$rawName] = $student->id;
        }

        // Bulk resolve courses
        $courseMap = [];
        foreach (array_keys($distinctCourses) as $code) {
            $course = Course::firstOrCreate(['code' => $code]);
            $courseMap[$code] = $course->id;
        }

        // Bulk resolve terms
        $termMap = [];
        foreach ($distinctTerms as $key => $pair) {
            $term = AcademicTerm::firstOrCreate(
                ['school_year' => $pair['school_year'], 'semester_short' => $pair['semester_short']],
                [
                    'semester'   => AcademicTerm::semesterLabel($pair['semester_short']),
                    'sort_order' => AcademicTerm::sortOrder($pair['semester_short']),
                ]
            );
            $termMap[$key] = $term->id;
        }

        return [$studentMap, $courseMap, $termMap];
    }

    /**
     * Maps a positional row to normalized (FK-based) DB columns.
     */
    private function mapImportRowNormalized(array $row, array $studentMap, array $courseMap, array $termMap): ?array
    {
        $rawName = trim(str_replace(['−', '–', '—'], '-', (string) ($row[0] ?? '')));
        if ($rawName === '') {
            return null;
        }

        $rawAmount  = (string) ($row[11] ?? '0');
        $rawTuition = (string) ($row[9] ?? '0');
        $code       = trim((string) ($row[1] ?? ''));
        $sy         = trim((string) ($row[2] ?? ''));
        $sem        = trim((string) ($row[3] ?? ''));

        return [
            'student_id'               => $studentMap[$rawName] ?? null,
            'course_id'                => $code !== '' ? ($courseMap[$code] ?? null) : null,
            'academic_term_id'         => ($sy !== '' && $sem !== '') ? ($termMap["{$sy}|||{$sem}"] ?? null) : null,
            'units'                    => is_numeric($row[5] ?? null) ? (float) $row[5] : null,
            'transaction_date'         => $this->normalizeDate($row[6] ?? null),
            'reference_or_jev_number'  => trim((string) ($row[7] ?? '')),
            'particulars'              => trim((string) ($row[8] ?? '')),
            'tuition_per_unit_or_misc' => $this->cleanAmount($rawTuition),
            'entry_type'               => $this->normalizeEntryType(
                (string) ($row[10] ?? ''),
                str_contains($rawAmount, '(') && str_contains($rawAmount, ')')
            ),
            'amount'  => $this->cleanAmount($rawAmount),
            'remarks' => trim((string) ($row[12] ?? '')),
            'input_by' => trim((string) ($row[13] ?? '')),
        ];
    }

    /**
     * Maps a positional CSV/Excel row array to legacy string DB columns.
     */
    private function mapImportRow(array $row): ?array
    {
        $studentName = trim(str_replace(['−', '–', '—'], '-', (string) ($row[0] ?? '')));
        if ($studentName === '') {
            return null;
        }

        $rawAmount  = (string) ($row[11] ?? '0');
        $rawTuition = (string) ($row[9] ?? '0');

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
            'amount'   => $this->cleanAmount($rawAmount),
            'remarks'  => trim((string) ($row[12] ?? '')),
            'input_by' => trim((string) ($row[13] ?? '')),
        ];
    }

    private function getFilterOptions(): array
    {
        $currentYear       = (int) date('Y');
        $defaultSchoolYears = [];
        for ($i = $currentYear - 5; $i <= $currentYear + 3; $i++) {
            $defaultSchoolYears[] = $i . '-' . ($i + 1);
        }

        if ($this->isNormalized) {
            $schoolYears = AcademicTerm::distinct()
                ->orderBy('school_year', 'desc')
                ->pluck('school_year')
                ->filter()
                ->values()
                ->all();

            return [
                'courses'     => Course::orderBy('code')->pluck('code')->filter()->values()->all(),
                'schoolYears' => $schoolYears ?: $defaultSchoolYears,
                'semesters'   => AcademicTerm::distinct()->pluck('semester_short')->filter()->values()->all(),
            ];
        }

        // Legacy
        $schoolYears = GraduateLedger::query()
            ->whereNotNull('school_year')
            ->where('school_year', '!=', '')
            ->distinct()
            ->orderBy('school_year', 'desc')
            ->pluck('school_year')
            ->filter()
            ->values()
            ->all();

        return [
            'courses'     => GraduateLedger::query()->whereNotNull('course')->where('course', '!=', '')->distinct()->orderBy('course')->pluck('course')->filter()->values()->all(),
            'schoolYears' => $schoolYears ?: $defaultSchoolYears,
            'semesters'   => GraduateLedger::query()->whereNotNull('semester_short')->where('semester_short', '!=', '')->distinct()->orderBy('semester_short')->pluck('semester_short')->filter()->values()->all(),
        ];
    }

    // ─── Small utilities ──────────────────────────────────────────────────────

    private function normalizeDate($value): ?string
    {
        if (blank($value)) {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_numeric($value)) {
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

    /** Normalizes raw import type string → legacy display label ('AR'/'Payment'/'Adjustment') */
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

    /** Normalizes raw import type string → new enum value ('ar'/'payment'/'adjustment') */
    private function normalizeEntryType(?string $rawType, bool $isParenthesesNegative): string
    {
        $type = strtoupper(trim($rawType ?? ''));

        if ($type === 'AR') {
            return 'ar';
        }
        if ($type === 'ADJUSTMENT' || $type === 'ADJ') {
            return 'adjustment';
        }
        if (in_array($type, ['PAYMENT', 'P', 'PAYMENR', 'SETTLED']) || $isParenthesesNegative) {
            return 'payment';
        }

        return 'payment';
    }

    /** Converts entry_type enum value → UI display label */
    private function entryTypeToLabel(?string $entryType): string
    {
        return match ($entryType) {
            'ar'         => 'AR',
            'adjustment' => 'Adjustment',
            default      => 'Payment',
        };
    }

    /** Converts legacy ar_payment string → entry_type enum value */
    private function arPaymentToEntryType(?string $arPayment): string
    {
        return match (strtoupper(trim($arPayment ?? ''))) {
            'AR'                            => 'ar',
            'ADJUSTMENT', 'ADJ'             => 'adjustment',
            default                         => 'payment',
        };
    }

    private function cleanAmount($rawAmount): float
    {
        return abs((float) preg_replace('/[^\d.]/', '', (string) $rawAmount));
    }

    private function calculateStudentBalance($records): array
    {
        $totalCharges  = 0;
        $totalPayments = 0;

        foreach ($records as $record) {
            $rawType     = strtoupper(trim($record->ar_payment ?? ''));
            $cleanAmount = $this->cleanAmount($record->amount);

            if ($rawType === 'AR') {
                $totalCharges += $cleanAmount;
            } else {
                $totalPayments += $cleanAmount;
            }
        }

        return [
            'totalCharges'      => $totalCharges,
            'totalPayments'     => $totalPayments,
            'outstandingBalance' => $totalCharges - $totalPayments,
        ];
    }

    private function calculateStudentBalanceNormalized($records): array
    {
        $totalCharges  = 0;
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
            'totalCharges'      => $totalCharges,
            'totalPayments'     => $totalPayments,
            'outstandingBalance' => $totalCharges - $totalPayments,
        ];
    }
}
