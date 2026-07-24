<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Implement authorization as needed
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');
        $membershipId = $this->route('membership') ? $this->route('membership')->id : null;

        return [
            'member_code' => [
                'required',
                'string',
                'max:50',
                $isUpdate ? "unique:memberships,member_code,{$membershipId}" : 'unique:memberships,member_code',
            ],
            'member_desc' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'member_code.required' => 'Member code is required.',
            'member_code.unique' => 'This member code already exists.',
            'member_desc.required' => 'Member description is required.',
        ];
    }
}