<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuthorizedOfficialRequest;
use App\Models\AuthorizedOfficial;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AuthorizedOfficialController extends BaseResourceController
{
    // ---- Config consumed by BaseResourceController::index() ----
    protected string $model = AuthorizedOfficial::class;

    protected array $searchableColumns = ['name', 'position', 'course'];

    protected string $indexView = 'staff/authorized-officials/authorizedofficial';

    protected string $resourceKey = 'authorizedOfficials';

    protected int $perPage = 10;

    protected string $orderBy = 'updated_at';

    protected string $orderDirection = 'desc';

    // Allowlist: only these columns can be sorted on via ?sort=...
    protected array $sortableColumns = ['id', 'name', 'position', 'course', 'is_active', 'created_at'];

    protected array $filterableColumns = [];

    public function create()
    {
        return Inertia::render('staff/authorized-officials/createauthorizedofficial', [
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function store(AuthorizedOfficialRequest $request)
    {
        try {
            DB::beginTransaction();

            // The most recently saved official becomes the current signatory;
            // every other record is deactivated to keep exactly one active.
            AuthorizedOfficial::query()->update(['is_active' => false]);
            AuthorizedOfficial::create(array_merge($request->validated(), ['is_active' => true]));

            DB::commit();

            return redirect()->route('staff.authorized-officials.index')
                ->with('success', 'Authorized official created and set as the current signatory.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create authorized official: '.$e->getMessage(), [
                'request' => $request->validated(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create the authorized official. Please try again.');
        }
    }

    public function edit(AuthorizedOfficial $authorizedOfficial)
    {
        return Inertia::render('staff/authorized-officials/editauthorizedofficial', [
            'authorizedOfficial' => $authorizedOfficial,
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function update(AuthorizedOfficialRequest $request, AuthorizedOfficial $authorizedOfficial)
    {
        try {
            DB::beginTransaction();

            AuthorizedOfficial::query()->where('id', '!=', $authorizedOfficial->id)
                ->update(['is_active' => false]);
            $authorizedOfficial->update(array_merge($request->validated(), ['is_active' => true]));

            DB::commit();

            return redirect()->route('staff.authorized-officials.index')
                ->with('success', 'Authorized official updated.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to update authorized official ID {$authorizedOfficial->id}: ".$e->getMessage());

            return back()
                ->withInput()
                ->with('error', 'Failed to update the authorized official. Please try again.');
        }
    }

    public function destroy(AuthorizedOfficial $authorizedOfficial)
    {
        if ($authorizedOfficial->is_active) {
            return back()->with('error', 'Cannot delete the currently active authorized official. Set another one as the current signatory first.');
        }

        try {
            DB::beginTransaction();

            $authorizedOfficial->delete();

            DB::commit();

            return redirect()->route('staff.authorized-officials.index')
                ->with('success', 'Authorized official deleted.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to delete authorized official ID {$authorizedOfficial->id}: ".$e->getMessage());

            return back()->with('error', 'Failed to delete the authorized official.');
        }
    }

    public function setActive(AuthorizedOfficial $authorizedOfficial)
    {
        if ($authorizedOfficial->is_active) {
            return back()->with('success', 'This official is already the current signatory.');
        }

        try {
            DB::beginTransaction();

            AuthorizedOfficial::query()->update(['is_active' => false]);
            $authorizedOfficial->update(['is_active' => true]);

            DB::commit();

            return redirect()->route('staff.authorized-officials.index')
                ->with('success', $authorizedOfficial->name.' is now the current authorized official.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to set authorized official ID {$authorizedOfficial->id} as active: ".$e->getMessage());

            return back()->with('error', 'Failed to set the authorized official as active. Please try again.');
        }
    }
}
