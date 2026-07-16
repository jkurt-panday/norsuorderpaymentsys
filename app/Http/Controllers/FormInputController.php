<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicFormSubmissionRequest;
use App\Models\FormInput;
use App\Models\SupportingDocument;
use App\Services\FileUploadService;
use App\Services\ReferenceNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FormInputController extends Controller
{
    protected FileUploadService $fileUploadService;
    protected ReferenceNumberService $referenceNumberService;

    public function __construct(
        FileUploadService $fileUploadService,
        ReferenceNumberService $referenceNumberService
    ) {
        $this->fileUploadService = $fileUploadService;
        $this->referenceNumberService = $referenceNumberService;
    }

    /**
     * Display the public submission form
     */
    public function create()
    {
        $memberships = \App\Models\Membership::orderBy('member_desc')->get();
        $paymentOptions = \App\Models\PaymentDetailOption::orderBy('payment_desc')->get();
        
        return view('public.submit', compact('memberships', 'paymentOptions'));
    }

    /**
     * Store a new form submission
     */
    public function store(PublicFormSubmissionRequest $request)
    {
        try {
            DB::beginTransaction();

            // Generate reference number
            $referenceNumber = $this->referenceNumberService->generate();

            // Create form input
            $formInput = FormInput::create([
                'reference_number' => $referenceNumber,
                'email' => $request->email,
                'contact_num' => $request->contact_num,
                'firstname_or_office' => $request->firstname_or_office,
                'middlename_or_project' => $request->middlename_or_project,
                'lastname_or_agency' => $request->lastname_or_agency,
                'office_or_college' => $request->office_or_college,
                'position_or_designation' => $request->position_or_designation,
                'address' => $request->address,
                'amount' => $request->amount,
                'request_type' => $request->request_type,
                'membership_id' => $request->membership_id,
                'payment_detail_option_id' => $request->payment_detail_option_id,
            ]);

            // Upload and store documents
            $uploadedDocuments = [];
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $file) {
                    $uploadedDocument = $this->fileUploadService->upload(
                        $file,
                        $formInput->id,
                        'supporting-documents'
                    );
                    $uploadedDocuments[] = $uploadedDocument;
                }
            }

            DB::commit();

            // Return success view with reference number
            return view('public.success', [
                'formInput' => $formInput,
                'documents' => $uploadedDocuments,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Form submission failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to submit request. Please try again or contact support.');
        }
    }

    /**
     * Display a specific form input (for staff viewing)
     */
    public function show(FormInput $formInput)
    {
        $formInput->load([
            'membership',
            'paymentDetailOption',
            'supportingDocuments',
            'staffInput.bankAccount',
            'staffInput.uacs',
            'staffInput.referenceDocument'
        ]);

        return view('staff.requests.show', compact('formInput'));
    }
}