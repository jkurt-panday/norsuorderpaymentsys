<?php

namespace App\Http\Controllers;

use App\Http\Requests\UacsRequest;
use App\Models\UACS;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class UACSController extends BaseResourceController
{
    // ---- Config consumed by BaseResourceController::index() ----
    protected string $model = Uacs::class;
    protected array $searchableColumns = ['object_code', 'account_title'];
    protected string $indexView = 'staff/uacs/uacs';
    protected string $resourceKey = 'uacs';
    protected int $perPage = 10;
    protected string $orderBy = 'object_code';
    protected string $orderDirection = 'asc';
    protected array $sortableColumns = ['id', 'object_code', 'account_title', 'created_at'];
    protected array $filterableColumns = [];

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
        return Inertia::render('staff/uacs/createuacs');
    }

    public function store(UacsRequest $request)
    {
        try {
            DB::beginTransaction();

            Uacs::create($request->validated());

            DB::commit();

            return redirect()->route('staff.uacs.index')
                ->with('success', 'UACS created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create UACS: ' . $e->getMessage(), [
                'request' => $request->validated(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create UACS. Please try again.');
        }
    }

    public function edit(Uacs $uacs)
    {
        return Inertia::render('staff/uacs/edituacs', [
            'uacs' => $uacs,
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function update(UacsRequest $request, Uacs $uacs)
    {
        try {
            DB::beginTransaction();

            $uacs->update($request->validated());

            DB::commit();

            return redirect()->route('staff.uacs.index')
                ->with('success', 'UACS updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to update UACS ID {$uacs->id}: " . $e->getMessage());

            return back()
                ->withInput()
                ->with('error', 'Failed to update UACS. Please try again.');
        }
    }

    public function destroy(Uacs $uacs)
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
            Log::error("Failed to delete UACS ID {$uacs->id}: " . $e->getMessage());

            return back()
                ->with('error', 'Failed to delete UACS. Please check if it is still in use.');
        }
    }
}
