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
            'pendingRequests' => $statusCounts->get('pending', 0),
            'approvedRequests' => $statusCounts->get('approved', 0),
            'cancelledRequests' => $statusCounts->get('cancelled', 0),
            'statusBreakdown' => collect(['pending', 'approved', 'cancelled'])
                ->map(fn (string $status) => [
                    'name' => ucfirst($status),
                    'count' => $statusCounts->get($status, 0),
                ])
                ->values(),
            'requestsOverTime' => $requestsOverTime,
            'requestsByMembership' => FormInput::query()
                ->join('memberships', 'form_inputs.membership_id', '=', 'memberships.id')
                ->selectRaw('memberships.member_desc as name, count(*) as count')
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
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'LIKE', "%{$search}%")
                  ->orWhere('firstname_or_office', 'LIKE', "%{$search}%")
                  ->orWhere('lastname_or_agency', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        $formInputs = $query->paginate(15);
        $filters = $request->only(['search', 'status', 'date_from', 'date_to']);

        return Inertia::render('staff/requestform/request', compact('formInputs', 'filters'));
    }

    /**
     * Show form for processing a specific request
     */
    public function create(FormInput $formInput)
    {
        if ($formInput->staffInput()->exists()) {
            return  Inertia::render('staff/requestform/processrequest', compact(
            'formInput',
            'bankAccounts',
            'uacsList',
            'documents'
        )) ->with('warning', 'This request has already been processed.');
        }

        $bankAccounts = BankAccountInfo::orderBy('bank_name')->get();
        $uacsList = Uacs::orderBy('object_code')->get();

        $formInput->loadMissing('supportingDocuments');
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
        $staffInput->load('formInput');
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
                'status'          => $validated['status'], // <--- Status gets updated here
            ]);

            DB::commit();

            return Inertia::render('Staff/RequestForm/Request', [
                'success' => 'Processing updated successfully! New status: ' . ucfirst($staffInput->status)
            ]);

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
