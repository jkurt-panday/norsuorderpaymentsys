<?php

namespace App\Http\Controllers;

use App\Models\FormInput;
use App\Models\Membership;
use App\Models\PaymentDetailOption;
// use App\Models\SupportingDocument;
use App\Services\FileUploadService;
use App\Services\ReferenceNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Facades\Storage;
// use Illuminate\Support\Str;
use Inertia\Inertia;
use App\Http\Requests\PublicFormSubmissionRequest;

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
        $memberships = Membership::orderBy('member_desc')->get();
        $paymentOptions = PaymentDetailOption::orderBy('payment_desc')->get();

        return Inertia::render('public/SubmitForm', [
            'memberships' => $memberships,
            'paymentOptions' => $paymentOptions,
        ]);
    }

    public function store(PublicFormSubmissionRequest $request)
    {
        // validation is done with publicformsubmissionrequest service
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            // 2. Generate Reference Number
            $referenceNumber = $this->referenceNumberService->generate();

            // 3. Create FormInput Record using your explicit mapping
            $formInput = FormInput::create([
                'reference_number'              => $referenceNumber,
                'email'                         => $validated['email'],
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

            // Eager-load relations before rendering, so the Success page
            // receives formInput.membership and formInput.paymentDetailOption
            // as populated nested objects instead of undefined.
            $formInput->load(['membership', 'paymentDetailOption', 'supportingDocuments']);

            // Render the success page directly — no separate success() action
            // or route needed, since we already have everything we need here.
            return Inertia::render('public/Success', [
                'reference_number' => $formInput->reference_number,
                'formInput' => $formInput,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()->with('error', 'Failed to submit request: '.$e->getMessage());
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
            'staffInput.referenceDocument',
        ]);

        return Inertia::render('staff/requestform/showrequest', compact('formInput'));
    }
}
