<?php

namespace App\Http\Controllers;

use App\Http\Requests\UacsRequest;
use App\Models\UACS;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/** @extends BaseResourceController<UACS> */
class UACSController extends BaseResourceController
{
    // ---- Config consumed by BaseResourceController::index() ----
    protected string $model = UACS::class;

    /** @var list<string> */
    protected array $searchableColumns = ['object_code', 'account_title'];

    protected string $indexView = 'staff/uacs/uacs';

    protected string $resourceKey = 'uacs';

    protected int $perPage = 10;

    protected string $orderBy = 'object_code';

    /** @var 'asc'|'desc' */
    protected string $orderDirection = 'asc';

    /** @var list<string> */
    protected array $sortableColumns = ['id', 'object_code', 'account_title', 'created_at'];

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function modifyIndexQuery(Builder $query, Request $request): Builder
    {
        return $query
            ->select('*')
            ->selectRaw('(SELECT COUNT(*) FROM uacs AS t2 WHERE t2.id <= uacs.id) as display_number');
    }

    public function create(): Response
    {
        return Inertia::render('staff/uacs/createuacs');
    }

    public function store(UacsRequest $request): RedirectResponse
    {
        try {
            DB::beginTransaction();

            UACS::create($request->validated());

            DB::commit();

            return redirect()->route('staff.uacs.index')
                ->with('success', 'UACS created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create UACS: '.$e->getMessage(), [
                'request' => $request->validated(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create UACS. Please try again.');
        }
    }

    public function edit(UACS $uacs): Response
    {
        return Inertia::render('staff/uacs/edituacs', [
            'uacs' => $uacs,
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function update(UacsRequest $request, UACS $uacs): RedirectResponse
    {
        try {
            DB::beginTransaction();

            $uacs->update($request->validated());

            DB::commit();

            return redirect()->route('staff.uacs.index')
                ->with('success', 'UACS updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to update UACS ID {$uacs->id}: ".$e->getMessage());

            return back()
                ->withInput()
                ->with('error', 'Failed to update UACS. Please try again.');
        }
    }

    public function destroy(UACS $uacs): RedirectResponse
    {
        if ($uacs->staffInputs()->exists()) {
            return back()->with('error', 'Cannot delete UACS that has associated staff inputs.');
        }

        try {
            DB::beginTransaction();

            $uacs->delete();

            DB::commit();

            return redirect()->route('staff.uacs.index')
                ->with('success', 'UACS deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to delete UACS ID {$uacs->id}: ".$e->getMessage());

            return back()
                ->with('error', 'Failed to delete UACS. Please check if it is still in use.');
        }
    }
}
