<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentDetailOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');
        $optionId = $this->route('payment_detail_option') ? $this->route('payment_detail_option')->id : null;

        return [
            'payment_desc' => [
                'required',
                'string',
                'max:255',
                $isUpdate ? "unique:payment_detail_options,payment_desc,{$optionId}" : 'unique:payment_detail_options,payment_desc',
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