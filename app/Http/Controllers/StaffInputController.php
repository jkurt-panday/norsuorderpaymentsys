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

        // Filter by status if provided
        if ($request->has('status') && $request->status !== '') {
            $query->whereHas('staffInput', function ($q) use ($request) {
                $q->where('status', $request->status);
            });
        }

        // Filter by date range if provided
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search by reference number or name
        if ($request->has('search') && $request->search) {
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
        // Check if already processed
        if ($formInput->staffInput()->exists()) {
            return redirect()->route('staff.requests.show', $formInput)
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

            // Check if already processed
            if ($formInput->staffInput()->exists()) {
                throw new \Exception('This request has already been processed.');
            }

            // Create staff input
            StaffInput::create([
                'form_input_id' => $request->form_input_id,
                'fundcluster_id' => $request->fundcluster_id,
                'ref_document_id' => $request->ref_document_id,
                'ref_date' => $request->ref_date,
                'uacs_id' => $request->uacs_id,
                'status' => $request->status,
            ]);

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

    /**
     * Update staff processing data
     */
    public function update(StaffProcessingRequest $request, StaffInput $staffInput)
    {
        try {
            DB::beginTransaction();

            $staffInput->update([
                'fundcluster_id' => $request->fundcluster_id,
                'ref_document_id' => $request->ref_document_id,
                'ref_date' => $request->ref_date,
                'uacs_id' => $request->uacs_id,
                'status' => $request->status,
            ]);

            DB::commit();

            return redirect()->route('staff.requests.show', $staffInput->form_input_id)
                ->with('success', 'Request updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Staff processing update failed: ' . $e->getMessage());

            return back()
                ->withInput()
                ->with('error', 'Failed to update request: ' . $e->getMessage());
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