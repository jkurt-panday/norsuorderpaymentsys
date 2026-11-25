<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CashierPaymentRequest;
use App\Models\StaffInput;
use App\Services\CashierLedgerPostingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class CashierRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');
        $allowedStatuses = ['processed', 'paid', 'cancelled'];
        $search = trim((string) $request->query('search', ''));
        $dateFrom = (string) $request->query('date_from', '');
        $dateTo = (string) $request->query('date_to', '');

        $query = StaffInput::query()
            ->with(['formInput.membership', 'formInput.paymentDetailOption'])
            ->whereIn('staff_inputs.status', $allowedStatuses)
            ->when(in_array($status, $allowedStatuses, true), fn (Builder $query) => $query->where('staff_inputs.status', $status))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.mb_strtolower($search).'%';
                $query->where(function (Builder $query) use ($like): void {
                        $query->whereRaw('LOWER(staff_inputs.or_no) LIKE ?', [$like])
                        ->orWhereHas('formInput', function (Builder $query) use ($like): void {
                            $query->whereRaw('LOWER(reference_number) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(firstname_or_office) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(lastname_or_agency) LIKE ?', [$like])
                                ->orWhereRaw("LOWER(CONCAT_WS(' ', firstname_or_office, middlename_or_project, lastname_or_agency)) LIKE ?", [$like]);
                        });
                });
            })
            ->when($dateFrom !== '', fn (Builder $query) => $query->where('staff_inputs.created_at', '>=', $dateFrom.' 00:00:00'))
            ->when($dateTo !== '', fn (Builder $query) => $query->where('staff_inputs.created_at', '<=', $dateTo.' 23:59:59'));

        $formColumns = ['reference_number', 'firstname_or_office', 'email', 'amount'];
        $staffColumns = ['status', 'or_no', 'or_date', 'created_at'];
        $sort = (string) $request->query('sort', 'created_at');
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        if (in_array($sort, $formColumns, true)) {
            $query->join('form_inputs', 'form_inputs.id', '=', 'staff_inputs.form_input_id')
                ->select('staff_inputs.*')
                ->orderBy('form_inputs.'.$sort, $direction);
        } elseif (in_array($sort, $staffColumns, true)) {
            $query->orderBy('staff_inputs.'.$sort, $direction);
        } else {
            $query->orderByDesc('staff_inputs.created_at');
        }

        $perPage = max(1, min((int) $request->query('per_page', 10), 100));
        $paginator = $query->paginate($perPage)->withQueryString();

        $items = collect($paginator->items())->each(function (StaffInput $item): void {
            $item->formInput?->makeHidden('email');
        });

        return response()->json([
            'data' => $items->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'links' => ['next' => $paginator->nextPageUrl(), 'prev' => $paginator->previousPageUrl()],
        ]);
    }

    public function show(StaffInput $staffInput): JsonResponse
    {
        $staffInput->load(['formInput.membership', 'formInput.paymentDetailOption']);
        $staffInput->formInput?->makeHidden('email');

        return response()->json(['data' => $staffInput]);
    }

    public function updatePayment(
        CashierPaymentRequest $request,
        StaffInput $staffInput,
        CashierLedgerPostingService $postingService,
    ): JsonResponse {
        $validated = $request->validated();
        $posting = ['posted' => false, 'reason' => null, 'ledger' => null];

        try {
            DB::transaction(function () use ($validated, $staffInput, $postingService, &$posting): void {
                $locked = StaffInput::query()->lockForUpdate()->findOrFail($staffInput->id);
                $formInput = $locked->formInput()->with('course')->first();
                $targetStatus = $validated['status'];

                // A completed payment is immutable for cashiers. Staff/admin have
                // a separate OR-correction workflow when a correction is needed.
                if ($locked->status === 'paid' && filled($locked->or_no)) {
                    throw ValidationException::withMessages([
                        'status' => ['This payment is already paid and locked. Cashiers cannot edit it further.'],
                    ]);
                }

                if ($targetStatus === 'paid' && $locked->status === 'pending') {
                    throw ValidationException::withMessages(['status' => ['Cannot mark a pending request as paid. It must be processed first.']]);
                }

                if ($targetStatus === 'paid' && in_array($formInput?->course?->course_college, ['Graduate School', 'School of Law'], true) && $formInput?->student_num === null) {
                    throw ValidationException::withMessages(['student' => ['A student must be matched before this payment can be completed.']]);
                }

                $wasPaid = $locked->status === 'paid';
                $locked->update([
                    'or_no' => $validated['or_no'] ?? null,
                    'or_date' => $validated['or_date'] ?? null,
                    'status' => $targetStatus,
                ]);

                if ($wasPaid && $targetStatus === 'cancelled') {
                    $postingService->reversePayment($locked->fresh('formInput.course'));
                } elseif ($targetStatus === 'paid') {
                    $posting = $postingService->postPayment($locked->fresh('formInput.course'));
                }
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('API cashier payment update failed', ['staff_input_id' => $staffInput->id, 'error' => $exception->getMessage()]);

            return response()->json(['message' => 'Payment update failed. Please try again.'], 500);
        }

        return response()->json([
            'message' => 'Payment details and status updated successfully.',
            'data' => $staffInput->refresh()->load(['formInput.membership', 'formInput.paymentDetailOption']),
            'posting' => $posting,
        ]);
    }
}
