<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicFormSubmissionRequest;
use App\Models\FormInput;
use App\Models\Membership;
// use App\Models\SupportingDocument;
use App\Models\PaymentDetailOption;
use App\Services\FileUploadService;
use App\Services\ReferenceNumberService;
use App\Services\ReceiptPDFService;
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Storage;
// use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FormInputController extends Controller
{
    protected FileUploadService $fileUploadService;
    protected ReferenceNumberService $referenceNumberService;
    protected ReceiptPDFService $receiptPDFService;

    public function __construct(
        FileUploadService $fileUploadService,
        ReferenceNumberService $referenceNumberService,
        ReceiptPDFService $receiptPDFService
    ) {
        $this->fileUploadService = $fileUploadService;
        $this->referenceNumberService = $referenceNumberService;
        $this->receiptPDFService = $receiptPDFService;
    }

    /**
     * Display the public submission form
     */
    public function create(): Response
    {
        $memberships = Membership::orderBy('member_desc')->get();
        $paymentOptions = PaymentDetailOption::orderBy('payment_desc')->get();

        return Inertia::render('public/SubmitForm', [
            'memberships' => $memberships,
            'paymentOptions' => $paymentOptions,
        ]);
    }

    public function store(PublicFormSubmissionRequest $request)
    {
        
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            // 2. Generate Reference Number
            $referenceNumber = $this->referenceNumberService->generate();

            
            $formInput = FormInput::create([
                'reference_number'              => $referenceNumber,
                'email'                         => $validated['email'],
                // 'purpose'                       => $validated['purpose'] ?? null,
                'contact_num'                   => $validated['contact_num'],
                'firstname_or_office'           => $validated['firstname_or_office'],
                'middlename_or_project'         => $validated['middlename_or_project'] ?? null,
                'lastname_or_agency'            => $validated['lastname_or_agency'],
                'office_or_college'             => $validated['office_or_college'],
                'position_or_designation'       => $validated['position_or_designation'],
                'address'                       => $validated['address'],
                'amount'                        => $validated['amount'],
                'request_type'                  => $validated['request_type'],
                'membership_id'                 => $validated['membership_id'],
                'payment_detail_option_id'      => $validated['payment_detail_option_id'],
            ]);

            // 4. Handle Uploaded Documents using FileUploadService
            if ($request->hasFile('documents')) {
                $this->fileUploadService->uploadDocuments(
                    $request->file('documents'),
                    $formInput
                );
            }

            DB::commit();

            return redirect()->route('public.success', [
                'reference_number' => $formInput->reference_number      // '' must be the same in the web.php
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            
            \Log::error('Public form submission failed', [
                'message' => $e->getMessage(),
                // 'trace' => $e->getTraceAsString(),
            ]);

            
            return back()
                ->withInput()
                ->with('error', 'Failed to submit request.');
        }
    }

    public function success(string $referenceNumber)
    {
        $formInput = FormInput::where('reference_number', $referenceNumber)->firstOrFail();
        $formInput->load(['membership', 'paymentDetailOption', 'supportingDocuments']);
        
        return Inertia::render('public/Success', [
            'reference_number' => $formInput->reference_number,
            'formInput' => $formInput,
        ]);
    }

    /**
    * Stream the PDF receipt in the browser window.
    */
    public function printReceipt(string $referenceNumber)
    {
        $formInput = FormInput::where(
            'reference_number',
            $referenceNumber
        )->firstOrFail();

        // dd($formInput);
    
        return $this->receiptPDFService
            ->make($formInput)
            ->inline();
    }

    /**
    * Download the PDF receipt file.
    */
    public function downloadReceipt(FormInput $formInput)
    {
        return $this->receiptPDFService
            ->make($formInput)
            ->name("receipt-{$formInput->reference_number}.pdf");
    }

    /**
     * Display a specific form input (for staff viewing)
     */
    // public function show(FormInput $formInput): Response
    // {
    //     $formInput->load([
    //         'membership',
    //         'paymentDetailOption',
    //         'supportingDocuments',
    //         'staffInput.bankAccount',
    //         'staffInput.uacs',
    //         'staffInput.referenceDocument',
    //     ]);

    //     return Inertia::render('staff/requestform/showrequest', compact('formInput'));
    // }
}

// Eager-load relations before rendering, so the Success page
// receives formInput.membership and formInput.paymentDetailOption
// as populated nested objects instead of undefined.
// $formInput->load(['membership', 'paymentDetailOption', 'supportingDocuments']);

// Render the success page directly — no separate success() action
// or route needed, since we already have everything we need here.
// return Inertia::render('public/Success', [
//     'reference_number' => $formInput->reference_number,
//     'formInput' => $formInput,
// ]);
// 
// 
// // validation is done with publicformsubmissionrequest service
// 1. Validate Form & Files
// $request->validate([
//     'firstname_or_office'           => 'required|string|max:255',
//     'middlename_or_project'         => 'nullable|string|max:255',
//     'lastname_or_agency'            => 'required|string|max:255',
//     'office_or_college'             => 'required|string|max:255',
//     'position_or_designation'       => 'required|string|max:255',
//     'contact_num'                   => 'required|string|max:50',
//     'email'                         => 'required|email|max:255',
//     'address'                       => 'required|string',
//     'request_type'                  => 'required|string',
//     'amount'                        => 'required|numeric|min:0',
//     'membership_id'                 => 'required|exists:memberships,id',
//     'payment_detail_option_id'      => 'required|exists:payment_detail_options,id',

//     'documents' => 'nullable|array',
//     'documents.*' => 'file|mimes:pdf,jpg,jpeg,png,webp,svg|max:10240',
// ]);
//
// // 3. Create FormInput Record using your explicit mapping
// $formInput = FormInput::create([
//     'reference_number'          => $referenceNumber,
//     'email'                     => $request->email,
//     'contact_num'               => $request->contact_num,
//     'firstname_or_office'       => $request->firstname_or_office,
//     'middlename_or_project'     => $request->middlename_or_project,
//     'lastname_or_agency'        => $request->lastname_or_agency,
//     'office_or_college'         => $request->office_or_college,
//     'position_or_designation'   => $request->position_or_designation,
//     'address'                   => $request->address,
//     'amount'                    => $request->amount,
//     'request_type'              => $request->request_type,
//     'membership_id'             => $request->membership_id,
//     'payment_detail_option_id'  => $request->payment_detail_option_id,
// ]);
//