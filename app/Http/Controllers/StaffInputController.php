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

class StaffInputController extends Controller
{
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
        
        return view('staff.requests.index', compact('formInputs'));
    }

    /**
     * Show form for processing a specific request
     */
    public function create(FormInput $formInput)
    {
        if ($formInput->staffInput()->exists()) {
            return redirect()->route('staff.requests.show', $formInput->staffInput)
                ->with('warning', 'This request has already been processed.');
        }

        $bankAccounts = BankAccountInfo::orderBy('bank_name')->get();
        $uacsList = Uacs::orderBy('object_code')->get();
        $documents = $formInput->supportingDocuments;

        return view('staff.requests.process', compact(
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

        return view('staff.requests.edit', compact(
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
    public function show(StaffInput $staffInput)
    {
        $staffInput->load([
            'formInput.membership',
            'formInput.paymentDetailOption',
            'formInput.supportingDocuments',
            'bankAccount',
            'uacs',
            'referenceDocument'
        ]);

        return view('staff.requests.show', compact('staffInput'));
    }
}