<?php

namespace App\Http\Requests;

use App\Models\UACS;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UacsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $uacs = $this->route('uacs');
        $uacsId = $uacs instanceof UACS
            ? $uacs->getKey()
            : (is_numeric($uacs) ? (int) $uacs : null);

        return [
            'classification' => ['nullable', 'string', 'max:255'],
            'sub_class' => ['nullable', 'string', 'max:255'],
            'group' => ['nullable', 'string', 'max:255'],
            'object_code' => ['nullable', 'string', 'max:255'],
            'sub_object_code' => ['nullable', 'string', 'max:255'],
            'uacs' => [
                'required',
                'string',
                'max:20',
                Rule::unique('uacs', 'uacs')->ignore($uacsId),
            ],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'uacs.required' => 'The UACS code is required.',
            'uacs.unique' => 'This UACS code already exists.',
            'status.required' => 'The status is required.',
            'status.in' => 'Status must be Active or Inactive.',
        ];
    }
}
