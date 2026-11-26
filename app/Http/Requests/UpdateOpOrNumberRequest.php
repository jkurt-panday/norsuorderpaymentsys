<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\FormInput;
use App\Models\StaffInput;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Lets staff and admin correct an OR number that a cashier already placed,
 * or issue the first OR number in emergency situations.
 */
class UpdateOpOrNumberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(
            $this->user()?->role,
            [UserRole::Staff->value, UserRole::Admin->value],
            true,
        );
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'or_no' => [
                'required',
                'string',
                'max:50',
                'regex:/^[0-9\-\.\/\s]+$/',
            ],
            'or_date' => ['required', 'date', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'or_no.required' => 'OR number is required.',
            'or_no.regex' => 'OR number can only contain numbers, dashes, slashes, dots and spaces.',
            'or_date.required' => 'OR date is required.',
            'or_date.date_format' => 'OR date is invalid.',
        ];
    }

    /**
     * Removed validation that blocked creating first OR number.
     * Staff/admin can now issue OR in emergency when cashier is unavailable.
     */
    public function withValidator(Validator $validator): void
    {
        // No longer blocking on blank or_no - allow emergency creation
    }
}
