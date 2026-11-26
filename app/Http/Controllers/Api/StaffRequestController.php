<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\StaffInputController;
use App\Http\Requests\AssignOpAcademicTermRequest;
use App\Http\Requests\CreateAndLinkOpStudentRequest;
use App\Http\Requests\LinkOpStudentRequest;
use App\Http\Requests\SearchOpStudentsRequest;
use App\Http\Requests\StaffProcessingRequest;
use App\Http\Requests\UpdateOpOrNumberRequest;
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
use App\Services\CashierLedgerPostingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class StaffRequestController extends Controller
{
    // ── Index ─────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $query = FormInput::with(['membership', 'staffInput']);

        if ($request->has('status') && $request->status !== '') {
            if ($request->status === 'unprocessed') {
                $query->whereDoesntHave('staffInput');
            } else {
                $query->whereHas('staffInput', fn ($q) => $q->where('status', $request->status));
            }
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from.' 00:00:00');
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to.' 23:59:59');
        }

        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(reference_number) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(firstname_or_office) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(middlename_or_project) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(lastname_or_agency) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw(
                        "LOWER(CONCAT_WS(' ', firstname_or_office, middlename_or_project, lastname_or_agency)) LIKE ?",
                        ["%{$search}%"],
                    );
            });
        }

        $sortableColumns = [
            'reference_number', 'firstname_or_office', 'email',
            'amount', 'membership_id', 'created_at',
        ];
        $sort = $request->query('sort');
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        if ($sort === 'status') {
            $query->select('form_inputs.*')
                ->leftJoin('staff_inputs', 'staff_inputs.form_input_id', '=', 'form_inputs.id')
                ->orderBy('staff_inputs.status', $direction);
        } elseif ($sort && in_array($sort, $sortableColumns, true)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = max(1, min((int) $request->query('per_page', 10), 100));
        $formInputs = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => $formInputs->items(),
            'meta' => [
                'current_page' => $formInputs->currentPage(),
                'per_page' => $formInputs->perPage(),
                'total' => $formInputs->total(),
                'last_page' => $formInputs->lastPage(),
            ],
            'links' => [
                'next' => $formInputs->nextPageUrl(),
                'prev' => $formInputs->previousPageUrl(),
            ],
        ]);
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function show(FormInput $formInput): JsonResponse
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

        return response()->json([
            'data' => $formInput,
            'bankAccounts' => BankAccountInfo::orderBy('bank_name')->get(),
            'uacsList' => UACS::select(['id', 'uacs_object_code', 'account_title'])->orderBy('id')->get(),
            'paymentOptions' => PaymentDetailOption::orderBy('payment_desc')->get(),
            'academicTerms' => AcademicTerm::orderByDesc('school_year')->orderBy('semester')->get(['id', 'school_year', 'semester']),
        ]);
    }

    // ── Store (initial processing) ────────────────────────────────────────────

    public function store(StaffProcessingRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $formInput = FormInput::findOrFail($request->integer('form_input_id'));
            $formInput->loadMissing('course');

            if ($this->isLedgerCourse($formInput) && $formInput->academic_term === null) {
                throw new \RuntimeException('Select an academic term before processing a Graduate or Law request.');
            }

            if ($this->isLedgerCourse($formInput) && $formInput->student_num === null) {
                throw new \RuntimeException('Match a student before processing a Graduate or Law request.');
            }

            if ($formInput->staffInput()->exists()) {
                throw ValidationException::withMessages([
                    'form_input_id' => ['This request has already been processed.'],
                ]);
            }

            $staffInput = StaffInput::create(array_merge(
                $request->validated(),
                [
                    'form_input_id' => $formInput->id,
                    'status' => $request->status ?: 'processed',
                ]
            ));

            DB::commit();

            $formInput->load(['staffInput.bankAccount', 'staffInput.uacs']);

            return response()->json([
                'message' => 'Request processed successfully.',
                'data' => $formInput,
            ], 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\RuntimeException $e) {
            DB::rollBack();
            Log::error('API staff processing failed: '.$e->getMessage());

            return response()->json([
                'message' => 'Failed to process request: '.$e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('API staff processing failed: '.$e->getMessage());

            return response()->json([
                'message' => 'Failed to process request.',
            ], 500);
        }
    }

    public function process(StaffProcessingRequest $request, FormInput $formInput): JsonResponse
    {
        return $this->store($request);
    }

    // ── Update (edit existing StaffInput) ─────────────────────────────────────

    public function update(
        StaffProcessingRequest $request,
        StaffInput $staffInput,
        CashierLedgerPostingService $postingService,
    ): JsonResponse {
        $validated = $request->validated();

        try {
            DB::transaction(function () use ($validated, $staffInput, $postingService): void {
                $locked = StaffInput::lockForUpdate()->findOrFail($staffInput->id);
                $status = $validated['status'];

                if ($locked->status === 'paid' && $status === 'cancelled') {
                    $postingService->reversePayment($locked);
                }

                $locked->update([
                    'fundcluster_id' => $validated['fundcluster_id'],
                    'ref_document_id' => $validated['ref_document_id'] ?? null,
                    'ref_date' => $validated['ref_date'],
                    'uacs_id' => $validated['uacs_id'],
                    'status' => $status,
                    'purpose' => $validated['purpose'] ?? null,
                ]);
            });

            $staffInput->refresh();

            return response()->json([
                'message' => 'Processing updated successfully.',
                'data' => $staffInput,
            ]);

        } catch (\Throwable $e) {
            Log::error('API staff update failed: '.$e->getMessage(), ['staff_input_id' => $staffInput->id]);

            return response()->json(['message' => 'An error occurred. Please try again.'], 500);
        }
    }

    // ── Update OR number ──────────────────────────────────────────────────────

    public function updateOrNumber(
        UpdateOpOrNumberRequest $request,
        StaffInput $staffInput,
        CashierLedgerPostingService $postingService,
    ): JsonResponse {
        $validated = $request->validated();

        try {
            DB::transaction(function () use ($validated, $staffInput, $postingService): void {
                $locked = StaffInput::lockForUpdate()->findOrFail($staffInput->id);

                if (blank($locked->or_no)) {
                    return;
                }

                $locked->update([
                    'or_no' => $validated['or_no'],
                    'or_date' => $validated['or_date'],
                ]);

                if ($locked->status === 'paid') {
                    $postingService->postPayment($locked->fresh('formInput.course'));
                }
            });

            return response()->json([
                'message' => 'OR number updated successfully.',
                'data' => $staffInput->refresh(),
            ]);

        } catch (\Throwable $e) {
            Log::error('API OR update failed: '.$e->getMessage(), ['staff_input_id' => $staffInput->id]);

            return response()->json(['message' => 'An error occurred. Please try again.'], 500);
        }
    }

    // ── Update form input details ─────────────────────────────────────────────

    public function updateDetails(Request $request, FormInput $formInput): JsonResponse
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
            $paymentOption = PaymentDetailOption::whereRaw('LOWER(payment_desc) = ?', [mb_strtolower($customOption)])->first()
                ?? PaymentDetailOption::create(['payment_desc' => $customOption]);

            $validated['payment_detail_option_id'] = $paymentOption->id;
        }

        if (empty($validated['payment_detail_option_id'])) {
            throw ValidationException::withMessages([
                'payment_detail_option_id' => ['Please select or add a payment option.'],
            ]);
        }

        unset($validated['new_payment_option']);

        try {
            DB::beginTransaction();

            if ($formInput->staffInput) {
                $formInput->staffInput->update(['purpose' => $validated['purpose'] ?? null]);
            }

            unset($validated['purpose']);
            $formInput->update($validated);

            DB::commit();

            return response()->json([
                'message' => 'Request details updated successfully.',
                'data' => $formInput->refresh(),
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("API FormInput details update failed ID {$formInput->id}: ".$e->getMessage());

            return response()->json(['message' => 'Failed to update request details.'], 500);
        }
    }

    // ── Student search ────────────────────────────────────────────────────────

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
            ->map(fn (Student $s): array => [
                'id' => $s->id,
                'student_number' => $s->student_number,
                'full_name' => $s->full_name,
            ]);

        return response()->json(['students' => $students]);
    }

    // ── Link existing student ─────────────────────────────────────────────────

    public function linkStudent(LinkOpStudentRequest $request, FormInput $formInput): JsonResponse
    {
        $this->ensureLedgerMatchingIsApplicable($formInput);

        DB::transaction(function () use ($request, $formInput): void {
            $lockedForm = FormInput::lockForUpdate()->findOrFail($formInput->id);
            $student = Student::lockForUpdate()->findOrFail($request->integer('student_id'));
            $updates = [];

            if (blank($student->student_number) && filled($lockedForm->submitted_student_number)) {
                $num = preg_replace('/\D/', '', (string) $lockedForm->submitted_student_number) ?? '';

                if ($num !== '') {
                    $conflict = Student::whereKeyNot($student->id)
                        ->whereRaw("REPLACE(REPLACE(student_number, '-', ''), ' ', '') = ?", [$num])
                        ->first();

                    if ($conflict !== null) {
                        throw ValidationException::withMessages([
                            'student_id' => ['That student number already belongs to another student.'],
                        ]);
                    }

                    $updates['student_number'] = $num;
                }
            }

            if (blank($student->email) && filled($lockedForm->email)) {
                $updates['email'] = trim((string) $lockedForm->email);
            }

            if ($updates !== []) {
                $student->update($updates);
            }

            $lockedForm->update(['student_num' => $student->id]);
        });

        return response()->json([
            'message' => 'Student matched to this Order of Payment.',
            'data' => $formInput->refresh()->load('student'),
        ]);
    }

    // ── Create + link student ─────────────────────────────────────────────────

    public function createAndLinkStudent(
        CreateAndLinkOpStudentRequest $request,
        FormInput $formInput,
    ): JsonResponse {
        $this->ensureLedgerMatchingIsApplicable($formInput);

        $validated = $request->validated();
        $studentNumber = preg_replace('/\D/', '', (string) $validated['student_number']) ?? '';

        try {
            DB::transaction(function () use ($formInput, $validated, $studentNumber): void {
                $lockedForm = FormInput::lockForUpdate()->findOrFail($formInput->id);
                $student = Student::whereRaw("REPLACE(REPLACE(student_number, '-', ''), ' ', '') = ?", [$studentNumber])
                    ->lockForUpdate()
                    ->first();

                if ($student === null) {
                    $student = Student::create([
                        'student_number' => $studentNumber,
                        'first_name' => trim((string) $validated['first_name']),
                        'middle_name' => filled($validated['middle_name'] ?? null) ? trim((string) $validated['middle_name']) : null,
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
        } catch (QueryException $e) {
            if ((string) $e->getCode() !== '23505') {
                throw $e;
            }

            $student = Student::where('student_number', $studentNumber)->firstOrFail();
            $formInput->update(['student_num' => $student->id]);
        }

        return response()->json([
            'message' => 'Student created and matched to this Order of Payment.',
            'data' => $formInput->refresh()->load('student'),
        ], 201);
    }

    // ── Unlink student ────────────────────────────────────────────────────────

    public function unlinkStudent(FormInput $formInput): JsonResponse
    {
        DB::transaction(function () use ($formInput): void {
            FormInput::lockForUpdate()->findOrFail($formInput->id)->update(['student_num' => null]);
        });

        return response()->json([
            'message' => 'Student unlinked from this Order of Payment.',
            'data' => $formInput->refresh()->load('student'),
        ]);
    }

    // ── Assign academic term ──────────────────────────────────────────────────

    public function assignAcademicTerm(
        AssignOpAcademicTermRequest $request,
        FormInput $formInput,
    ): JsonResponse {
        $this->ensureLedgerMatchingIsApplicable($formInput);

        $formInput->update(['academic_term' => $request->integer('academic_term')]);

        return response()->json([
            'message' => 'Academic term assigned to this Order of Payment.',
            'data' => $formInput->refresh()->load('academicTerm'),
        ]);
    }

    // ── Email recipients list ─────────────────────────────────────────────────

    public function emailRecipients(): JsonResponse
    {
        $formInputs = FormInput::with([
            'staffInput' => fn ($q) => $q->select('id', 'form_input_id', 'status', 'emailed_at', 'email_status'),
        ])->orderBy('id')->get();

        return response()->json([
            'data' => $formInputs,
            'queue_stalled' => false, // stall detection is a UI convenience; keep it lightweight here
        ]);
    }

    // ── Email single OP ───────────────────────────────────────────────────────

    public function emailOp(FormInput $formInput, Request $request): JsonResponse
    {
        if (! $formInput->staffInput) {
            abort(404, 'This request has not been processed yet.');
        }

        $validated = $request->validate([
            'subject' => 'nullable|string|max:255',
            'recipient_name' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:2000',
        ]);

        $formInput->load(['staffInput.bankAccount', 'staffInput.uacs', 'staffInput.referenceDocument']);

        $copyLabels = StaffInputController::OP_COPY_LABELS;

        $portraitPdf = Pdf::loadView('pdf.op-a6', compact('formInput', 'copyLabels'))->setPaper('a5', 'portrait');
        $landscapePdf = Pdf::loadView('pdf.op-landscape', compact('formInput', 'copyLabels'))->setPaper('legal', 'landscape');

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

        $formInput->staffInput()->update(['emailed_at' => now(), 'email_status' => 'sent', 'email_error' => null]);

        $actor = $request->user();
        try {
            ActivityLog::create([
                'actor_id' => $actor?->getAuthIdentifier(),
                'actor_name' => $actor?->name,
                'actor_role' => $actor?->role,
                'action' => 'email.sent',
                'target_id' => null,
                'subject_type' => null,
                'subject_id' => null,
                'description' => "Email sent to {$formInput->email} with subject \"".($validated['subject'] ?? "Order of Payment - {$formInput->reference_number}").'\".',
                'meta' => [
                    'to' => $formInput->email,
                    'subject' => $validated['subject'] ?? "Order of Payment - {$formInput->reference_number}",
                ],
            ]);
        } catch (\Throwable $e) {
            Log::debug('Failed to log single OP email activity', ['error' => $e->getMessage()]);
        }

        return response()->json(['message' => 'Order of Payment emailed successfully.']);
    }

    // ── Bulk email ────────────────────────────────────────────────────────────

    public function bulkEmailOp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'form_input_ids' => ['required', 'array', 'min:1'],
            'form_input_ids.*' => ['integer', 'exists:form_inputs,id'],
            'subject' => 'nullable|string|max:255',
            'recipient_name' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:2000',
        ]);

        $requestedIds = array_values(array_unique(array_map('intval', $validated['form_input_ids'])));

        $eligibleIds = FormInput::whereKey($requestedIds)
            ->whereNotNull('email')
            ->whereHas('staffInput')
            ->pluck('id')
            ->all();

        $skipped = count($requestedIds) - count($eligibleIds);

        $alreadyQueued = StaffInput::whereIn('form_input_id', $eligibleIds)
            ->where('email_status', 'pending')
            ->where('email_queued_at', '>=', now()->subMinutes(StaffInputController::EMAIL_QUEUE_WINDOW_MINUTES))
            ->pluck('form_input_id')
            ->all();

        $queueIds = array_values(array_diff($eligibleIds, $alreadyQueued));

        $now = now();

        if ($eligibleIds !== []) {
            StaffInput::whereIn('form_input_id', $eligibleIds)
                ->update(['email_status' => 'pending', 'email_error' => null, 'updated_at' => $now]);
        }

        if ($queueIds !== []) {
            StaffInput::whereIn('form_input_id', $queueIds)->update(['email_queued_at' => $now]);
        }

        foreach ($queueIds as $formInputId) {
            try {
                SendOrderOfPaymentEmail::dispatchSync(
                    $formInputId,
                    $validated['subject'] ?? null,
                    $validated['recipient_name'] ?? null,
                    $validated['note'] ?? null,
                    $request->user()?->id,
                );
            } catch (\Throwable $e) {
                Log::error('API bulk OP email failed', ['id' => $formInputId, 'error' => $e->getMessage()]);
                StaffInput::where('form_input_id', $formInputId)->update([
                    'email_status' => 'failed',
                    'email_error' => \Str::limit($e->getMessage(), 500),
                ]);
            }
        }

        $messages = [count($queueIds).' email job(s) queued successfully.'];

        if (count($alreadyQueued) > 0) {
            $messages[] = count($alreadyQueued).' request(s) were already queued and will not be sent twice.';
        }
        if ($skipped > 0) {
            $messages[] = "{$skipped} request(s) were skipped (no email or not yet processed).";
        }

        return response()->json([
            'message' => implode(' ', $messages),
            'form_input_ids' => array_values($eligibleIds),
            'total' => count($eligibleIds),
        ]);
    }

    // ── Staff Dashboard ───────────────────────────────────────────────────────

    public function dashboard(Request $request): JsonResponse
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

        $unprocessedStats = FormInput::query()
            ->whereDoesntHave('staffInput')
            ->selectRaw('count(*) as count, MAX(created_at) as latest')
            ->first();
        $unprocessedCount = (int) ($unprocessedStats->count ?? 0);
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

        $recentRequests = FormInput::with(['staffInput', 'membership'])
            ->latest()
            ->take(8)
            ->get()
            ->map(fn (FormInput $f) => [
                'id' => $f->id,
                'reference_number' => $f->reference_number,
                'full_name' => $f->full_name,
                'created_at' => $f->created_at?->toISOString(),
                'status' => $f->staffInput?->status ?? 'unprocessed',
            ]);

        $recentActivity = StaffInput::with('formInput')
            ->latest()
            ->take(8)
            ->get()
            ->map(fn (StaffInput $staffInput) => [
                'id' => $staffInput->id,
                'event' => 'processed',
                'description' => "Request {$staffInput->formInput?->reference_number} was processed as ".ucfirst($staffInput->status).'.',
                'causerName' => null,
                'createdAt' => $staffInput->created_at?->toISOString(),
            ]);

        return response()->json([
            'data' => [
                'totalRequests' => FormInput::count(),
                'unprocessedRequests' => $unprocessedCount,
                'pendingRequests' => (int) $statusCounts->get('pending', 0),
                'processedRequests' => (int) $statusCounts->get('processed', 0),
                'paidRequests' => (int) $statusCounts->get('paid', 0),
                'cancelledRequests' => (int) $statusCounts->get('cancelled', 0),
                'totalRequestsLastDate' => $totalRequestsLastDate,
                'unprocessedLastDate' => $unprocessedLastDate,
                'pendingLastDate' => $latestByStatus->get('pending'),
                'processedLastDate' => $latestByStatus->get('processed'),
                'paidLastDate' => $latestByStatus->get('paid'),
                'cancelledLastDate' => $latestByStatus->get('cancelled'),
                'statusBreakdown' => collect([
                    ['name' => 'Unprocessed', 'count' => $unprocessedCount],
                ])
                    ->concat(
                        collect(['pending', 'processed', 'paid', 'cancelled'])
                            ->map(fn (string $status) => [
                                'name' => ucfirst($status),
                                'count' => (int) $statusCounts->get($status, 0),
                            ])
                    )
                    ->values(),
                'requestsOverTime' => $requestsOverTime,
                'requestsByMembership' => FormInput::query()
                    ->join('memberships', 'form_inputs.membership_id', '=', 'memberships.id')
                    ->selectRaw('memberships.member_code as name, count(*) as count')
                    ->groupBy('memberships.id', 'memberships.member_desc', 'memberships.member_code')
                    ->orderBy('name')
                    ->get(),
                'recentRequests' => $recentRequests,
                'recentActivity' => $recentActivity,
            ],
        ]);
    }

    // ── Staff Activity Log ────────────────────────────────────────────────────

    public function activityLog(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('activity_search', ''));
        $action = (string) $request->query('activity_action', '');
        $dateFrom = (string) $request->query('date_from', '');
        $dateTo = (string) $request->query('date_to', '');

        $query = ActivityLog::query();

        if ($search !== '') {
            $like = '%'.mb_strtolower($search).'%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(description) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(actor_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(actor_role) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(action) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(subject_type) LIKE ?', [$like]);
            });
        }

        if ($action !== '') {
            if ($action === 'created') {
                $query->whereIn('action', ['created', 'user.created']);
            } else {
                $query->where('action', $action);
            }
        }

        $query->whereNotIn('action', ['user.created', 'user.deactivated', 'user.reactivated', 'user.updated']);

        if ($dateFrom !== '') {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo !== '') {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $allowedSorts = ['created_at', 'action', 'actor_name', 'subject_type'];
        $sort = (string) $request->query('sort', 'created_at');
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        if ($sort === 'actor_name') {
            $query->orderByRaw('actor_name IS NULL ASC')
                ->orderBy('actor_name', $direction)
                ->orderBy('created_at', 'desc');
        } elseif ($sort === 'subject_type') {
            $query->orderByRaw('subject_type IS NULL ASC')
                ->orderBy('subject_type', $direction)
                ->orderBy('created_at', 'desc');
        } elseif ($sort === 'action') {
            $query->orderBy('action', $direction)
                ->orderBy('created_at', 'desc');
        } else {
            $query->orderBy('created_at', $direction);
        }

        $paginator = $query->paginate(min(max($request->integer('per_page', 10), 1), 100))->withQueryString();

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (ActivityLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'event' => $log->eventCategory(),
                'description' => $log->description,
                'actor_name' => $log->actor_name,
                'actor_role' => $log->actor_role,
                'type' => $log->subjectTypeLabel(),
                'created_at' => $log->created_at?->toISOString(),
                'changes' => is_array($log->meta) && isset($log->meta['changes']) && is_array($log->meta['changes'])
                    ? $log->meta['changes']
                    : [],
            ])->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'links' => [
                'next' => $paginator->nextPageUrl(),
                'prev' => $paginator->previousPageUrl(),
            ],
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

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
}
