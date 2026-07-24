<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicFormSubmissionRequest;
use App\Models\FormInput;
use App\Models\Membership;
use App\Models\PaymentDetailOption;
use App\Models\SupportingDocument;
use App\Services\FileUploadService;
use App\Services\ReferenceNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

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

    return Inertia::render('SubmitForm', [
        'memberships' => $memberships,
        'paymentOptions' => $paymentOptions,
    ]);
}

    public function store(Request $request)
    {
        // 1. Validate Form & Files
        $request->validate([
            'firstname_or_office'      => 'required|string|max:255',
            'middlename_or_project'    => 'nullable|string|max:255',
            'lastname_or_agency'       => 'required|string|max:255',
            'office_or_college'        => 'required|string|max:255',
            'position_or_designation'  => 'required|string|max:255',
            'contact_num'              => 'required|string|max:50',
            'email'                    => 'required|email|max:255',
            'address'                  => 'required|string',
            'request_type'             => 'required|string',
            'amount'                   => 'required|numeric|min:0',
            'membership_id'            => 'required|exists:memberships,id',
            'payment_detail_option_id' => 'required|exists:payment_detail_options,id',
            
            'documents'                => 'nullable|array',
            'documents.*'              => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        try {
            DB::beginTransaction();

            // 2. Generate Reference Number
            $referenceNumber = $this->referenceNumberService->generate();

            // 3. Create FormInput Record using your explicit mapping
            $formInput = FormInput::create([
                'reference_number'        => $referenceNumber,
                'email'                   => $request->email,
                'contact_num'             => $request->contact_num,
                'firstname_or_office'     => $request->firstname_or_office,
                'middlename_or_project'    => $request->middlename_or_project,
                'lastname_or_agency'      => $request->lastname_or_agency,
                'office_or_college'       => $request->office_or_college,
                'position_or_designation' => $request->position_or_designation,
                'address'                 => $request->address,
                'amount'                  => $request->amount,
                'request_type'            => $request->request_type,
                'membership_id'           => $request->membership_id,
                'payment_detail_option_id' => $request->payment_detail_option_id,
            ]);

            // 4. Handle Uploaded Documents
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $file) {
                    $originalName   = $file->getClientOriginalName();
                    $extension      = $file->getClientOriginalExtension();
                    $mimeType       = $file->getClientMimeType();
                    $fileSize       = $file->getSize();

                    $storedFilename = Str::uuid() . '.' . $extension;

                    $file->storeAs('supporting-documents', $storedFilename, 'public');
    
                    $fileUrl = Storage::disk('public')->url('supporting-documents/' . $storedFilename);

                    SupportingDocument::create([
                        'form_input_id'     => $formInput->id,
                        'original_filename' => $originalName,
                        'stored_filename'   => $storedFilename,
                        'file_url'          => $fileUrl,
                        'mime_type'         => $mimeType,
                        'file_extension'    => $extension,
                        'file_size'         => $fileSize,
                        'uploaded_at'       => now(),
                    ]);
                }
            }

            DB::commit();
            return response()->json([
                    'success' => true,
                    'message' => 'Order of payment submitted successfully!',
                    'reference_number' => $formInput->reference_number,
                ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Failed to submit request: ' . $e->getMessage());
        }
    }

    /**
     * Display the success page after order submission.
     */
    public function success($referenceNumber = null)
    {
        // Optionally fetch the record if you want to display submission details
        $formInput = null;
        if ($referenceNumber) {
            $formInput = FormInput::where('reference_number', $referenceNumber)->first();
        }

        return view('public.success', compact('referenceNumber', 'formInput'));
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

        return Inertia::render('staff/requestform/showrequest', compact('formInput'));
    }
}