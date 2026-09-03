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
        $status = in_array($requestedStatus, ['processed', 'paid'], true)
            ? $requestedStatus
            : 'processed';
        $search = trim($request->string('search')->toString());

        $requests = StaffInput::query()
            ->with(['formInput.membership', 'formInput.paymentDetailOption'])
            ->whereIn('status', ['processed', 'paid'])
            ->where('status', $status)
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
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('cashier/requests/Index', [
            'requests' => $requests,
            'filters' => ['search' => $search, 'status' => $status],
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
