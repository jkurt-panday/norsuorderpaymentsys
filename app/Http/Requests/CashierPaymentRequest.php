<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class CashierPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Cashier->value;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'or_no' => 'required|string|max:50',
            'or_date' => 'required|date_format:Y-m-d|before_or_equal:today',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'or_no.required' => 'OR number is required.',
            'or_date.required' => 'OR date is required.',
            'or_date.before_or_equal' => 'OR date cannot be in the future.',
        ];
    }
}
