<?php

namespace App\Http\Controllers;

use App\Http\Requests\CashierPaymentRequest;
use App\Models\StaffInput;
use App\Services\CashierLedgerPostingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CashierRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $requestedStatus = $request->string('status')->toString();
        // An empty/absent (or "All") status means "no single-status filter" —
        // i.e. show both 'processed' and 'paid' (the cashier's two scopes),
        // mirroring the StaffInputController "All Status" behaviour. Only an
        // explicit 'processed'/'paid' narrows the result set.
        $status = in_array($requestedStatus, ['processed', 'paid'], true)
            ? $requestedStatus
            : '';
        $search = trim($request->string('search')->toString());
        $dateFrom = (string) $request->query('date_from', '');
        $dateTo = (string) $request->query('date_to', '');

        $query = StaffInput::query()
            ->with(['formInput.membership', 'formInput.paymentDetailOption'])
            ->whereIn('status', ['processed', 'paid'])
            ->when($status !== '', function (Builder $query) use ($status): void {
                $query->where('status', $status);
            })
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.mb_strtolower($search).'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query->whereRaw('LOWER(or_no) LIKE ?', [$like])
                        ->orWhereHas('formInput', function (Builder $query) use ($like): void {
                            $query->whereRaw('LOWER(reference_number) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(firstname_or_office) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(lastname_or_agency) LIKE ?', [$like]);
                        });
                });
            });

        // Date range — qualified to avoid ambiguity if a form_inputs join is
        // applied below for sortable columns.
        if ($dateFrom !== '') {
            $query->where('staff_inputs.created_at', '>=', $dateFrom.' 00:00:00');
        }
        if ($dateTo !== '') {
            $query->where('staff_inputs.created_at', '<=', $dateTo.' 23:59:59');
        }

        // Sorting — only allowlisted columns are accepted, and form_inputs
        // columns are joined in (1:1 belongsTo) so the sort targets the right
        // table without leaking arbitrary columns from the query string.
        $sortableFormColumns = ['reference_number', 'firstname_or_office', 'email', 'amount'];
        $sortableStaffColumns = ['status', 'or_no', 'or_date', 'created_at'];
        $sortableColumns = [...$sortableFormColumns, ...$sortableStaffColumns];

        $sort = $request->query('sort');
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        if ($sort && in_array($sort, $sortableColumns, true)) {
            if (in_array($sort, $sortableFormColumns, true)) {
                $query->join('form_inputs', 'form_inputs.id', '=', 'staff_inputs.form_input_id')
                    ->select('staff_inputs.*')
                    ->orderBy($sort, $direction);
            } else {
                $query->orderBy('staff_inputs.'.$sort, $direction);
            }
        } else {
            $query->latest();
        }

        $requests = $query->paginate(10)->withQueryString();

        return Inertia::render('cashier/requests/Index', [
            'requests' => $requests,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    public function show(StaffInput $staffInput): Response
    {
        abort_unless(in_array($staffInput->status, ['processed', 'paid'], true), 404);

        $staffInput->load(['formInput.membership', 'formInput.paymentDetailOption']);

        return Inertia::render('cashier/requests/Show', ['request' => $staffInput]);
    }

    public function updatePayment(
        CashierPaymentRequest $request,
        StaffInput $staffInput,
        CashierLedgerPostingService $postingService,
    ): RedirectResponse {
        $isCorrection = false;
        $posting = ['posted' => false, 'reason' => null];

        DB::transaction(function () use ($request, $staffInput, $postingService, &$isCorrection, &$posting): void {
            $lockedRequest = StaffInput::query()->lockForUpdate()->findOrFail($staffInput->id);

            abort_unless(
                in_array($lockedRequest->status, ['processed', 'paid'], true),
                422,
                'Only processed or paid requests can be updated by the cashier.',
            );

            $isCorrection = $lockedRequest->status === 'paid';

            $lockedRequest->update([
                ...$request->validated(),
                'status' => 'paid',
            ]);

            // Same transaction: when the payer matches a ledger student and an
            // OR number is present, auto-post a payment row to their ledger.
            // The service is idempotent, so corrections (re-saves) are safe.
            $posting = $postingService->postPayment($lockedRequest->fresh('formInput.course'));
        });

        $base = $isCorrection
            ? 'Payment details updated successfully.'
            : 'OR number saved. Status set to Paid.';

        return to_route('cashier.requests.show', $staffInput)
            ->with('success', $base.$this->ledgerPostingSuffix($posting, $posting['ledger'] ?? null));
    }

    /**
     * Human-readable suffix describing the selected ledger's auto-post outcome.
     *
     * @param  array{posted: bool, reason: string|null, ledger?: string|null}  $posting
     */
    private function ledgerPostingSuffix(array $posting, ?string $ledger = null): string
    {
        $label = match ($ledger) {
            'law' => 'law school',
            'graduate' => 'graduate',
            default => 'ledger',
        };

        if ($posting['posted']) {
            return " Posted to the {$label} ledger.";
        }

        return match ($posting['reason']) {
            'already_posted' => " This OR was already posted to the {$label} ledger.",
            'or_already_used' => " Could not update {$label} ledger: that OR number is already in use.",
            'student_not_found' => " No matching {$label} student — payment was not auto-posted.",
            'no_ledger_context' => " Matching {$label} student has no ledger records yet — payment was not auto-posted.",
            'no_course' => ' No course was selected and the ledger destination could not be determined — payment was not auto-posted.',
            'insert_failed' => " Could not post to the {$label} ledger (insert failed).",
            'missing_or', 'no_form' => '',
            default => $posting['reason'] === null
                ? ''
                : " ({$label} ledger auto-post skipped: {$posting['reason']}.)",
        };
    }
}
