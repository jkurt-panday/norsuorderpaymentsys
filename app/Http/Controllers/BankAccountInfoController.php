<?php

namespace App\Http\Controllers;

use App\Http\Requests\BankAccountInfoRequest;
use App\Models\BankAccountInfo;
use Illuminate\Http\Request;

class BankAccountInfoController extends Controller
{
    public function index()
    {
        $bankAccounts = BankAccountInfo::orderBy('bank_name')
            ->orderBy('account_name')
            ->paginate(10);
        return view('staff.bank-accounts.index', compact('bankAccounts'));
    }

    public function create()
    {
        return view('staff.bank-accounts.create');
    }

    public function store(BankAccountInfoRequest $request)
    {
        BankAccountInfo::create($request->validated());
        return redirect()->route('staff.bank-accounts.index')
            ->with('success', 'Bank account created successfully.');
    }

    public function edit(BankAccountInfo $bankAccountInfo)
    {
        return view('staff.bank-accounts.edit', compact('bankAccountInfo'));
    }

    public function update(BankAccountInfoRequest $request, BankAccountInfo $bankAccountInfo)
    {
        $bankAccountInfo->update($request->validated());
        return redirect()->route('staff.bank-accounts.index')
            ->with('success', 'Bank account updated successfully.');
    }

    public function destroy(BankAccountInfo $bankAccountInfo)
    {
        if ($bankAccountInfo->staffInputs()->exists()) {
            return back()->with('error', 'Cannot delete bank account that is in use.');
        }

        $bankAccountInfo->delete();
        return redirect()->route('staff.bank-accounts.index')
            ->with('success', 'Bank account deleted successfully.');
    }
}