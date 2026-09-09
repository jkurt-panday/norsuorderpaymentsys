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
            'account_title' => ['required', 'string', 'max:255'],
            'object_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('uacs', 'object_code')->ignore($uacsId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'account_title.required' => 'Account title is required.',
            'object_code.required' => 'Object code is required.',
            'object_code.unique' => 'This object code already exists.',
        ];
    }
}
