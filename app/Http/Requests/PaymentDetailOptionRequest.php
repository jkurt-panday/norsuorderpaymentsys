<?php

namespace App\Http\Requests;

use App\Models\PaymentDetailOption;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentDetailOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        // Route parameter is bound as 'paymentOption' (see web.php:
        // ->parameters(['payment-options' => 'paymentOption'])) — not 'payment_detail_option'.
        $option = $this->route('paymentOption');
        $optionId = $option instanceof PaymentDetailOption
            ? $option->getKey()
            : (is_numeric($option) ? (int) $option : null);

        return [
            'payment_desc' => [
                'required',
                'string',
                'max:255',
                Rule::unique('payment_detail_options', 'payment_desc')->ignore($optionId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_desc.required' => 'Payment description is required.',
            'payment_desc.unique' => 'This payment description already exists.',
        ];
    }
}
