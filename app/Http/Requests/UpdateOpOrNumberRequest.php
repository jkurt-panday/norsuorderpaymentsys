<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\StaffInput;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Lets staff and admin correct an OR number that a cashier already placed.
 *
 * This is deliberately NOT a way to place the first OR number — placing one
 * stays a cashier-only action on `cashier.requests.payment.update`, so the
 * cashier remains the only person who can issue a receipt. Staff/admin may only
 * fix a typo in an OR number that already exists, which covers the case where
 * the cashier is unavailable.
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
     * Staff/admin may only correct an OR number that the cashier already
     * placed. A blank `or_no` means no receipt was issued yet, so there is
     * nothing to correct — that stays a cashier-only action.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $staffInput = $this->route('staffInput');

            if (! $staffInput instanceof StaffInput) {
                return;
            }

            if (blank($staffInput->or_no)) {
                $validator->errors()->add(
                    'or_no',
                    'No OR number has been placed yet, so there is nothing to correct. Only a cashier can issue an OR number.',
                );
            }
        });
    }
}