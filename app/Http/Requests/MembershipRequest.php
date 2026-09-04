<?php

namespace App\Http\Requests;

use App\Models\Membership;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Implement authorization as needed
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $membership = $this->route('membership');
        $membershipId = $membership instanceof Membership
            ? $membership->getKey()
            : (is_numeric($membership) ? (int) $membership : null);

        return [
            'member_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('memberships', 'member_code')->ignore($membershipId),
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
