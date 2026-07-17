<?php

namespace App\Http\Controllers;

use App\Http\Requests\BankAccountInfoRequest;
use App\Models\BankAccountInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BankAccountInfoController extends Controller
{
    /**
     * Display list of bank accounts
     */
    public function index()
    {
        $bankAccounts = BankAccountInfo::orderBy('bank_name')
            ->orderBy('account_name')
            ->paginate(10);

        return view('staff.bank-accounts.index', compact('bankAccounts'));
    }

    /**
     * Show create form
     */
    public function create()
    {
        return view('staff.bank-accounts.create');
    }

    /**
     * Store a new bank account
     */
    public function store(BankAccountInfoRequest $request)
    {
        try {
            DB::beginTransaction();

            BankAccountInfo::create($request->validated());

            DB::commit();

            return redirect()->route('staff.bank-accounts.index')
                ->with('success', 'Bank account created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create bank account: ' . $e->getMessage(), [
                'request' => $request->validated()
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create bank account. Please try again.');
        }
    }

    /**
     * Show edit form
     * 
     * Note: Renamed variable to $bankAccount to align perfectly with 
     * Laravel's Route-Model Binding standard pattern.
     */
    public function edit(BankAccountInfo $bankAccount)
    {
        return view('staff.bank-accounts.edit', compact('bankAccount'));
    }

    /**
     * Update bank account details
     */
    public function update(BankAccountInfoRequest $request, BankAccountInfo $bankAccount)
    {
        try {
            DB::beginTransaction();

            $bankAccount->update($request->validated());

            DB::commit();

            return redirect()->route('staff.bank-accounts.index')
                ->with('success', 'Bank account updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to update bank account ID {$bankAccount->id}: " . $e->getMessage());

            return back()
                ->withInput()
                ->with('error', 'Failed to update bank account. Please try again.');
        }
    }

    /**
     * Delete a bank account safely
     */
    public function destroy(BankAccountInfo $bankAccount)
    {
        try {
            DB::beginTransaction();

            // Safety check: Don't allow deletion of accounts with historical references.
            // Using withTrashed() here is a defensive programming best practice.
            $hasRelations = $bankAccount->staffInputs()
                ->withTrashed() // Ensures we check soft-deleted records too
                ->exists();

            if ($hasRelations) {
                return back()->with('error', 'Cannot delete a bank account that is in use by processing records.');
            }

            $bankAccount->delete();

            DB::commit();

            return redirect()->route('staff.bank-accounts.index')
                ->with('success', 'Bank account deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to delete bank account ID {$bankAccount->id}: " . $e->getMessage());

            return back()->with('error', 'Failed to delete bank account. Please check if it is still in use.');
        }
    }
}