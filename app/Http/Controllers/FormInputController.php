<?php

namespace App\Http\Controllers;

use App\Models\FormInput;
use App\Models\StaffInput;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class FormInputController extends Controller
{
    /**
     * Store a new form submission (Client side)
     */
    public function store(Request $request)
    {
        // Validate client form inputs only
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'contact_num' => 'required|string|max:13',
            'firstname_or_office' => 'required|string',
            'middlename_or_project' => 'nullable|string',
            'lastname_or_agency' => 'required|string',
            'office_or_college' => 'required|string',
            'position_or_designation' => 'required|string',
            'address' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'request_type' => 'required|in:New Request,Re-issue Request',
            'membership_id' => 'required|exists:membership,id',
            'payment_details_id' => 'required|exists:payment_details_options,id',
            'supporting_documents.*' => 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Handle file uploads
        $documentUrls = [];
        if ($request->hasFile('supporting_documents')) {
            foreach ($request->file('supporting_documents') as $file) {
                $filename = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
                $path = $file->storeAs('supporting_documents', $filename, 'public');
                $documentUrls[] = Storage::url($path);
            }
        }

        // Create Form Input (Client data only)
        $formInput = FormInput::create([
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
            'payment_details_id' => $request->payment_details_id,
            'supporting_documents' => $documentUrls,
        ]);

        return response()->json([
            'message' => 'Form submitted successfully',
            'data' => $formInput,
            'reference_id' => $formInput->id, // For staff to reference later
        ], 201);
    }

    /**
     * Get a specific form submission
     */
    public function show($id)
    {
        $formInput = FormInput::with(['membership', 'paymentDetails', 'staffInput'])->findOrFail($id);

        return response()->json($formInput);
    }

    /**
     * Get all form submissions with status
     */
    public function index()
    {
        $formInputs = FormInput::with(['membership', 'paymentDetails', 'staffInput'])->get();

        return response()->json($formInputs);
    }

    /**
     * Update form submission (Client side - limited fields)
     */
    public function update(Request $request, $id)
    {
        $formInput = FormInput::findOrFail($id);

        // Check if staff already processed this
        if ($formInput->staffInput) {
            return response()->json([
                'message' => 'Cannot update form. Staff has already processed this request.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'sometimes|email',
            'contact_num' => 'sometimes|string|max:13',
            'firstname_or_office' => 'sometimes|string',
            'middlename_or_project' => 'nullable|string',
            'lastname_or_agency' => 'sometimes|string',
            'office_or_college' => 'sometimes|string',
             'position_or_designation' => 'sometimes|string',
            'address' => 'sometimes|string',
            'amount' => 'sometimes|numeric|min:0',
            'request_type' => 'sometimes|in:New Request,Re-issue Request',
            'supporting_documents.*' => 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Handle new file uploads
        if ($request->hasFile('supporting_documents')) {
            // Delete old files
            if ($formInput->supporting_documents) {
                foreach ($formInput->supporting_documents as $oldUrl) {
                    $path = str_replace('/storage/', '', $oldUrl);
                    if (Storage::disk('public')->exists($path)) {
                        Storage::disk('public')->delete($path);
                    }
                }
            }

            $documentUrls = [];
            foreach ($request->file('supporting_documents') as $file) {
                $filename = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
                $path = $file->storeAs('supporting_documents', $filename, 'public');
                $documentUrls[] = Storage::url($path);
            }
            $request->merge(['supporting_documents' => $documentUrls]);
        }

        $formInput->update($request->all());

        return response()->json([
            'message' => 'Form updated successfully',
            'data' => $formInput,
        ]);
    }

    /**
     * Delete form submission (Client side)
     */
    // public function destroy($id)
    // {
    //     $formInput = FormInput::findOrFail($id);

    //     // Check if staff already processed this
    //     if ($formInput->staffInput) {
    //         return response()->json([
    //             'message' => 'Cannot delete. Staff has already processed this request.'
    //         ], 403);
    //     }

    //     // Delete associated files
    //     if ($formInput->supporting_documents) {
    //         foreach ($formInput->supporting_documents as $documentUrl) {
    //             $path = str_replace('/storage/', '', $documentUrl);
    //             if (Storage::disk('public')->exists($path)) {
    //                 Storage::disk('public')->delete($path);
    //             }
    //         }
    //     }

    //     $formInput->delete();

    //     return response()->json(['message' => 'Form deleted successfully']);
    // }
}
