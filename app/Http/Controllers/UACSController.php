<?php

namespace App\Http\Controllers;

use App\Http\Requests\UacsRequest;
use App\Exports\UACSExport;
use App\Models\UACS;
use App\Services\UACSService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/** @extends BaseResourceController<UACS> */
class UACSController extends BaseResourceController
{
    public function __construct(private readonly UACSService $uacsService) {}

    // ---- Config consumed by BaseResourceController::index() ----
    protected string $model = UACS::class;

    /** @var list<string> */
    protected array $searchableColumns = [
        'norsu_object_code',
        'norsu_sub_object_code',
        'uacs_object_code',
        'uacs_sub_object_code',
        'rca_code',
        'account_title',
    ];

    protected string $indexView = 'staff/uacs/uacs';

    protected string $resourceKey = 'uacs';

    protected int $perPage = 10000;

    protected string $orderBy = 'uacs_object_code';

    /** @var 'asc'|'desc' */
    protected string $orderDirection = 'asc';

    /** @var list<string> */
    protected array $sortableColumns = [
        'id',
        'norsu_object_code',
        'norsu_sub_object_code',
        'uacs_object_code',
        'uacs_sub_object_code',
        'rca_code',
        'account_title',
        'created_at',
    ];

    public function index(Request $request): Response
    {
        $uacs = UACS::query()
            ->orderBy('uacs_object_code', 'asc')
            ->orderBy('uacs_sub_object_code', 'asc')
            ->get();

        return Inertia::render($this->indexView, [
            'uacs' => $uacs,
        ]);
    }

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

    public function export()
    {
        return Excel::download(new UACSExport(), 'uacs_'.now()->format('Ymd_His').'.xlsx');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $file = $request->file('file');
        if (! $file instanceof UploadedFile) {
            return back()->with('error', 'The uploaded UACS file is invalid.');
        }

        try {
            $count = $this->uacsService->import($file);

            return back()->with('success', "Imported {$count} UACS records successfully.");
        } catch (\Throwable $e) {
            Log::error('Failed to import UACS file.', [
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to import the UACS file: '.$e->getMessage());
        }
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
