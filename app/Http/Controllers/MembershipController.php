<?php

namespace App\Http\Controllers;

use App\Http\Requests\MembershipRequest;
use App\Models\Membership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class MembershipController extends BaseResourceController
{
    // ---- Config consumed by BaseResourceController::index() ----
    protected string $model = Membership::class;
    protected array $searchableColumns = ['member_code', 'member_desc'];
    protected string $indexView = 'staff/memberships/membership';
    protected string $resourceKey = 'memberships';
    protected int $perPage = 10;
    protected string $orderBy = 'id';
    protected string $orderDirection = 'asc';
    // Allowlist: only these columns can be sorted on via ?sort=...
    protected array $sortableColumns = ['id', 'member_code', 'member_desc', 'created_at'];
    // Membership has no obvious status/type field to filter on today —
    // leave empty. Add entries here (e.g. ['type' => 'member_type']) if
    // one gets added later.
    protected array $filterableColumns = [];

    // index() is now inherited from BaseResourceController — no need to
    // redeclare it here. Everything below is unchanged from before.

    /**
     * Adds a `display_number` column via ROW_NUMBER() — a rank that's
     * always computed against a FIXED base order (id ASC), independent of
     * whatever sort the user currently has applied for display. That's
     * what keeps each row's number permanent/stable even when sorting
     * newest, oldest, A–Z, etc.
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
        return Inertia::render('staff/memberships/createmembership');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(MembershipRequest $request)
    {
        try {
            DB::beginTransaction();

            Membership::create($request->validated());

            DB::commit();

            return redirect()->route('staff.memberships.index')
                ->with('success', 'Membership created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create membership: ' . $e->getMessage(), [
                'request' => $request->validated()
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create membership. Please try again.');
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Membership $membership)
    {
        return Inertia::render('staff/memberships/editmembership', [
            'membership' => $membership,
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ]
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(MembershipRequest $request, Membership $membership)
    {
        try {
            DB::beginTransaction();

            $membership->update($request->validated());

            DB::commit();

            return redirect()->route('staff.memberships.index')
                ->with('success', 'Membership updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to update membership ID {$membership->id}: " . $e->getMessage());

            return back()
                ->withInput()
                ->with('error', 'Failed to update membership. Please try again.');
        }
    }

    /**
     * Remove the specified resource from storage safely.
     */
    public function destroy(Membership $membership)
    {
        // Safety check first — read-only, no transaction needed.
        if ($membership->formInputs()->exists()) {
            return back()->with('error', 'Cannot delete membership that has associated form inputs.');
        }

        try {
            DB::beginTransaction();

            $membership->delete();

            DB::commit();

            return redirect()->route('staff.memberships.index')
                ->with('success', 'Membership deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to delete membership ID {$membership->id}: " . $e->getMessage());

            return back()->with('error', 'Failed to delete membership. Please check if it is still in use.');
        }
    }
}