<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\GraduateLedgerController;
use App\Http\Controllers\LawSchoolLedgerController;
use App\Jobs\SendGraduateLedgerStatementEmail;
use App\Jobs\SendLawLedgerStatementEmail;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\GraduateLedger;
use App\Models\LawSchoolLedger;
use App\Models\StaffInput;
use App\Models\Student;
use App\Services\LedgerImportResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Spatie\LaravelPdf\PdfBuilder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class LedgerController extends Controller
{
    private function model(string $ledger): string
    {
        abort_unless(in_array($ledger, ['graduate', 'law'], true), 404);

        return $ledger === 'graduate' ? GraduateLedger::class : LawSchoolLedger::class;
    }

    private function college(string $ledger): string
    {
        return $ledger === 'graduate' ? 'Graduate School' : 'School of Law';
    }

    private function buildFilteredQuery(Request $request, string $ledger): Builder
    {
        $model = $this->model($ledger);
        $table = $ledger === 'graduate' ? 'graduate_ledgers' : 'law_school_ledgers';
        $query = $model::query()->with(['student', 'course', 'academicTerm', 'inputByUser:id,name']);

        $caseSql = "SUM(CASE WHEN LOWER(TRIM(balance_rows.entry_type)) = 'ar' THEN ABS(balance_rows.amount) WHEN LOWER(TRIM(balance_rows.entry_type)) IN ('payment','adjustment') THEN -ABS(balance_rows.amount) ELSE 0 END)";

        $balanceStatus = $request->input('balance_status');
        if ($balanceStatus === 'with_balance' || $request->boolean('has_balance')) {
            $query->whereExists(function ($subQuery) use ($caseSql, $table) {
                $subQuery->selectRaw('1')
                    ->from("{$table} as balance_rows")
                    ->whereColumn('balance_rows.student_id', "{$table}.student_id")
                    ->whereColumn('balance_rows.academic_term_id', "{$table}.academic_term_id")
                    ->groupBy('balance_rows.student_id', 'balance_rows.academic_term_id')
                    ->havingRaw("{$caseSql} > 0");
            });
        } elseif ($balanceStatus === 'cleared') {
            $query->whereExists(function ($subQuery) use ($caseSql, $table) {
                $subQuery->selectRaw('1')
                    ->from("{$table} as balance_rows")
                    ->whereColumn('balance_rows.student_id', "{$table}.student_id")
                    ->whereColumn('balance_rows.academic_term_id', "{$table}.academic_term_id")
                    ->groupBy('balance_rows.student_id', 'balance_rows.academic_term_id')
                    ->havingRaw("{$caseSql} <= 0");
            });
        }

        if ($request->filled('search')) {
            $search = mb_strtolower(trim((string) $request->input('search')));
            $term = "%{$search}%";
            $query->where(function ($q) use ($term) {
                $q->whereHas('student', fn ($sq) => $sq
                    ->whereRaw('LOWER(last_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(first_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(student_number) LIKE ?', [$term])
                    ->orWhereRaw("LOWER(CONCAT(last_name, ', ', first_name)) LIKE ?", [$term])
                    ->orWhereRaw("LOWER(CONCAT(first_name, ' ', last_name)) LIKE ?", [$term]))
                    ->orWhereHas('course', fn ($sq) => $sq
                        ->whereRaw('LOWER(course_code) LIKE ?', [$term]))
                    ->orWhereRaw('LOWER(reference_number) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(particulars) LIKE ?', [$term]);
            });
        }

        if ($request->filled('school_year')) {
            $sy = $request->input('school_year');
            $query->whereHas('academicTerm', fn ($q) => $q->where('school_year', $sy));
        }

        if ($request->filled('semester')) {
            $sem = (string) $request->input('semester');
            $query->whereHas('academicTerm', function ($q) use ($sem) {
                $lower = strtolower(trim($sem));
                if (str_contains($lower, '1st') || str_contains($lower, 'first')) {
                    $q->where(fn ($sq) => $sq->where('semester', 'First Semester')->orWhere('semester', '1st Sem'));
                } elseif (str_contains($lower, '2nd') || str_contains($lower, 'second')) {
                    $q->where(fn ($sq) => $sq->where('semester', 'Second Semester')->orWhere('semester', '2nd Sem'));
                } elseif (str_contains($lower, 'summer')) {
                    $q->where(fn ($sq) => $sq->where('semester', 'Summer')->orWhere('semester', 'Midyear'));
                } else {
                    $q->where('semester', $sem);
                }
            });
        }

        if ($request->filled('course')) {
            $course = $request->input('course');
            $query->whereHas('course', fn ($q) => $q->where('course_code', $course));
        }

        if ($request->filled('entry_type')) {
            $query->whereRaw('LOWER(TRIM(entry_type)) = ?', [strtolower(trim((string) $request->input('entry_type')))]);
        }

        if ($request->filled('status')) {
            $query->whereRaw('UPPER(TRIM(status)) = ?', [strtoupper(trim((string) $request->input('status')))]);
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->integer('student_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->input('date_to'));
        }

        return $query;
    }

    private function getFilterOptions(string $ledger): array
    {
        $college = $this->college($ledger);
        $currentYear = (int) date('Y');
        $defaultSchoolYears = [];
        for ($i = $currentYear - 5; $i <= $currentYear + 3; $i++) {
            $defaultSchoolYears[] = $i.'-'.($i + 1);
        }

        $schoolYears = AcademicTerm::distinct()
            ->orderBy('school_year', 'desc')
            ->pluck('school_year')
            ->filter(fn ($v) => is_string($v) && $v !== '')
            ->map(fn ($v) => (string) $v)
            ->values()
            ->all();

        $courses = Course::where('course_college', $college)
            ->orderBy('course_code')
            ->pluck('course_code')
            ->filter(fn ($v) => is_string($v) && $v !== '')
            ->map(fn ($v) => (string) $v)
            ->values()
            ->all();

        $semesters = $ledger === 'graduate'
            ? ['First Semester', 'Second Semester', 'Summer']
            : ['1st Sem', '2nd Sem', 'Summer'];

        return [
            'courses' => array_values($courses),
            'schoolYears' => array_values($schoolYears ?: $defaultSchoolYears),
            'semesters' => $semesters,
            'types' => ['AR', 'Payment', 'Adjustment'],
        ];
    }

    public function index(Request $request, string $ledger): JsonResponse
    {
        $query = $this->buildFilteredQuery($request, $ledger);

        $statsQuery = clone $query;
        $totalStudents = (clone $statsQuery)
            ->whereNotNull('student_id')
            ->reorder()
            ->distinct()
            ->count('student_id');

        $totalAssessments = (float) (clone $statsQuery)
            ->whereRaw("LOWER(TRIM(entry_type)) = 'ar'")
            ->sum(DB::raw('ABS(amount)'));

        $totalPayments = (float) (clone $statsQuery)
            ->whereRaw("LOWER(TRIM(entry_type)) = 'payment'")
            ->sum(DB::raw('ABS(amount)'));

        $totalAdjustments = (float) (clone $statsQuery)
            ->whereRaw("LOWER(TRIM(entry_type)) = 'adjustment'")
            ->sum(DB::raw('ABS(amount)'));

        $outstandingBalance = $totalAssessments - $totalPayments - $totalAdjustments;

        $stats = [
            'totalStudents' => $totalStudents,
            'totalAssessments' => $totalAssessments,
            'totalPayments' => $totalPayments,
            'totalAdjustments' => $totalAdjustments,
            'outstandingBalance' => $outstandingBalance,
        ];

        $p = (clone $query)->latest('id')->paginate(min(max($request->integer('per_page', 15), 1), 100))->withQueryString();
        $termBalances = $this->calculateTermBalancesForRecords($p->getCollection(), $ledger);
        $rows = $p->getCollection()->map(fn ($r) => $this->row($r, $termBalances))->values();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => $p->currentPage(),
                'per_page' => $p->perPage(),
                'total' => $p->total(),
                'last_page' => $p->lastPage(),
            ],
            'links' => [
                'next' => $p->nextPageUrl(),
                'prev' => $p->previousPageUrl(),
            ],
            'stats' => $stats,
            'filterOptions' => $this->getFilterOptions($ledger),
        ]);
    }

    public function store(Request $request, string $ledger): JsonResponse
    {
        $created = [];
        $scholarships = 0;
        if (is_array($request->input('items'))) {
            $items = $request->input('items');
            abort_if(count($items) < 1 || count($items) > 50, 422, 'Items must contain between 1 and 50 transactions.');
            $base = $request->all();
            unset($base['items']);
            DB::transaction(function () use ($ledger, $base, $items, $request, &$created, &$scholarships): void {
                foreach ($items as $item) {
                    $payload = array_merge($base, $item);
                    $record = $this->model($ledger)::create($this->validated(new Request($payload), $ledger));
                    $created[] = $record;

                    if ($this->shouldApplyMembershipOnCreate($ledger, $payload)) {
                        $this->applyMembershipToRecord($record, (string) $payload['membership'], $request->user()?->id);
                        $scholarships++;
                    } elseif ($this->shouldApplyLatinHonorOnCreate($ledger, $payload)) {
                        $this->applyLatinHonorToRecord($record, (string) $payload['latin_honor'], $request->user()?->id);
                        $scholarships++;
                    }
                }
            });

            $message = 'Ledger transactions created successfully.';
            if ($scholarships > 0) {
                $message .= sprintf(' %d scholarship adjustment%s added.', $scholarships, $scholarships === 1 ? '' : 's');
            }

            return response()->json([
                'message' => $message,
                'scholarships_applied' => $scholarships,
                'data' => array_map(fn ($r) => $this->row($r->load(['student', 'course', 'academicTerm'])), $created),
            ], 201);
        }
        $data = $this->validated($request, $ledger);
        $scholarshipApplied = false;
        $record = DB::transaction(function () use ($request, $ledger, $data, &$scholarshipApplied) {
            $studentId = $this->resolveStudentId($data);
            unset($data['new_student']);
            $data['student_id'] = $studentId;

            $record = $this->model($ledger)::create($data);

            if ($this->shouldApplyMembershipOnCreate($ledger, $data)) {
                $this->applyMembershipToRecord($record, (string) $data['membership'], $request->user()?->id);
                $scholarshipApplied = true;
            } elseif ($this->shouldApplyLatinHonorOnCreate($ledger, $data)) {
                $this->applyLatinHonorToRecord($record, (string) $data['latin_honor'], $request->user()?->id);
                $scholarshipApplied = true;
            }

            return $record;
        });

        $message = 'Ledger transaction created successfully.';
        if ($scholarshipApplied) {
            $message .= ' 1 scholarship adjustment added.';
        }

        return response()->json([
            'message' => $message,
            'scholarships_applied' => $scholarshipApplied ? 1 : 0,
            'data' => $this->row($record->refresh()->load(['student', 'course', 'academicTerm'])),
        ], 201);
    }

    public function update(Request $request, int|string $id): JsonResponse
    {
        $ledger = (string) $request->route('ledger');
        $record = $this->model($ledger)::findOrFail((int) $id);
        $record->update($this->validated($request, $ledger, $record->id));

        return response()->json(['message' => 'Ledger transaction updated successfully.', 'data' => $this->row($record->refresh()->load(['student', 'course', 'academicTerm']))]);
    }

    public function show(Request $request, int|string $id): JsonResponse
    {
        $ledger = (string) $request->route('ledger');
        $record = $this->model($ledger)::with(['student', 'course', 'academicTerm', 'inputByUser:id,name'])
            ->findOrFail((int) $id);

        $termBalances = $this->calculateTermBalancesForRecords(collect([$record]), $ledger);

        return response()->json([
            'data' => $this->row($record, $termBalances),
        ]);
    }

    public function cohortStore(Request $request): JsonResponse
    {
        $ledger = (string) $request->route('ledger');
        $rows = $request->input('cohort_rows');
        if (! is_array($rows) || count($rows) < 1 || count($rows) > 500) {
            abort(422, 'cohort_rows must contain between 1 and 500 students.');
        }
        $base = $request->all();
        unset($base['cohort_rows']);
        $scholarships = 0;
        $created = DB::transaction(function () use ($ledger, $base, $rows, $request, &$scholarships) {
            return array_map(function (array $row) use ($ledger, $base, $request, &$scholarships) {
                $payload = array_merge($base, $row);
                $record = $this->model($ledger)::create($this->validated(new Request($payload), $ledger));

                if ($this->shouldApplyMembershipOnCreate($ledger, $payload)) {
                    $this->applyMembershipToRecord($record, (string) $payload['membership'], $request->user()?->id);
                    $scholarships++;
                } elseif ($this->shouldApplyLatinHonorOnCreate($ledger, $payload)) {
                    $this->applyLatinHonorToRecord($record, (string) $payload['latin_honor'], $request->user()?->id);
                    $scholarships++;
                }

                return $record;
            }, $rows);
        });

        $message = 'Cohort ledger transactions created successfully.';
        if ($scholarships > 0) {
            $message .= sprintf(' %d scholarship adjustment%s added.', $scholarships, $scholarships === 1 ? '' : 's');
        }

        return response()->json([
            'message' => $message,
            'created_count' => count($created),
            'scholarships_applied' => $scholarships,
            'data' => array_map(fn ($r) => $this->row($r), $created),
        ], 201);
    }

    public function searchStudents(Request $request): JsonResponse
    {
        $ledger = (string) $request->route('ledger');
        $table  = $ledger === 'graduate' ? 'graduate_ledgers' : 'law_school_ledgers';

        $term  = trim((string) $request->query('q', ''));
        $limit = min(max($request->integer('limit', 50), 1), 100);

        $students = Student::query()
            ->where(function ($q) use ($term) {
                $like = '%'.mb_strtolower($term).'%';
                $q->whereRaw('LOWER(first_name) LIKE ?', [$like])
                  ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                  ->orWhereRaw('LOWER(student_number) LIKE ?', [$like]);
            })
            ->orderBy('last_name')
            ->limit($limit)
            ->get(['id', 'student_number', 'first_name', 'middle_name', 'last_name']);

        $studentIds = $students->pluck('id')->all();
        $courseMap  = [];

        if (count($studentIds) > 0) {
            $latestRowIds = DB::table($table)
                ->select('student_id', DB::raw('MAX(id) as latest_row_id'))
                ->whereIn('student_id', $studentIds)
                ->whereNotNull('course_id')
                ->groupBy('student_id')
                ->pluck('latest_row_id', 'student_id')
                ->toArray();

            if (count($latestRowIds) > 0) {
                $courseMap = DB::table($table)
                    ->whereIn('id', array_values($latestRowIds))
                    ->pluck('course_id', 'student_id')
                    ->toArray();
            }
        }

        return response()->json(['data' => $students->map(fn (Student $s) => [
            'id'             => $s->id,
            'student_number' => $s->student_number,
            'name'           => $s->full_name,
            'last_course_id' => isset($courseMap[$s->id]) ? (int) $courseMap[$s->id] : null,
        ])->values()]);
    }

    public function roster(Request $request): JsonResponse
    {
        $ledger = (string) $request->route('ledger');
        $table = $ledger === 'graduate' ? 'graduate_ledgers' : 'law_school_ledgers';

        $latestCourses = DB::table($table)
            ->select('student_id', DB::raw('MAX(course_id) as last_course_id'))
            ->whereNotNull('student_id')
            ->whereNotNull('course_id')
            ->groupBy('student_id')
            ->pluck('last_course_id', 'student_id')
            ->toArray();

        $students = Student::orderBy('last_name')
            ->get(['id', 'student_number', 'last_name', 'first_name', 'middle_name'])
            ->map(fn (Student $s): array => [
                'id' => $s->id,
                'student_number' => $s->student_number,
                'name' => $s->full_name,
                'last_course_id' => isset($latestCourses[$s->id]) ? (int) $latestCourses[$s->id] : null,
            ])
            ->values();

        return response()->json(['data' => $students]);
    }

    public function roster(Request $request): JsonResponse
    {
        $ledger = (string) $request->route('ledger');
        $relationship = $ledger === 'graduate' ? 'graduateLedgers' : 'lawSchoolLedgers';

        $students = Student::query()
            ->whereHas($relationship)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'student_number', 'email', 'first_name', 'middle_name', 'last_name']);

        return response()->json([
            'data' => $students->map(fn (Student $student) => [
                'id' => $student->id,
                'student_number' => $student->student_number,
                'name' => $student->full_name,
                'email' => $student->email,
            ])->values(),
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $ledger = (string) $request->route('ledger');
        $request->validate(['file' => ['required', 'file', 'mimes:csv,xlsx,xls', 'max:51200'], 'preset_course_id' => ['nullable', 'integer', 'exists:courses,id'], 'preset_academic_term_id' => ['nullable', 'integer', 'exists:academic_terms,id']]);
        $file = $request->file('file');
        if (! $file instanceof UploadedFile) {
            return response()->json(['message' => 'The uploaded ledger file is invalid.'], 422);
        }
        $controller = $ledger === 'graduate' ? app(GraduateLedgerController::class) : app(LawSchoolLedgerController::class);
        $response = $controller->import($request);
        $session = $response->getSession();
        $message = (string) ($session->get('success') ?? $session->get('error') ?? 'Ledger import completed.');
        $result = LedgerImportResult::fromSession($session->get('ledger_import_result'));
        if ($result === null) {
            $result = new LedgerImportResult(0, 0, 0, []);
        }

        return response()->json(
            ['message' => $message] + $result->toArray(),
            $session->has('error') ? 422 : 200,
        );
    }

    public function export(Request $request): BinaryFileResponse
    {
        $ledger = (string) $request->route('ledger');

        if ($ledger === 'law') {
            $request->merge([
                'semester_or_summer' => $request->input('semester'),
                'ar_or_payment' => $request->input('entry_type'),
            ]);

            return app(LawSchoolLedgerController::class)->export($request);
        }

        return app(GraduateLedgerController::class)->export($request);
    }

    public function pdf(Request $request): PdfBuilder
    {
        $ledger = (string) $request->route('ledger');

        return $ledger === 'law'
            ? app(LawSchoolLedgerController::class)->generatePdf($request)
            : app(GraduateLedgerController::class)->generatePdf($request);
    }

    public function emailRecipients(Request $request): JsonResponse
    {
        $ledger = (string) $request->route('ledger');
        $table = $ledger === 'graduate' ? 'graduate_ledgers' : 'law_school_ledgers';

        $studentIds = (clone $this->buildFilteredQuery($request, $ledger))
            ->whereNotNull('student_id')
            ->reorder()
            ->distinct()
            ->pluck('student_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($studentIds === []) {
            return response()->json(['data' => []]);
        }

        $balances = DB::table($table)
            ->whereIn('student_id', $studentIds)
            ->select('student_id')
            ->selectRaw("SUM(CASE WHEN LOWER(TRIM(entry_type)) = 'ar' THEN ABS(amount) WHEN LOWER(TRIM(entry_type)) IN ('payment','adjustment') THEN -ABS(amount) ELSE 0 END) as balance")
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        $students = Student::query()
            ->whereIn('id', $studentIds)
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->orderBy('last_name')
            ->get(['id', 'student_number', 'last_name', 'first_name', 'middle_name', 'email']);

        $data = $students->map(function (Student $student) use ($balances): array {
            $balance = isset($balances[$student->id]) ? (float) $balances[$student->id]->balance : 0.0;

            return [
                'id' => $student->id,
                'student_number' => $student->student_number,
                'name' => $student->full_name,
                'full_name' => $student->full_name,
                'email' => $student->email,
                'balance' => $balance,
                'balance_status' => $balance > 0 ? 'outstanding' : 'settled',
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    public function sendEmails(Request $request): JsonResponse
    {
        // Statement PDFs are rendered one by one with headless Chrome, so a
        // batch can take several minutes. Send synchronously like the web
        // ledger controllers do, and lift PHP's execution ceiling.
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        $ledger = (string) $request->route('ledger');

        $validated = $request->validate([
            'student_ids' => ['nullable', 'array', 'max:500'],
            'student_ids.*' => ['integer', 'exists:students,id'],
            'school_year' => ['nullable', 'string', 'max:20'],
            'semester' => ['nullable', 'string', 'max:50'],
            'semester_or_summer' => ['nullable', 'string', 'max:50'],
            'subject' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'exam_period' => ['nullable', 'in:Midterm,Final'],
            'exam_deadline' => ['nullable', 'date'],
        ]);

        $semester = $validated['semester'] ?? ($validated['semester_or_summer'] ?? null);
        if ($ledger === 'law' && filled($semester)) {
            $normalizedSemester = AcademicTerm::normalizeSemester((string) $semester);
            if ($normalizedSemester === null) {
                $normalizedSemester = match (strtoupper(trim((string) preg_replace('/\s+/', ' ', (string) $semester)))) {
                    '1ST SEM', 'FIRST SEMESTER', '1ST SEMESTER', 'FIRST SEM', '1ST', '1' => 'First Semester',
                    '2ND SEM', 'SECOND SEMESTER', '2ND SEMESTER', 'SECOND SEM', '2ND', '2' => 'Second Semester',
                    'SUMMER', 'SUMMER TERM', 'SUMMER SEMESTER', '3RD SEM', '3RD SEMESTER', 'MIDYEAR' => 'Summer',
                    default => null,
                };
            }
            $semester = $normalizedSemester;
        }

        $studentIds = (clone $this->buildFilteredQuery($request, $ledger))
            ->whereNotNull('student_id')
            ->reorder()
            ->distinct()
            ->pluck('student_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $specificIds = $request->input('student_ids');
        if (is_array($specificIds) && $specificIds !== []) {
            $specific = array_values(array_unique(array_map('intval', $specificIds)));
            $studentIds = array_values(array_intersect($studentIds, $specific));
        }

        if ($studentIds === []) {
            return response()->json([
                'message' => 'No eligible students matched the current filters.',
                'queued_count' => 0,
            ]);
        }

        $eligibleStudentIds = Student::query()
            ->whereIn('id', $studentIds)
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $sentCount = 0;
        $failedCount = 0;

        foreach ($eligibleStudentIds as $id) {
            $job = $ledger === 'law'
                ? new SendLawLedgerStatementEmail(
                    (int) $id,
                    $validated['school_year'] ?? null,
                    $semester,
                    $validated['subject'] ?? null,
                    $validated['note'] ?? null,
                    $validated['exam_period'] ?? null,
                    $validated['exam_deadline'] ?? null,
                )
                : new SendGraduateLedgerStatementEmail(
                    (int) $id,
                    $validated['school_year'] ?? null,
                    $semester ?? ($validated['semester'] ?? null),
                    $validated['subject'] ?? null,
                    $validated['note'] ?? null,
                    $validated['exam_period'] ?? null,
                    $validated['exam_deadline'] ?? null,
                );

            try {
                dispatch_sync($job);
                $sentCount++;
            } catch (Throwable $exception) {
                $failedCount++;
                Log::error('Ledger statement email failed', [
                    'ledger' => $ledger,
                    'student_id' => $id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $message = match (true) {
            $sentCount === 0 && $failedCount === 0 => 'No eligible students with registered emails found.',
            $failedCount === 0 => "Emailed statement of account to {$sentCount} student(s).",
            $sentCount === 0 => "Failed to email all {$failedCount} student(s). Check the logs for details.",
            default => "Emailed statement of account to {$sentCount} student(s); {$failedCount} failed. Check the logs for details.",
        };

        return response()->json([
            'message' => $message,
            // `queued_count` is kept for the existing SPA contract; it now
            // reports successfully sent recipients.
            'queued_count' => $sentCount,
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
        ]);
    }

    public function destroy(Request $request, int|string $id): Response|JsonResponse
    {
        $ledger = (string) $request->route('ledger');
        $this->model($ledger)::findOrFail((int) $id)->delete();

        return response()->noContent();
    }

    public function balance(Request $request): JsonResponse
    {
        $ledger = (string) $request->route('ledger');
        $student = Student::findOrFail((int) $request->route('student'));
        $records = $this->model($ledger)::where('student_id', $student->id)
            ->with(['course', 'academicTerm', 'student'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();

        $charges = 0.0;
        $payments = 0.0;
        $adjustments = 0.0;

        foreach ($records as $record) {
            $entryType = strtolower(trim((string) $record->entry_type));
            $amount = abs((float) $record->amount);

            if ($entryType === 'ar') {
                $charges += $amount;
            } elseif ($entryType === 'payment' || $entryType === 'p') {
                $payments += $amount;
            } elseif (str_contains($entryType, 'adjust')) {
                $adjustments += $amount;
            }
        }

        $termBalances = $this->calculateTermBalancesForRecords($records, $ledger);
        $latestRecord = $records->first();
        $studentCourse = $latestRecord?->course?->course_code
            ?? $latestRecord?->course?->code
            ?? null;

        return response()->json([
            'student' => [
                'id' => $student->id,
                'student_number' => $student->student_number,
                'name' => $student->full_name,
                'email' => $student->email ?? null,
                'contactNumber' => $student->contact_num ?? ($student->contact_number ?? null),
                'course' => $studentCourse,
            ],
            'summary' => [
                'total_charges' => $charges,
                'total_payments' => $payments,
                'total_adjustments' => $adjustments,
                'outstanding_balance' => $charges - $payments - $adjustments,
            ],
            'transactions' => $records->map(fn ($r) => $this->row($r, $termBalances))->values(),
        ]);
    }

    /**
     * Batch calculate net balance for student + academic term pairs.
     *
     * @param  Collection|iterable  $records
     * @return array<string, float> map of "studentId_termId" => netBalance
     */
    private function calculateTermBalancesForRecords($records, ?string $ledger = null): array
    {
        $collection = $records instanceof Collection ? $records : collect($records);
        if ($collection->isEmpty()) {
            return [];
        }

        $table = null;
        if ($ledger) {
            $table = $ledger === 'graduate' ? 'graduate_ledgers' : 'law_school_ledgers';
        } else {
            $first = $collection->first();
            if ($first && method_exists($first, 'getTable')) {
                $table = $first->getTable();
            }
        }

        if (! $table) {
            $table = 'graduate_ledgers';
        }

        $studentIds = $collection->pluck('student_id')->filter()->unique()->values()->all();
        $termIds = $collection->pluck('academic_term_id')->filter()->unique()->values()->all();

        if (empty($studentIds) || empty($termIds)) {
            return [];
        }

        $balances = DB::table($table)
            ->select('student_id', 'academic_term_id')
            ->selectRaw("SUM(CASE WHEN LOWER(TRIM(entry_type)) = 'ar' THEN ABS(amount) WHEN LOWER(TRIM(entry_type)) IN ('payment', 'adjustment') THEN -ABS(amount) ELSE 0 END) as net_balance")
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
     * Whether a fresh AR should receive an inline membership scholarship plus
     * its matching adjustment twin. Membership columns exist on the graduate
     * ledger only, so the law ledger never qualifies.
     */
    private function shouldApplyMembershipOnCreate(string $ledger, array $data): bool
    {
        return $ledger === 'graduate'
            && ($data['entry_type'] ?? null) === 'ar'
            && filled($data['membership'] ?? null)
            && filled($data['discount_amount'] ?? null)
            && (float) $data['discount_amount'] > 0;
    }

    private function shouldApplyLatinHonorOnCreate(string $ledger, array $data): bool
    {
        return $ledger === 'law'
            && ($data['entry_type'] ?? null) === 'ar'
            && in_array($data['latin_honor'] ?? null, ['SUMMA', 'MAGNA', 'CUM_LAUDE'], true);
    }

    /**
     * Tags an AR with the membership scholarship and books its adjustment twin.
     * Mirrors GraduateLedgerController::applyMembershipToRecord() so both paths
     * produce identical rows.
     */
    private function applyMembershipToRecord(GraduateLedger $record, string $membership, ?int $userId): void
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
            'input_by' => $userId,
        ]);
    }

    private function applyLatinHonorToRecord(LawSchoolLedger $record, string $latinHonor, ?int $userId): void
    {
        $percentage = match ($latinHonor) {
            'SUMMA' => 100,
            'MAGNA' => 75,
            'CUM_LAUDE' => 50,
        };
        $discountAmount = round(abs((float) $record->amount) * $percentage / 100, 2);

        if ($discountAmount <= 0) {
            return;
        }

        $referencePrefix = match ($latinHonor) {
            'SUMMA' => 'SUM',
            'MAGNA' => 'MAG',
            'CUM_LAUDE' => 'CUM',
        };
        $particulars = match ($latinHonor) {
            'SUMMA' => 'Summa Cum Laude Scholarship (100%)',
            'MAGNA' => 'Magna Cum Laude Scholarship (75%)',
            'CUM_LAUDE' => 'Cum Laude Scholarship (50%)',
        };

        $record->update([
            'latin_honor' => $latinHonor,
            'discount_amount' => $discountAmount,
        ]);

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
            'input_by' => $userId,
        ]);
    }

    private function validated(Request $request, string $ledger, ?int $ignore = null): array
    {
        $isCreate = $ignore === null;
        $isBatch = is_array($request->input('items'));
        $allowNewStudent = $isCreate && ! $isBatch;
        $data = $request->validate([
            'student_id' => [
                'nullable',
                Rule::requiredIf(fn () => ! $allowNewStudent || ! $request->filled('new_student')),
                'exists:students,id',
            ],
            'new_student' => [
                'nullable',
                'array',
                Rule::prohibitedIf(fn () => ! $allowNewStudent),
            ],
            'new_student.student_number' => ['nullable', 'string', 'max:50', Rule::unique(Student::class, 'student_number')],
            'new_student.email' => ['nullable', 'email:rfc', 'max:255'],
            'new_student.last_name' => ['required_with:new_student', 'string', 'max:255'],
            'new_student.first_name' => ['required_with:new_student', 'string', 'max:255'],
            'new_student.middle_name' => ['nullable', 'string', 'max:255'],
            'course_id' => ['required', Rule::exists('courses', 'id')->where('course_college', $this->college($ledger))],
            'academic_term_id' => ['required', 'exists:academic_terms,id'],
            'entry_type' => ['required', Rule::in(['ar', 'payment', 'adjustment'])],
            'units' => ['nullable', 'numeric', 'min:0'],
            'transaction_date' => ['required', 'date'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'particulars' => ['required', 'string', 'max:255'],
            'rate' => ['required', 'numeric', 'min:0'],
            'amount' => ['required', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:30'],
            'membership' => ['nullable', Rule::in(['NAPU', 'NORSUFFA'])],
            'latin_honor' => ['nullable', Rule::in(['SUMMA', 'MAGNA', 'CUM_LAUDE'])],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
        ], [
            'new_student.student_number.unique' => 'This Student ID is already registered. Select the existing student instead.',
        ]);
        $data['input_by'] = $request->user()?->id;
        $data['status'] ??= $ledger === 'law' ? 'Paid' : 'posted';

        if ($ledger !== 'graduate' || ($data['entry_type'] ?? null) !== 'ar') {
            unset($data['membership']);
        }
        if ($ledger !== 'law' || ($data['entry_type'] ?? null) !== 'ar') {
            unset($data['latin_honor']);
        }
        if (($data['entry_type'] ?? null) !== 'ar') {
            unset($data['discount_amount']);
        }

        return $data;
    }

    /** Resolve an existing student or create one inline for a single transaction. */
    private function resolveStudentId(array $data): int
    {
        if (filled($data['student_id'] ?? null)) {
            return (int) $data['student_id'];
        }

        $newStudent = $data['new_student'];
        $student = Student::create([
            'student_number' => filled($newStudent['student_number'] ?? null)
                ? trim((string) $newStudent['student_number'])
                : null,
            'email' => filled($newStudent['email'] ?? null)
                ? trim((string) $newStudent['email'])
                : null,
            'last_name' => trim((string) $newStudent['last_name']),
            'first_name' => trim((string) $newStudent['first_name']),
            'middle_name' => filled($newStudent['middle_name'] ?? null)
                ? trim((string) $newStudent['middle_name'])
                : null,
        ]);

        return (int) $student->id;
    }

    private function row($r, ?array $termBalances = null): array
    {
        $entryTypeLower = strtolower(trim((string) $r->entry_type));
        $orLink = null;
        if ($entryTypeLower === 'payment' && filled($r->reference_number)) {
            $staffInput = StaffInput::where('or_no', $r->reference_number)
                ->select('id', 'form_input_id')
                ->first();
            if ($staffInput) {
                $orLink = "/staff/requests/{$staffInput->form_input_id}";
            }
        }

        $inputBy = method_exists($r, 'inputByDisplay')
            ? $r->inputByDisplay()
            : ($r->inputByUser?->name ?? (string) ($r->input_by ?? ''));

        $studentNumber = $r->student?->student_number ?? $r->student_number ?? null;

        $studentId = $r->student_id;
        $termId = $r->academic_term_id;
        $remark = 'Settled';

        if ($studentId && $termId) {
            $key = "{$studentId}_{$termId}";
            if ($termBalances !== null && array_key_exists($key, $termBalances)) {
                $remark = $termBalances[$key] > 0 ? 'Outstanding' : 'Settled';
            } else {
                $table = method_exists($r, 'getTable') ? $r->getTable() : 'graduate_ledgers';
                $balance = (float) DB::table($table)
                    ->where('student_id', $studentId)
                    ->where('academic_term_id', $termId)
                    ->sum(DB::raw("CASE WHEN LOWER(TRIM(entry_type)) = 'ar' THEN ABS(amount) WHEN LOWER(TRIM(entry_type)) IN ('payment', 'adjustment') THEN -ABS(amount) ELSE 0 END"));
                $remark = $balance > 0 ? 'Outstanding' : 'Settled';
            }
        } elseif ($entryTypeLower === 'ar' && (float) $r->amount > 0) {
            $remark = 'Outstanding';
        }

        return [
            'id' => $r->id,
            'student_id' => $r->student_id,
            'student_number' => $studentNumber,
            'course_id' => $r->course_id,
            'academic_term_id' => $r->academic_term_id,
            'entry_type' => $r->entry_type,
            'units' => $r->units,
            'transaction_date' => $r->transaction_date?->format('Y-m-d') ?? ($r->transaction_date ? (string) $r->transaction_date : null),
            'reference_number' => $r->reference_number,
            'particulars' => $r->particulars,
            'rate' => $r->rate,
            'amount' => $r->amount,
            'remark' => $remark,
            'remarks' => $r->remarks,
            'status' => $r->status,
            'input_by' => $inputBy,
            'membership' => $r->membership ?? null,
            'latin_honor' => $r->latin_honor ?? null,
            'discount_amount' => $r->discount_amount ?? 0,
            'orLink' => $orLink,
            'student' => $r->student ? [
                'id' => $r->student->id,
                'student_number' => $r->student->student_number ?? $studentNumber,
                'first_name' => $r->student->first_name,
                'last_name' => $r->student->last_name,
                'name' => $r->student->full_name,
            ] : null,
            'course' => $r->course?->only(['id', 'course_code', 'course_desc', 'course_college']),
            'academic_term' => $r->academicTerm?->only(['id', 'school_year', 'semester']),
        ];
    }

    // ── Apply Membership (Graduate only) ──────────────────────────────────────

    public function applyMembership(Request $request, int $id): JsonResponse
    {
        $ledger = (string) $request->route('ledger');

        if ($ledger !== 'graduate') {
            return response()->json(['message' => 'Membership scholarships only apply to the Graduate School Ledger.'], 422);
        }

        $validated = $request->validate([
            'membership' => ['required', 'in:NAPU,NORSUFFA'],
        ]);

        $record = GraduateLedger::findOrFail($id);

        if (strtolower(trim((string) $record->entry_type)) !== 'ar') {
            return response()->json(['message' => 'Membership scholarship discounts can only be applied to AR (Assessment) entries.'], 422);
        }

        if (filled($record->membership)) {
            return response()->json(['message' => 'A membership scholarship has already been applied to this assessment.'], 422);
        }

        $discountAmount = abs((float) $record->amount);

        if ($discountAmount <= 0) {
            return response()->json(['message' => 'Cannot apply a scholarship to an assessment with an amount of ₱0.00.'], 422);
        }

        $membership = $validated['membership'];
        $membershipFullName = $membership === 'NAPU'
            ? 'NORSU Administrative Personnel Union'
            : 'NORSU Federated Faculty Association';

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

        return response()->json([
            'message' => "{$membership} ({$membershipFullName}) 100% scholarship of ₱".number_format($discountAmount, 2).' applied successfully.',
            'record_id' => $record->id,
        ]);
    }

    // ── Apply Latin Honor (Law only) ──────────────────────────────────────────

    public function applyLatinHonor(Request $request, int $id): JsonResponse
    {
        $ledger = (string) $request->route('ledger');

        if ($ledger !== 'law') {
            return response()->json(['message' => 'Latin honor scholarships only apply to the School of Law Ledger.'], 422);
        }

        $validated = $request->validate([
            'latin_honor' => ['required', 'in:SUMMA,MAGNA,CUM_LAUDE'],
        ]);

        $record = LawSchoolLedger::findOrFail($id);

        if ($record->entry_type !== 'ar') {
            return response()->json(['message' => 'Latin honor discounts can only be applied to AR (Assessment) entries.'], 422);
        }

        $originalAmount = abs((float) $record->amount);

        if ($originalAmount <= 0) {
            return response()->json(['message' => 'Cannot apply a Latin honor discount to a zero-amount assessment.'], 422);
        }

        $latinHonor = $validated['latin_honor'];

        $existingHonorAdjustment = LawSchoolLedger::query()
            ->where('entry_type', 'adjustment')
            ->where('reference_number', 'like', 'HONOR-%-'.$record->id)
            ->exists();

        if ($existingHonorAdjustment) {
            return response()->json(['message' => 'A Latin honor discount has already been applied to this assessment.'], 422);
        }

        $discountPercentage = match ($latinHonor) {
            'SUMMA' => 100,
            'MAGNA' => 75,
            'CUM_LAUDE' => 50,
            default => 0,
        };
        $discountAmount = round(($originalAmount * $discountPercentage) / 100, 2);

        $referencePrefix = match ($latinHonor) {
            'SUMMA' => 'SUM',
            'MAGNA' => 'MAG',
            'CUM_LAUDE' => 'CUM',
            default => 'HON',
        };
        $particulars = match ($latinHonor) {
            'SUMMA' => 'Summa Cum Laude Scholarship (100%)',
            'MAGNA' => 'Magna Cum Laude Scholarship (75%)',
            'CUM_LAUDE' => 'Cum Laude Scholarship (50%)',
            default => 'Latin Honor Scholarship',
        };
        $honorName = match ($latinHonor) {
            'SUMMA' => 'Summa Cum Laude',
            'MAGNA' => 'Magna Cum Laude',
            'CUM_LAUDE' => 'Cum Laude',
            default => 'Latin Honor',
        };

        DB::transaction(function () use ($record, $latinHonor, $discountAmount, $referencePrefix, $particulars): void {
            $record->update([
                'latin_honor' => $latinHonor,
                'discount_amount' => $discountAmount,
            ]);

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

        return response()->json([
            'message' => "{$honorName} discount of ₱".number_format($discountAmount, 2).' applied successfully.',
            'record_id' => $record->id,
        ]);
    }
}
