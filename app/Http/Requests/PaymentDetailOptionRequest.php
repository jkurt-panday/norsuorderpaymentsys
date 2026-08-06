<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentDetailOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Route parameter is bound as 'paymentOption' (see web.php:
        // ->parameters(['payment-options' => 'paymentOption'])) — not 'payment_detail_option'.
        $optionId = $this->route('paymentOption')?->id;

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
