<?php

namespace App\Http\Controllers;

use App\Http\Requests\BankAccountInfoRequest;
use App\Models\BankAccountInfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class BankAccountInfoController extends BaseResourceController
{
    /**
     * Display list of bank accounts
     */
    protected string $model = BankAccountInfo::class;

    protected array $searchableColumns = ['bank_name', 'account_name'];

    protected string $indexView = 'staff/bankaccounts/bankaccount';

    protected string $resourceKey = 'bankAccounts';

    protected string $orderBy = 'id';

    protected string $orderDirection = 'asc';

    protected array $secondaryOrderBy = [
        ['column' => 'account_name', 'direction' => 'asc'],
    ];

    // Added 'fund_cluster' and 'account_num' — the frontend now has sortable
    // arrows on every column (ID through Account Number), and this allowlist
    // is what actually lets those two new ones take effect. Without a key
    // being present here, BaseResourceController silently ignores a
    // requested ?sort= for it and falls back to the default order.
    protected array $sortableColumns = ['id', 'bank_name', 'account_name', 'fund_cluster', 'account_num', 'created_at'];

    protected array $filterableColumns = [];

    /**
     * Show create form
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

    public function create()
    {
        return Inertia::render('staff/bankaccounts/createbankaccount');
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
            Log::error('Failed to create bank account: '.$e->getMessage(), [
                'request' => $request->validated(),
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
        return Inertia::render('staff/bankaccounts/editbankaccount', compact('bankAccount'));
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
            Log::error("Failed to update bank account ID {$bankAccount->id}: ".$e->getMessage());

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
            $hasRelations = $bankAccount->staffInputs()->exists();

            if ($hasRelations) {
                DB::rollBack();

                return back()->with('error', 'Cannot delete a bank account that is in use by processing records.');
            }

            $bankAccount->delete();

            DB::commit();

            return redirect()->route('staff.bank-accounts.index')
                ->with('success', 'Bank account deleted successfully.');

        } catch (\Throwable $e) {
            // \Throwable (not just \Exception) so this also catches a
            // \Error — e.g. "Call to undefined method staffInputs()" if
            // that relation is missing/misnamed on the model. Without this,
            // that kind of error crashes uncaught instead of surfacing as
            // a flash message, which is why deletes could fail silently
            // with no toast at all.
            DB::rollBack();
            Log::error("Failed to delete bank account ID {$bankAccount->id}: ".$e->getMessage());

            return back()->with('error', 'Failed to delete bank account. Please check if it is still in use.');
        }
    }
}
