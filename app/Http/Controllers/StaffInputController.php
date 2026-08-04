<?php

namespace App\Http\Controllers;

use App\Http\Requests\StaffProcessingRequest;
use App\Models\FormInput;
use App\Models\StaffInput;
use App\Models\BankAccountInfo;
use App\Models\Uacs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class StaffInputController extends Controller
{
    /**
     * Display the staff dashboard.
     */
    public function dashboard()
    {
        $startDate = now()->subDays(29)->startOfDay();
        $statusCounts = StaffInput::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $latestByStatus = StaffInput::query()
        ->selectRaw('status, MAX(created_at) as latest')
        ->groupBy('status')
        ->pluck('latest', 'status');

        // "Unprocessed" isn't a StaffInput.status value — it's a FormInput
        // that has no StaffInput row at all (same definition already used
        // in index(): $query->whereDoesntHave('staffInput')). Pulled as one
        // query (count + latest together) rather than two separate calls.
        $unprocessedStats = FormInput::query()
            ->whereDoesntHave('staffInput')
            ->selectRaw('count(*) as count, MAX(created_at) as latest')
            ->first();
        $unprocessedCount = $unprocessedStats->count ?? 0;
        $unprocessedLastDate = $unprocessedStats->latest ?? null;

        $totalRequestsLastDate = FormInput::max('created_at');
        $requestsOverTime = FormInput::query()
            ->where('created_at', '>=', $startDate)
            ->get(['created_at'])
            ->groupBy(fn (FormInput $formInput) => $formInput->created_at->toDateString())
            ->map(fn ($requests, $date) => ['date' => $date, 'count' => $requests->count()]);

        $requestsOverTime = collect(range(0, 29))
            ->map(function (int $daysAgo) use ($requestsOverTime) {
                $date = now()->subDays(29 - $daysAgo)->toDateString();
                return $requestsOverTime->get($date, ['date' => $date, 'count' => 0]);
            })
            ->values();
        $recentRequests = FormInput::with('staffInput')
            ->latest()
            ->take(8)
            ->get();
        $recentActivity = StaffInput::with('formInput')
            ->latest()
            ->take(8)
            ->get()
            ->map(fn (StaffInput $staffInput) => [
                'id' => $staffInput->id,
                'event' => 'processed',
                'description' => "Request {$staffInput->formInput->reference_number} was processed as " . ucfirst($staffInput->status) . '.',
                'causerName' => null,
                'createdAt' => $staffInput->created_at->toISOString(),
            ]);

        return Inertia::render('staff/staffdashboard', [
            'totalRequests' => FormInput::count(),
            'unprocessedRequests' => $unprocessedCount,
            'pendingRequests' => $statusCounts->get('pending', 0),
            'approvedRequests' => $statusCounts->get('approved', 0),
            'cancelledRequests' => $statusCounts->get('cancelled', 0),
            'totalRequestsLastDate' => $totalRequestsLastDate,
            'unprocessedLastDate' => $unprocessedLastDate,
            'pendingLastDate' => $latestByStatus->get('pending'),
            'approvedLastDate' => $latestByStatus->get('approved'),
            'cancelledLastDate' => $latestByStatus->get('cancelled'),
            // Order here (Unprocessed, Pending, Approved, Cancelled) must match
            // the pieColors array on the frontend: ['#94a3b8', '#f59e0b', '#22c55e', '#ef4444']
            'statusBreakdown' => collect([
                    ['name' => 'Unprocessed', 'count' => $unprocessedCount],
                ])
                ->concat(
                    collect(['pending', 'approved', 'cancelled'])
                        ->map(fn (string $status) => [
                            'name' => ucfirst($status),
                            'count' => $statusCounts->get($status, 0),
                        ])
                )
                ->values(),
            'requestsOverTime' => $requestsOverTime,
            'requestsByMembership' => FormInput::query()
                ->join('memberships', 'form_inputs.membership_id', '=', 'memberships.id')
                ->selectRaw('memberships.member_code as name, count(*) as count')
                ->groupBy('memberships.id', 'memberships.member_desc')
                ->orderBy('name')
                ->get(),
            'recentRequests' => $recentRequests,
            'recentActivity' => $recentActivity,
        ]);
    }
    /**
     * Display list of requests for staff processing
     */
    public function index(Request $request)
    {
        $query = FormInput::with(['membership', 'staffInput'])
            ->orderBy('created_at', 'desc');

        // Filter by status (handling 'unprocessed' gracefully)
        if ($request->has('status') && $request->status !== '') {
            if ($request->status === 'unprocessed') {
                $query->whereDoesntHave('staffInput');
            } else {
                $query->whereHas('staffInput', function ($q) use ($request) {
                    $q->where('status', $request->status);
                });
            }
        }

        // Fast Date Range Queries (Index Friendly)
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from . ' 00:00:00');
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        // Search (grouped to avoid breaking status/date filters)
        // Wrapped in LOWER() on both sides so matching is case-insensitive
        // regardless of the database's default collation (e.g. Postgres'
        // LIKE is case-sensitive by default, unlike MySQL's usual collation).
        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(reference_number) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(firstname_or_office) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(lastname_or_agency) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"]);
            });
        }

        $formInputs = $query->paginate(10);
        $filters = $request->only(['search', 'status', 'date_from', 'date_to']);

        return Inertia::render('staff/requestform/request', compact('formInputs', 'filters'));
    }

    /**
     * Show form for processing a specific request
     */
   public function create(FormInput $formInput)
{
    if ($formInput->staffInput()->exists()) {
        return redirect()
            ->route('staff.requests.show', $formInput)
            ->with('warning', 'This request has already been processed.');
    }

    $bankAccounts = BankAccountInfo::orderBy('bank_name')->get();
    $uacsList = Uacs::orderBy('object_code')->get();

    $formInput->loadMissing(['membership', 'paymentDetailOption', 'supportingDocuments']);
    $documents = $formInput->supportingDocuments()->get();

    return Inertia::render('staff/requestform/processrequest', compact(
        'formInput',
        'bankAccounts',
        'uacsList',
        'documents'
    ));
}

    /**
     * Store staff processing data
     */
    public function store(StaffProcessingRequest $request)
    {
        try {
            DB::beginTransaction();

            $formInput = FormInput::findOrFail($request->form_input_id);

            if ($formInput->staffInput()->exists()) {
                throw new \Exception('This request has already been processed.');
            }

            // Fixed: Added bank_account_id using validated array data
            StaffInput::create(array_merge(
                $request->validated(),
                ['form_input_id' => $formInput->id]
            ));

            DB::commit();

            return redirect()->route('staff.requests.index')
                ->with('success', 'Request processed successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Staff processing failed: ' . $e->getMessage());

            return back()
                ->withInput()
                ->with('error', 'Failed to process request: ' . $e->getMessage());
        }
    }

    /**
     * Show form for editing staff processing
     */
        public function edit(StaffInput $staffInput)
        {
            $staffInput->load(['formInput.membership', 'formInput.paymentDetailOption']);
            $bankAccounts = BankAccountInfo::orderBy('bank_name')->get();
            $uacsList = Uacs::orderBy('object_code')->get();
            $documents = $staffInput->formInput->supportingDocuments;

            return Inertia::render('staff/requestform/editrequest', compact(
                'staffInput',
                'bankAccounts',
                'uacsList',
                'documents'
            ));
        }

    // App/Http/Controllers/StaffInputController.php

public function update(StaffProcessingRequest $request, StaffInput $staffInput)
{
    // Validates request including status
    $validated = $request->validated();

    try {
        DB::beginTransaction();

        $staffInput->update([
            'fundcluster_id'  => $validated['fundcluster_id'],
            'ref_document_id' => $validated['ref_document_id'] ?? null,
            'ref_date'        => $validated['ref_date'],
            'uacs_id'         => $validated['uacs_id'],
            'status'          => $validated['status'],
        ]);

        DB::commit();

        return redirect()->route('staff.requests.index')
            ->with('success', 'Processing updated successfully! New status: ' . ucfirst($staffInput->status));

    } catch (\Exception $e) {
        DB::rollBack();
        return back()->withInput()->with('error', 'Failed to update processing: ' . $e->getMessage());
    }
}
    /**
     * Display staff processing details
     */
    public function show(FormInput $formInput)
    {
        $formInput->load([
            'membership',
            'paymentDetailOption',
            'supportingDocuments',
            'staffInput.bankAccount',
            'staffInput.uacs',
            'staffInput.referenceDocument',
        ]);

        return Inertia::render('staff/requestform/showrequest', compact('formInput'));
    }
}