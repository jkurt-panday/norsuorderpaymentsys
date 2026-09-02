<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StaffOrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'or_no' => ['required', 'string', 'max:50', 'regex:/^[0-9\-\.\/\s]+$/'],
            'or_date' => 'required|date|date_format:Y-m-d|before_or_equal:today',
        ];
    }

    public function messages(): array
    {
        return [
            'or_no.required' => 'OR number is required.',
            'or_no.regex' => 'OR number can only contain numbers, dashes, slashes, dots and spaces.',
            'or_date.required' => 'OR date is required.',
            'or_date.before_or_equal' => 'OR date cannot be in the future.',
        ];
    }
}
