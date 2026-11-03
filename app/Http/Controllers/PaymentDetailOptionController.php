<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentDetailOptionRequest;
use App\Models\PaymentDetailOption;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/** @extends BaseResourceController<PaymentDetailOption> */
class PaymentDetailOptionController extends BaseResourceController
{
    /**
     * Display a listing of the resource.
     */
    protected string $model = PaymentDetailOption::class;

    /** @var list<string> */
    protected array $searchableColumns = ['payment_desc'];

    protected string $indexView = 'staff/payment-options/payment';

    protected string $resourceKey = 'paymentOptions';

    protected string $orderBy = 'id';

    /** @var 'asc'|'desc' */
    protected string $orderDirection = 'asc';

    /** @var list<string> */
    protected array $sortableColumns = ['id', 'payment_desc', 'created_at'];

    /**
     * Adds a `display_number` column via ROW_NUMBER() OVER (ORDER BY id ASC) — a
     * sequential rank computed within the result set using a fixed base order
     * (id ASC), independent of whatever sort the user currently has applied for
     * display. Because ROW_NUMBER() runs after the WHERE clause (search/filters)
     * but before the outer ORDER BY / pagination, each row's number stays
     * stable within a given sort/filter context: the result set is numbered 1,
     * 2, 3, ... starting from id ASC, and the outer ORDER BY only reorders
     * which row those numbers are attached to.
     *
     * This uses BaseResourceController's modifyIndexQuery() hook, which
     * runs right before sorting/pagination in the shared index() — so
     * search, sort, filters, and pagination all keep working exactly as
     * the base class already implements them.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function modifyIndexQuery(Builder $query, Request $request): Builder
    {
        return $query
            ->select('*')
            ->selectRaw('ROW_NUMBER() OVER (ORDER BY id ASC) as display_number');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('staff/payment-options/createpayment');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PaymentDetailOptionRequest $request): RedirectResponse
    {
        try {
            DB::beginTransaction();

            PaymentDetailOption::create($request->validated());

            DB::commit();

            return redirect()->route('staff.payment-options.index')
                ->with('success', 'Payment option created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create payment option: '.$e->getMessage(), [
                'request' => $request->validated(),
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
    public function edit(PaymentDetailOption $paymentOption): Response
    {
        return Inertia::render('staff/payment-options/editpayment', [
            'paymentOption' => $paymentOption,
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(PaymentDetailOptionRequest $request, PaymentDetailOption $paymentOption): RedirectResponse
    {
        try {
            DB::beginTransaction();

            $paymentOption->update($request->validated());

            DB::commit();

            return redirect()->route('staff.payment-options.index')
                ->with('success', 'Payment option updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to update payment option ID {$paymentOption->id}: ".$e->getMessage());

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
    public function destroy(PaymentDetailOption $paymentOption): RedirectResponse
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
            Log::error("Failed to delete payment option ID {$paymentOption->id}: ".$e->getMessage());

            return back()->with('error', 'Failed to delete payment option. Please check if it is still in use.');
        }
    }
}
