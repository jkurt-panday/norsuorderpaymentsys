<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublicFormSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public form, anyone can submit
    }

    public function rules(): array
    {
        return [
            // Personal/Office Information
            'email' => ['required', 'email', 'max:255'],
            'contact_num' => ['required', 'string', 'max:11', 'regex:/^[0-9+\-\s()]+$/'],
            'firstname_or_office' => ['required', 'string', 'max:100'],
            'middlename_or_project' => ['nullable', 'string', 'max:100'],
            'lastname_or_agency' => ['required', 'string', 'max:100'],
            'office_or_college' => ['required', 'string', 'max:100'],
            'position_or_designation' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string'],
            
            // Payment Information
            'amount' => ['required', 'numeric', 'min:0.01'],
            'request_type' => ['required', Rule::in(['New Request', 'Re-issue Request'])],
            
            // Foreign Keys
            'membership_id' => ['required', 'exists:memberships,id'],
            'payment_detail_option_id' => ['required', 'exists:payment_detail_options,id'],
            
            // File Uploads
            'documents' => ['required', 'array', 'min:1', 'max:5'],
            'documents.*' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:10240', // 10MB max per file
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email address is required.',
            'email.email' => 'Please enter a valid email address.',
            'contact_num.required' => 'Contact number is required.',
            'contact_num.regex' => 'Contact number should only contain numbers, spaces, and + - ( ) characters.',
            'amount.required' => 'Amount is required.',
            'amount.min' => 'Amount must be greater than 0.',
            'documents.required' => 'Please upload at least one supporting document.',
            'documents.max' => 'You can upload a maximum of 5 documents.',
            'documents.*.mimes' => 'Only PDF, JPG, JPEG, and PNG files are allowed.',
            'documents.*.max' => 'Each file must not exceed 10MB.',
            'membership_id.exists' => 'Selected membership type is invalid.',
            'payment_detail_option_id.exists' => 'Selected payment option is invalid.',
        ];
    }

    public function attributes(): array
    {
        return [
            'email' => 'Email Address',
            'contact_num' => 'Contact Number',
            'firstname_or_office' => 'First Name / Office',
            'middlename_or_project' => 'Middle Name / Project',
            'lastname_or_agency' => 'Last Name / Agency',
            'office_or_college' => 'Office / College',
            'position_or_designation' => 'Position / Designation',
            'address' => 'Address',
            'amount' => 'Amount',
            'request_type' => 'Request Type',
            'membership_id' => 'Membership Type',
            'payment_detail_option_id' => 'Payment Detail Option',
            'documents' => 'Supporting Documents',
        ];
    }
}