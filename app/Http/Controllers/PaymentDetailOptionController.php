<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentDetailOptionRequest;
use App\Models\PaymentDetailOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class PaymentDetailOptionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $paymentOptions = PaymentDetailOption::orderBy('payment_desc')->paginate(10);

        return Inertia::render('staff/payment-options/payment', [
            'paymentOptions' => $paymentOptions,
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ]
        ]);
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
    public function destroy(PaymentDetailOption $paymentOption)
    {
        try {
            DB::beginTransaction();

            // Safety check: Don't allow deletion if referenced by any submissions.
            // Using withTrashed() makes this check robust against soft-deletes.
            $hasRelations = $paymentOption->formInputs()
                ->withTrashed()
                ->exists();

            if ($hasRelations) {
                return back()->with('error', 'Cannot delete payment option that has associated form inputs.');
            }

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