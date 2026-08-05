<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentDetailOptionRequest;
use App\Models\PaymentDetailOption;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class PaymentDetailOptionController extends BaseResourceController
{
    /**
     * Display a listing of the resource.
     */
    protected string $model = PaymentDetailOption::class;
    protected array $searchableColumns = ['payment_desc'];
    protected string $indexView = 'staff/payment-options/payment';
    protected string $resourceKey = 'paymentOptions';
    protected string $orderBy = 'id';
    protected string $orderDirection = 'asc';
   protected array $sortableColumns = ['id', 'payment_desc', 'created_at'];
    protected array $filterableColumns = [];

/**
     * Adds a `display_number` column via ROW_NUMBER() — a permanent number
     * tied to creation order (id ASC), completely independent of whatever
     * sort the user currently has applied for display. This is what makes
     * the newest record always show its true position (e.g. record #7,
     * the 7th one ever created, always shows "7" — whether it's listed
     * first under "Newest" or last under "Oldest").
     *
     * This uses BaseResourceController's modifyIndexQuery() hook, which
     * runs right before sorting/pagination in the shared index() — so
     * search, sort, filters, and pagination all keep working exactly as
     * the base class already implements them. No need to override index()
     * itself (which previously broke: its `: Response` return type has to
     * be matched exactly on any override, which the earlier attempt missed).
/**
     * Adds a `display_number` column via a correlated subquery — a
     * permanent number tied to creation order (id ASC), computed against
     * the FULL, unfiltered table rather than a ROW_NUMBER() window
     * function. This is what makes a record's number stay stable whether
     * it's shown in the full list, in a search result, on any page, or
     * under any sort — because the subquery counts against the entire
     * table, completely independent of the outer query's WHERE clause.
     *
     * This uses BaseResourceController's modifyIndexQuery() hook, which
     * runs right before sorting/pagination in the shared index() — so
     * search, sort, filters, and pagination all keep working exactly as
     * the base class already implements them. No need to override index()
     * itself (which previously broke: its `: Response` return type has to
     * be matched exactly on any override, which the earlier attempt missed).
     */
    protected function modifyIndexQuery(Builder $query, Request $request): Builder
    {
        $table = (new $this->model)->getTable();

        return $query
            ->select('*')
            ->selectRaw(
                "(SELECT COUNT(*) FROM {$table} AS t2 WHERE t2.id <= {$table}.id) as display_number"
            );
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('staff/payment-options/createpayment');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PaymentDetailOptionRequest $request)
    {
        try {
            DB::beginTransaction();

            PaymentDetailOption::create($request->validated());

            DB::commit();

            return redirect()->route('staff.payment-options.index')
                ->with('success', 'Payment option created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create payment option: ' . $e->getMessage(), [
                'request' => $request->validated()
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create payment option. Please try again.');
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * Note: Variable renamed to $paymentOption to match Laravel's
     * automatic Route-Model Binding expectations for the 'payment-options' resource.
     */
    public function edit(PaymentDetailOption $paymentOption)
    {
        return Inertia::render('staff/payment-options/editpayment', [
            'paymentOption' => $paymentOption,
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ]
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(PaymentDetailOptionRequest $request, PaymentDetailOption $paymentOption)
    {
        try {
            DB::beginTransaction();

            $paymentOption->update($request->validated());

            DB::commit();

            return redirect()->route('staff.payment-options.index')
                ->with('success', 'Payment option updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to update payment option ID {$paymentOption->id}: " . $e->getMessage());

            return back()
                ->withInput()
                ->with('error', 'Failed to update payment option. Please try again.');
        }
    }

    /**
     * Remove the specified resource from storage safely.
     */
 /**
     * Remove the specified resource from storage safely.
     */
 public function destroy(PaymentDetailOption $paymentOption)
{
    if ($paymentOption->formInputs()->exists()) {
        return back()->with('error', 'Cannot delete payment option that has associated form inputs.');
    }

    try {
        DB::beginTransaction();

        $paymentOption->delete();

        DB::commit();

        return redirect()->route('staff.payment-options.index')
            ->with('success', 'Payment option deleted successfully.');

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error("Failed to delete payment option ID {$paymentOption->id}: " . $e->getMessage());

        return back()->with('error', 'Failed to delete payment option. Please check if it is still in use.');
    }
}
}