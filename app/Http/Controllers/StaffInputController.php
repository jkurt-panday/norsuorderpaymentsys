<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignOpAcademicTermRequest;
use App\Http\Requests\CreateAndLinkOpStudentRequest;
use App\Http\Requests\LinkOpStudentRequest;
use App\Http\Requests\SearchOpStudentsRequest;
use App\Http\Requests\StaffProcessingRequest;
use App\Jobs\SendOrderOfPaymentEmail;
use App\Mail\OrderOfPaymentMail;
use App\Models\AcademicTerm;
use App\Models\ActivityLog;
use App\Models\BankAccountInfo;
use App\Models\FormInput;
use App\Models\PaymentDetailOption;
use App\Models\StaffInput;
use App\Models\Student;
use App\Models\UACS;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StaffInputController extends Controller
{
    private const OP_COPY_LABELS = [
        "Payor's Copy",
        "Cash Unit's Copy",
        "Accounting Unit's Copy",
    ];

    /**
     * Display the staff dashboard.
     */
    public function dashboard(Request $request): Response
    {
        $startDate = now()->subDays(29)->startOfDay();
        $statusCounts = StaffInput::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $latestByStatus = StaffInput::query()
            ->selectRaw('status, MAX(created_at) as latest')
            ->groupBy('status')
            ->pluck('latest', 'status');

        // "Unprocessed" isn't a StaffInput.status value — it's a FormInput
        // that has no StaffInput row at all (same definition already used
        // in index(): $query->whereDoesntHave('staffInput')). Pulled as one
        // query (count + latest together) rather than two separate calls.
        $unprocessedStats = FormInput::query()
            ->whereDoesntHave('staffInput')
            ->selectRaw('count(*) as count, MAX(created_at) as latest')
            ->first();
        $unprocessedCount = $unprocessedStats->count ?? 0;
        $unprocessedLastDate = $unprocessedStats->latest ?? null;

        $totalRequestsLastDate = FormInput::max('created_at');
        $requestsOverTime = FormInput::query()
            ->where('created_at', '>=', $startDate)
            ->get(['created_at'])
            ->groupBy(fn (FormInput $formInput) => $formInput->created_at->toDateString())
            ->map(fn ($requests, $date) => ['date' => $date, 'count' => $requests->count()]);

        $requestsOverTime = collect(range(0, 29))
            ->map(function (int $daysAgo) use ($requestsOverTime) {
                $date = now()->subDays(29 - $daysAgo)->toDateString();

                return $requestsOverTime->get($date, ['date' => $date, 'count' => 0]);
            })
            ->values();
        $recentRequests = FormInput::with('staffInput')
            ->latest()
            ->take(8)
            ->get();
        $recentActivity = StaffInput::with('formInput')
            ->latest()
            ->take(8)
            ->get()
            ->map(fn (StaffInput $staffInput) => [
                'id' => $staffInput->id,
                'event' => 'processed',
                'description' => "Request {$staffInput->formInput->reference_number} was processed as ".ucfirst($staffInput->status).'.',
                'causerName' => null,
                'createdAt' => $staffInput->created_at->toISOString(),
            ]);

        return Inertia::render('staff/staffdashboard', [
            'totalRequests' => FormInput::count(),
            'unprocessedRequests' => $unprocessedCount,
            'pendingRequests' => $statusCounts->get('pending', 0),
            'approvedRequests' => $statusCounts->get('approved', 0),
            'cancelledRequests' => $statusCounts->get('cancelled', 0),
            'totalRequestsLastDate' => $totalRequestsLastDate,
            'unprocessedLastDate' => $unprocessedLastDate,
            'pendingLastDate' => $latestByStatus->get('pending'),
            'approvedLastDate' => $latestByStatus->get('approved'),
            'cancelledLastDate' => $latestByStatus->get('cancelled'),
            // Order here (Unprocessed, Pending, Approved, Cancelled) must match
            // the pieColors array on the frontend: ['#94a3b8', '#f59e0b', '#22c55e', '#ef4444']
            'statusBreakdown' => collect([
                ['name' => 'Unprocessed', 'count' => $unprocessedCount],
            ])
                ->concat(
                    collect(['pending', 'approved', 'cancelled'])
                        ->map(fn (string $status) => [
                            'name' => ucfirst($status),
                            'count' => $statusCounts->get($status, 0),
                        ])
                )
                ->values(),
            'requestsOverTime' => $requestsOverTime,
            'requestsByMembership' => FormInput::query()
                ->join('memberships', 'form_inputs.membership_id', '=', 'memberships.id')
                ->selectRaw('memberships.member_code as name, count(*) as count')
                ->groupBy('memberships.id', 'memberships.member_desc')
                ->orderBy('name')
                ->get(),
            'recentRequests' => $recentRequests,
            'recentActivity' => $recentActivity,
        ]);
    }

    /**
     * Standalone, searchable + paginated Activity Log page. Centralized audit
     * trail of every create/update/delete across the staff-managed entities
     * (logged via ActivityLogObserver), showing who did it and what changed.
     */
    public function activityLog(Request $request): Response
    {
        [$activityLogs, $activityFilters] = $this->buildActivityLogs($request);

        return Inertia::render('staff/activity-log/activity-log', [
            'activityLogs' => $activityLogs,
            'activityFilters' => $activityFilters,
        ]);
    }

    /**
     * Build the searchable/paginated activity log query shared by the
     * dashboard feed and the dedicated Activity Log page.
     *
     * @return array{
     *     0: LengthAwarePaginator<int, covariant array<string, mixed>>,
     *     1: array{activity_search: string, activity_action: string, sort: string, direction: string, date_from: string, date_to: string}
     * }
     */
    protected function buildActivityLogs(Request $request): array
    {
        $activitySearch = (string) $request->query('activity_search', '');
        $activityAction = (string) $request->query('activity_action', '');
        $activitySort = (string) $request->query('sort', '');
        $activityDir = (string) $request->query('direction', '');
        $dateFrom = (string) $request->query('date_from', '');
        $dateTo = (string) $request->query('date_to', '');

        $allowedSorts = ['created_at', 'action', 'actor_name', 'subject_type'];

        $activityQuery = ActivityLog::query();

        if ($activitySearch !== '') {
            $like = '%'.strtolower($activitySearch).'%';
            $activityQuery->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(description) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(actor_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(actor_role) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(action) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(subject_type) LIKE ?', [$like]);
            });
        }

        if ($activityAction !== '') {
            if ($activityAction === 'created') {
                // "created" filter also matches user provisioning entries.
                $activityQuery->whereIn('action', ['created', 'user.created']);
            } else {
                $activityQuery->where('action', $activityAction);
            }
        }

        $activityQuery->whereNotIn('action', ['user.created', 'user.deactivated', 'user.reactivated', 'user.updated']);

        if ($dateFrom !== '') {
            $activityQuery->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo !== '') {
            $activityQuery->whereDate('created_at', '<=', $dateTo);
        }

        // Apply explicit column sort (from clickable headers / sort dropdown),
        // otherwise default to newest-first.
        if (in_array($activitySort, $allowedSorts, true)
            && in_array($activityDir, ['asc', 'desc'], true)) {
            if ($activitySort === 'actor_name') {
                $activityQuery->orderByRaw('actor_name IS NULL ASC')
                    ->orderBy('actor_name', $activityDir)
                    ->orderBy('created_at', 'desc');
            } elseif ($activitySort === 'subject_type') {
                $activityQuery->orderByRaw('subject_type IS NULL ASC')
                    ->orderBy('subject_type', $activityDir)
                    ->orderBy('created_at', 'desc');
            } elseif ($activitySort === 'action') {
                $activityQuery->orderBy('action', $activityDir)
                    ->orderBy('created_at', 'desc');
            } else {
                $activityQuery->orderBy('created_at', $activityDir);
            }
        } else {
            $activityQuery->latest();
        }

        $activityLogs = $activityQuery
            ->paginate(10)
            ->withQueryString()
            ->through(function (ActivityLog $log): array {
                $meta = $log->meta;

                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'event' => $log->eventCategory(),
                    'description' => $log->description,
                    'actor_name' => $log->actor_name,
                    'actor_role' => $log->actor_role,
                    'type' => $log->subjectTypeLabel(),
                    'created_at' => $log->created_at->toISOString(),
                    'changes' => is_array($meta) && isset($meta['changes']) && is_array($meta['changes'])
                        ? $meta['changes']
                        : [],
                ];
            });

        $activityFilters = [
            'activity_search' => $activitySearch,
            'activity_action' => $activityAction,
            'sort' => $activitySort,
            'direction' => $activityDir,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];

        return [$activityLogs, $activityFilters];
    }

    /**
     * Display list of requests for staff processing
     */
    public function index(Request $request): Response
    {
        $query = FormInput::with(['membership', 'staffInput']);

        // Filter by status (handling 'unprocessed' gracefully)
        if ($request->has('status') && $request->status !== '') {
            if ($request->status === 'unprocessed') {
                $query->whereDoesntHave('staffInput');
            } else {
                $query->whereHas('staffInput', function ($q) use ($request) {
                    $q->where('status', $request->status);
                });
            }
        }

        // Fast Date Range Queries (Index Friendly)
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from.' 00:00:00');
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to.' 23:59:59');
        }

        // Search (grouped to avoid breaking status/date filters)
        // Wrapped in LOWER() on both sides so matching is case-insensitive
        // regardless of the database's default collation (e.g. Postgres'
        // LIKE is case-sensitive by default, unlike MySQL's usual collation).
        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(reference_number) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(firstname_or_office) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(lastname_or_agency) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"]);
            });
        }

        // Sorting — only columns in this allowlist can be sorted on. Never
        // pass $request->query('sort') straight into orderBy() unchecked;
        // that lets a crafted query string reference arbitrary columns.
        $sortableColumns = [
            'reference_number',
            'firstname_or_office',
            'email',
            'amount',
            'membership_id',
            'created_at',
        ];

        $sort = $request->query('sort');
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        if ($sort === 'status') {
            // status lives on the related staff_inputs table, not on
            // form_inputs itself, so it needs a join rather than a plain
            // orderBy(). left join (not inner) so "Unprocessed" rows
            // (no staff_input row at all) still appear, sorted to
            // whichever end NULLs land on for the given direction.
            $query->select('form_inputs.*')
                ->leftJoin('staff_inputs', 'staff_inputs.form_input_id', '=', 'form_inputs.id')
                ->orderBy('staff_inputs.status', $direction);
        } elseif ($sort && in_array($sort, $sortableColumns, true)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $formInputs = $query->paginate(10)->withQueryString();
        $filters = $request->only(['search', 'status', 'date_from', 'date_to']);

        return Inertia::render('staff/requestform/request', compact('formInputs', 'filters'));
    }

    public function store(StaffProcessingRequest $request): RedirectResponse
    {
        try {
            db()->beginTransaction();

            $formInput = FormInput::query()->findOrFail($request->integer('form_input_id'));
            $formInput->loadMissing('course');

            if ($this->isLedgerCourse($formInput) && $formInput->academic_term === null) {
                throw new \RuntimeException(
                    'Select an academic term before processing a Graduate or Law request.',
                );
            }

            if ($formInput->staffInput()->exists()) {
                throw new \Exception('This request has already been processed.');
            }

            StaffInput::create(array_merge(
                $request->validated(),
                [
                    'form_input_id' => $formInput->id,
                    'status' => $request->status ?: 'processed',
                ]
            ));

            db()->commit();

            return redirect()->route('staff.requests.show', $formInput)
                ->with('success', 'Request processed successfully.');

        } catch (\Exception $e) {
            db()->rollBack();
            Log::error('Staff processing failed: '.$e->getMessage());

            return back()
                ->withInput()
                ->with('error', 'Failed to process request: '.$e->getMessage());
        }
    }

    public function update(StaffProcessingRequest $request, StaffInput $staffInput): RedirectResponse
    {
        $validated = $request->validated();

        try {
            db()->beginTransaction();

            $status = $validated['status'];
            $staffInput->update([
                'fundcluster_id' => $validated['fundcluster_id'],
                'ref_document_id' => $validated['ref_document_id'] ?? null,
                'ref_date' => $validated['ref_date'],
                'uacs_id' => $validated['uacs_id'],
                'status' => $status,
                'purpose' => $validated['purpose'] ?? null,
            ]);

            db()->commit();

            return redirect()->route('staff.requests.show', $staffInput->formInput)
                ->with('success', 'Processing updated successfully! Current status: '.ucfirst($staffInput->status));

        } catch (\Exception $e) {
            db()->rollBack();
            Log::error('Staff processing update failed: '.$e->getMessage(), [
                'staff_input_id' => $staffInput->id,
                'request_data' => $request->all(),
            ]);

            return back()->withInput()->with('error', 'Failed to update processing: '.$e->getMessage());
        }
    }

    /**
     * Update FormInput's own fields (name, amount, payment option) — separate
     * from update() above, which only touches StaffInput (bank/UACS/status).
     * Available regardless of processing status, since these fields belong
     * to the original submission, not to staff processing.
     */
    public function updateDetails(Request $request, FormInput $formInput): RedirectResponse
    {
        $validated = $request->validate([
            'firstname_or_office' => 'required|string|max:100',
            'middlename_or_project' => 'nullable|string|max:100',
            'lastname_or_agency' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0',
            'purpose' => 'nullable|string|max:350',
            'payment_detail_option_id' => ['nullable', 'exists:payment_detail_options,id'],
            'new_payment_option' => 'nullable|string|max:255',
        ]);

        $customOption = trim((string) ($validated['new_payment_option'] ?? ''));

        if ($customOption !== '') {
            $paymentOption = PaymentDetailOption::query()
                ->whereRaw('LOWER(payment_desc) = ?', [mb_strtolower($customOption)])
                ->first();

            if (! $paymentOption) {
                $paymentOption = PaymentDetailOption::create([
                    'payment_desc' => $customOption,
                ]);
            }

            $validated['payment_detail_option_id'] = $paymentOption->id;
        }

        if (empty($validated['payment_detail_option_id'])) {
            return back()->withInput()->with('error', 'Please select or add a payment option.');
        }

        unset($validated['new_payment_option']);

        try {
            db()->beginTransaction();
            if ($formInput->staffInput) {
                $formInput->staffInput->update(['purpose' => $validated['purpose'] ?? null]);
            }

            unset($validated['purpose']);

            $formInput->update($validated);

            db()->commit();

            return redirect()->route('staff.requests.show', $formInput)
                ->with('success', 'Request details updated successfully.');

        } catch (\Exception $e) {
            db()->rollBack();
            Log::error("Failed to update FormInput details ID {$formInput->id}: ".$e->getMessage());

            return back()->withInput()->with('error', 'Failed to update request details: '.$e->getMessage());
        }
    }

    public function searchStudents(SearchOpStudentsRequest $request): JsonResponse
    {
        $search = trim((string) $request->validated('q', ''));
        $digits = preg_replace('/\D/', '', $search) ?? '';

        $students = Student::query()
            ->when($search !== '', function ($query) use ($search, $digits): void {
                $like = '%'.mb_strtolower($search).'%';

                $query->where(function ($query) use ($like, $digits): void {
                    $query->whereRaw('LOWER(student_number) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(first_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', [$like]);

                    if ($digits !== '') {
                        $query->orWhereRaw(
                            "REPLACE(REPLACE(student_number, '-', ''), ' ', '') LIKE ?",
                            ["%{$digits}%"],
                        );
                    }
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(20)
            ->get(['id', 'student_number', 'first_name', 'middle_name', 'last_name'])
            ->map(fn (Student $student): array => [
                'id' => $student->id,
                'student_number' => $student->student_number,
                'full_name' => $student->full_name,
            ]);

        return response()->json(['students' => $students]);
    }

    public function linkStudent(LinkOpStudentRequest $request, FormInput $formInput): RedirectResponse
    {
        $this->ensureLedgerMatchingIsApplicable($formInput);

        DB::transaction(function () use ($request, $formInput): void {
            $lockedForm = FormInput::query()->lockForUpdate()->findOrFail($formInput->id);
            $student = Student::query()
                ->lockForUpdate()
                ->findOrFail($request->integer('student_id'));
            $studentUpdates = [];

            if (blank($student->student_number) && filled($lockedForm->submitted_student_number)) {
                $studentNumber = preg_replace(
                    '/\D/',
                    '',
                    (string) $lockedForm->submitted_student_number,
                ) ?? '';

                if ($studentNumber !== '') {
                    $numberOwner = Student::query()
                        ->whereKeyNot($student->id)
                        ->whereRaw(
                            "REPLACE(REPLACE(student_number, '-', ''), ' ', '') = ?",
                            [$studentNumber],
                        )
                        ->first();

                    if ($numberOwner !== null) {
                        throw ValidationException::withMessages([
                            'student_id' => 'That student number already belongs to another student. Select that student instead.',
                        ]);
                    }

                    $studentUpdates['student_number'] = $studentNumber;
                }
            }

            if (blank($student->email) && filled($lockedForm->email)) {
                $studentUpdates['email'] = trim((string) $lockedForm->email);
            }

            if ($studentUpdates !== []) {
                $student->update($studentUpdates);
            }

            $lockedForm->update(['student_num' => $student->id]);
        });

        return back()->with('success', 'Student matched to this Order of Payment.');
    }

    public function createAndLinkStudent(
        CreateAndLinkOpStudentRequest $request,
        FormInput $formInput,
    ): RedirectResponse {
        $this->ensureLedgerMatchingIsApplicable($formInput);

        $validated = $request->validated();
        $studentNumber = preg_replace('/\D/', '', (string) $validated['student_number']) ?? '';

        try {
            DB::transaction(function () use ($formInput, $validated, $studentNumber): void {
                $lockedForm = FormInput::query()->lockForUpdate()->findOrFail($formInput->id);
                $student = Student::query()
                    ->whereRaw("REPLACE(REPLACE(student_number, '-', ''), ' ', '') = ?", [$studentNumber])
                    ->lockForUpdate()
                    ->first();

                if ($student === null) {
                    $student = Student::query()->create([
                        'student_number' => $studentNumber,
                        'first_name' => trim((string) $validated['first_name']),
                        'middle_name' => filled($validated['middle_name'] ?? null)
                            ? trim((string) $validated['middle_name'])
                            : null,
                        'last_name' => trim((string) $validated['last_name']),
                        'email' => $validated['email'] ?? null,
                        'contact_num' => $validated['contact_num'] ?? null,
                    ]);
                }

                $lockedForm->update([
                    'student_num' => $student->id,
                    'submitted_student_number' => $studentNumber,
                ]);
            });
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() !== '23505') {
                throw $exception;
            }

            $student = Student::query()->where('student_number', $studentNumber)->firstOrFail();
            $formInput->update(['student_num' => $student->id]);
        }

        return back()->with('success', 'Student created and matched to this Order of Payment.');
    }

    public function assignAcademicTerm(
        AssignOpAcademicTermRequest $request,
        FormInput $formInput,
    ): RedirectResponse {
        $this->ensureLedgerMatchingIsApplicable($formInput);

        $formInput->update([
            'academic_term' => $request->integer('academic_term'),
        ]);

        return back()->with('success', 'Academic term assigned to this Order of Payment.');
    }

    /**
     * Display staff processing details
     */
    public function show(FormInput $formInput): Response
    {
        $formInput->load([
            'membership',
            'paymentDetailOption',
            'student',
            'course',
            'supportingDocuments',
            'staffInput.bankAccount',
            'staffInput.uacs',
            'staffInput.referenceDocument',
        ]);
        $formInput->student?->append('full_name');

        // Added: bankAccounts + uacsList, so the inline "Process Now" form
        // on this page has what it needs without a separate navigation.
        // Added: paymentOptions, so the new "Edit Processed Request" form
        // (which also edits the FormInput's name/amount/payment option
        // fields, not just the StaffInput fields) has what it needs too.
        return Inertia::render('staff/requestform/showrequest', [
            'formInput' => $formInput,
            'bankAccounts' => BankAccountInfo::orderBy('bank_name')->get(),
            'uacsList' => UACS::orderBy('object_code')->get(),
            'paymentOptions' => PaymentDetailOption::orderBy('payment_desc')->get(),
            'academicTerms' => AcademicTerm::query()
                ->orderByDesc('school_year')
                ->orderBy('semester')
                ->get(['id', 'school_year', 'semester']),
        ]);
    }

    private function ensureLedgerMatchingIsApplicable(FormInput $formInput): void
    {
        $formInput->loadMissing('course');

        abort_unless(
            $this->isLedgerCourse($formInput),
            422,
            'Ledger matching is only available for Graduate School and School of Law payments.',
        );
    }

    private function isLedgerCourse(FormInput $formInput): bool
    {
        return in_array(
            $formInput->course?->course_college,
            ['Graduate School', 'School of Law'],
            true,
        );
    }

    public function viewOp(FormInput $formInput, Request $request): HttpResponse
    {
        $formInput->load(['staffInput.bankAccount', 'staffInput.uacs', 'staffInput.referenceDocument']);

        $copyLabels = self::OP_COPY_LABELS;

        $layout = $request->query('layout', 'portrait');

        $pdf = $layout === 'landscape'
            ? Pdf::loadView('pdf.op-landscape', compact('formInput', 'copyLabels'))->setPaper('legal', 'landscape')
            : Pdf::loadView('pdf.op-a6', compact('formInput', 'copyLabels'))->setPaper('a6', 'portrait');

        return $pdf->stream("OP-{$formInput->reference_number}.pdf");
    }

    public function emailOp(FormInput $formInput, Request $request): RedirectResponse
    {
        if (! $formInput->staffInput) {
            abort(404);
        }

        $validated = $request->validate([
            'subject' => 'nullable|string|max:255',
            'recipient_name' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:2000',
        ]);

        $formInput->load(['staffInput.bankAccount', 'staffInput.uacs', 'staffInput.referenceDocument']);

        $copyLabels = self::OP_COPY_LABELS;

        $portraitPdf = Pdf::loadView('pdf.op-a6', compact('formInput', 'copyLabels'))
            ->setPaper('a5', 'portrait');

        $landscapePdf = Pdf::loadView('pdf.op-landscape', compact('formInput', 'copyLabels'))
            ->setPaper('legal', 'landscape');

        \Mail::to($formInput->email)->send(
            new OrderOfPaymentMail(
                $formInput,
                $portraitPdf->output(),
                $landscapePdf->output(),
                $validated['subject'] ?? null,
                $validated['recipient_name'] ?? null,
                $validated['note'] ?? null,
            )
        );

        $formInput->staffInput()->update(['emailed_at' => now()]);

return back()->with('success', 'Order of Payment emailed successfully.');
    }

    /**
 * Send Order of Payment emails in bulk to selected requests.
 *
 * Accepts an array of form_input IDs plus optional subject / note.
 * Only requests that have a staff_input record (i.e. processed/paid/
 * cancelled) are eligible — unprocessed ones are skipped with a warning.
 */
public function bulkEmailOp(Request $request): \Illuminate\Http\JsonResponse
{
    $validated = $request->validate([
        'form_input_ids'   => ['required', 'array', 'min:1'],
        'form_input_ids.*' => ['integer', 'exists:form_inputs,id'],
        'subject'          => 'nullable|string|max:255',
        'recipient_name'   => 'nullable|string|max:255',
        'note'             => 'nullable|string|max:2000',
    ]);

    $requestedIds = array_values(array_unique(array_map('intval', $validated['form_input_ids'])));

    $eligibleIds = FormInput::query()
        ->whereKey($requestedIds)
        ->whereNotNull('email')
        ->whereHas('staffInput')
        ->pluck('id')
        ->all();

    $skipped = count($requestedIds) - count($eligibleIds);

    $dispatchedIds = [];
    foreach ($eligibleIds as $formInputId) {
        SendOrderOfPaymentEmail::dispatch(
            $formInputId,
            $validated['subject'] ?? null,
            $validated['recipient_name'] ?? null,
            $validated['note'] ?? null,
        );
        $dispatchedIds[] = $formInputId;
    }

    return response()->json([
        'success' => true,
        'message' => count($eligibleIds)." email job(s) queued successfully.".($skipped > 0 ? " {$skipped} request(s) were skipped (no email or not yet processed)." : ''),
        'form_input_ids' => $dispatchedIds,
        'total' => count($eligibleIds),
    ]);
}

/**
 * Check status of bulk email jobs by form_input_id.
 */
public function emailJobStatus(Request $request): \Illuminate\Http\JsonResponse
{
    $request->validate([
        'form_input_ids' => ['required', 'array'],
        'form_input_ids.*' => ['integer', 'exists:form_inputs,id'],
    ]);

    $formInputIds = $request->input('form_input_ids');

    // Check staff_input for emailed_at
    $emailedStatus = DB::table('staff_inputs')
        ->whereIn('form_input_id', $formInputIds)
        ->pluck('emailed_at', 'form_input_id');

    $results = [];
    $sentCount = 0;
    $pendingCount = 0;

    foreach ($formInputIds as $formInputId) {
        $emailedAt = $emailedStatus[$formInputId] ?? null;

        if ($emailedAt) {
            $status = 'sent';
            $sentCount++;
        } else {
            $status = 'pending';
            $pendingCount++;
        }

        $results[] = [
            'form_input_id' => $formInputId,
            'status' => $status,
            'emailed_at' => $emailedAt,
        ];
    }

    return response()->json([
        'jobs' => $results,
        'summary' => [
            'total' => count($formInputIds),
            'sent' => $sentCount,
            'failed' => 0,
            'pending' => $pendingCount,
        ],
        'completed' => $pendingCount === 0,
    ]);
}

    /**
     * Return ALL form inputs (not paginated) for the bulk-email modal.
     *
     * Includes staff_input + emailed_at so the frontend can render
     * "Sent email X days ago" tags and checkbox selection across every
     * page of results.
     */
    public function emailRecipients(): \Illuminate\Http\JsonResponse
    {
        $formInputs = FormInput::with([
            'staffInput' => function ($q) {
                $q->select('id', 'form_input_id', 'status', 'emailed_at');
            },
        ])->orderBy('id')->get();

        return response()->json([
            'data' => $formInputs,
        ]);
    }
}
