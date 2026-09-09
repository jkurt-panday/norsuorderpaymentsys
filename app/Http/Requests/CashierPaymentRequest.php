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
            'or_no' => ['required', 'string', 'max:50', 'regex:/^[0-9\-\.\/\s]+$/'],
            'or_date' => 'required|date|date_format:Y-m-d',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'or_no.required' => 'OR number is required.',
            'or_no.regex' => 'OR number can only contain numbers, dashes, slashes, dots and spaces.',
            'or_date.required' => 'OR date is required.',
        ];
    }
}
