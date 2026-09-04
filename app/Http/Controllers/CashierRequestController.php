<?php

namespace App\Http\Controllers;

use App\Http\Requests\CashierPaymentRequest;
use App\Models\StaffInput;
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
    ): RedirectResponse {
        $isCorrection = false;

        DB::transaction(function () use ($request, $staffInput, &$isCorrection): void {
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
        });

        return to_route('cashier.requests.show', $staffInput)
            ->with(
                'success',
                $isCorrection
                    ? 'Payment details updated successfully.'
                    : 'OR number saved. Status set to Paid.',
            );
    }
}
