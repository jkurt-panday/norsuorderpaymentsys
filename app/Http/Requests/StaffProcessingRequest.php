<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffProcessingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Add your authorization logic here
        // For example: return auth()->check() && auth()->user()->isStaff();
        return true;
    }

    public function rules(): array
    {
        return [
            'form_input_id' => ['required', 'exists:form_inputs,id'],
            'fundcluster_id' => ['required', 'exists:bankaccount_infos,id'],
            'ref_document_id' => ['nullable', 'exists:supporting_documents,id'],
            'ref_date' => ['required', 'date', 'date_format:Y-m-d', 'before_or_equal:today'],
            'uacs_id' => ['required', 'exists:uacs,id'],
            'status' => ['required', Rule::in(['pending', 'approved', 'cancelled'])],
        ];
    }

    public function messages(): array
    {
        return [
            'form_input_id.required' => 'Form input reference is required.',
            'form_input_id.exists' => 'The referenced form input does not exist.',
            'fundcluster_id.required' => 'Bank account selection is required.',
            'fundcluster_id.exists' => 'Selected bank account is invalid.',
            'ref_date.required' => 'Reference date is required.',
            'ref_date.before_or_equal' => 'Reference date cannot be in the future.',
            'uacs_id.required' => 'UACS selection is required.',
            'uacs_id.exists' => 'Selected UACS is invalid.',
            'status.required' => 'Status is required.',
            'status.in' => 'Invalid status value.',
        ];
    }
}