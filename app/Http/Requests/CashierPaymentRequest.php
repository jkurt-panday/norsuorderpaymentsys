<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CashierPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Cashier->value;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:processed,paid,cancelled'],
            'or_no' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[0-9\-\.\/\s]+$/',
                'required_if:status,paid',
            ],
            'or_date' => [
                'nullable',
                'date',
                'date_format:Y-m-d',
                'required_if:status,paid',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'status.required' => 'Status is required.',
            'status.in' => 'Selected status is invalid.',
            'or_no.required_if' => 'OR number is required when status is paid.',
            'or_no.regex' => 'OR number can only contain numbers, dashes, slashes, dots and spaces.',
            'or_date.required_if' => 'OR date is required when status is paid.',
        ];
    }
}
