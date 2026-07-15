<?php

namespace App\Http\Controllers;

use App\Models\FormInput;
use App\Models\StaffInput;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StaffInputController extends Controller
{
    /**
     * Add staff input to an existing form submission
     */
    public function store(Request $request)
    {
        // Validate staff inputs only
        $validator = Validator::make($request->all(), [
            'form_input_id' => 'required|exists:form_input,id',
            'fundcluster_id' => 'required|exists:bankaccount_info,id',
            'uacs_id' => 'required|exists:uacs,id',
            'ref_date_1' => 'nullable|date',
            'ref_date_2' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if form exists and doesn't have staff input yet
        $formInput = FormInput::find($request->form_input_id);
        
        if (!$formInput) {
            return response()->json(['message' => 'Form not found'], 404);
        }

        if ($formInput->staffInput) {
            return response()->json([
                'message' => 'Staff input already exists for this form. Use update endpoint.'
            ], 409);
        }

        // Get document URLs from the form's supporting documents
        $documents = $formInput->supporting_documents ?? [];
        
        // Create Staff Input
        $staffInput = StaffInput::create([
            'form_input_id' => $request->form_input_id,
            'fundcluster_id' => $request->fundcluster_id,
            'ref_doc_1' => $documents[0] ?? null,
            'ref_date_1' => $request->ref_date_1,
            'ref_doc_2' => $documents[1] ?? null,
            'ref_date_2' => $request->ref_date_2,
            'uacs_id' => $request->uacs_id,
        ]);

        return response()->json([
            'message' => 'Staff input added successfully',
            'data' => $staffInput
        ], 201);
    }

    /**
     * Update staff input
     */
    public function update(Request $request, $id)
    {
        $staffInput = StaffInput::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'fundcluster_id' => 'sometimes|exists:bankaccount_info,id',
            'uacs_id' => 'sometimes|exists:uacs,id',
            'ref_date_1' => 'nullable|date',
            'ref_date_2' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $staffInput->update($request->all());

        return response()->json([
            'message' => 'Staff input updated successfully',
            'data' => $staffInput
        ]);
    }

    /**
     * Get staff input for a specific form
     */
    public function show($formInputId)
    {
        $staffInput = StaffInput::with(['fundCluster', 'uacs', 'formInput'])
            ->where('form_input_id', $formInputId)
            ->firstOrFail();
            
        return response()->json($staffInput);
    }

    /**
     * Get all staff inputs
     */
    public function index()
    {
        $staffInputs = StaffInput::with(['fundCluster', 'uacs', 'formInput'])->get();
        return response()->json($staffInputs);
    }

    /**
     * Get pending forms (no staff input yet)
     */
    public function pendingForms()
    {
        $pendingForms = FormInput::doesntHave('staffInput')
            ->with(['membership', 'paymentDetails'])
            ->get();
            
        return response()->json([
            'pending_count' => $pendingForms->count(),
            'data' => $pendingForms
        ]);
    }

    /**
     * Get processed forms (with staff input)
     */
    public function processedForms()
    {
        $processedForms = FormInput::has('staffInput')
            ->with(['membership', 'paymentDetails', 'staffInput'])
            ->get();
            
        return response()->json([
            'processed_count' => $processedForms->count(),
            'data' => $processedForms
        ]);
    }

    /**
     * Delete staff input
     */
    public function destroy($id)
    {
        $staffInput = StaffInput::findOrFail($id);
        $staffInput->delete();

        return response()->json([
            'message' => 'Staff input deleted successfully'
        ]);
    }
}