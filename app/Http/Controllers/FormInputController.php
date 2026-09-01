<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicFormSubmissionRequest;
use App\Models\FormInput;
use App\Models\Membership;
// use App\Models\SupportingDocument;
use App\Models\PaymentDetailOption;
use App\Models\UserProfile;
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
        $memberships = Membership::query()->orderBy('member_desc')->get();
        $paymentOptions = PaymentDetailOption::query()->orderBy('payment_desc')->get();

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

            // If user is authenticated, save/update their profile preferences for next time
            if (auth()->check()) {
                UserProfile::updateOrCreate(
                    ['user_id' => auth()->id()],
                    [
                        'firstname_or_office'     => $validated['firstname_or_office'],
                        'middlename_or_project'   => $validated['middlename_or_project'] ?? null,
                        'lastname_or_agency'      => $validated['lastname_or_agency'],
                        'contact_num'             => $validated['contact_num'],
                        'office_or_college'       => $validated['office_or_college'],
                        'position_or_designation' => $validated['position_or_designation'],
                        'address'                 => $validated['address'],
                    ]
                );
            }

            return redirect()->route('public.success', [
                'reference_number' => $formInput->reference_number
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
        $formInput = FormInput::query()
            ->where('reference_number', $referenceNumber)
            ->firstOrFail();
        $formInput->load(['membership', 'paymentDetailOption', 'supportingDocuments']);

        $user = auth()->user();
        $dashboardUrl = $user ? match ($user->role) {
            'admin' => '/admin/dashboard',
            'staff' => '/staff/staffdashboard',
            'client' => '/client/dashboard',
            default => null,
        } : null;

        return Inertia::render('public/Success', [
            'reference_number' => $formInput->reference_number,
            'formInput' => $formInput,
            'dashboardUrl' => $dashboardUrl,
        ]);
    }

    /**
    * Stream the PDF receipt in the browser window.
    */
    public function printReceipt(string $referenceNumber)
    {
        $formInput = FormInput::query()->where('reference_number', $referenceNumber)->firstOrFail();

        // dd($formInput);
    
        return $this->receiptPDFService
            ->orderOfPaymentPrint($formInput)
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
