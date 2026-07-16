<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentDetailOptionRequest;
use App\Models\PaymentDetailOption;
use Illuminate\Http\Request;

class PaymentDetailOptionController extends Controller
{
    public function index()
    {
        $paymentOptions = PaymentDetailOption::orderBy('payment_desc')->paginate(10);
        return view('staff.payment-options.index', compact('paymentOptions'));
    }

    public function create()
    {
        return view('staff.payment-options.create');
    }

    public function store(PaymentDetailOptionRequest $request)
    {
        PaymentDetailOption::create($request->validated());
        return redirect()->route('staff.payment-options.index')
            ->with('success', 'Payment option created successfully.');
    }

    public function edit(PaymentDetailOption $paymentDetailOption)
    {
        return view('staff.payment-options.edit', compact('paymentDetailOption'));
    }

    public function update(PaymentDetailOptionRequest $request, PaymentDetailOption $paymentDetailOption)
    {
        $paymentDetailOption->update($request->validated());
        return redirect()->route('staff.payment-options.index')
            ->with('success', 'Payment option updated successfully.');
    }

    public function destroy(PaymentDetailOption $paymentDetailOption)
    {
        if ($paymentDetailOption->formInputs()->exists()) {
            return back()->with('error', 'Cannot delete payment option that has associated form inputs.');
        }

        $paymentDetailOption->delete();
        return redirect()->route('staff.payment-options.index')
            ->with('success', 'Payment option deleted successfully.');
    }
}