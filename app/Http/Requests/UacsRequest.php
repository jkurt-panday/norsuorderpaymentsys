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
            'norsu_object_code' => [
                'required',
                'string',
                'size:12',
                'regex:/^\d{12}$/',
                Rule::unique('uacs', 'norsu_object_code')
                    ->where('norsu_sub_object_code', $this->input('norsu_sub_object_code'))
                    ->ignore($uacsId),
            ],
            'norsu_sub_object_code' => ['required', 'string', 'size:2', 'regex:/^\d{2}$/'],
            'uacs_object_code' => ['required', 'string', 'size:10', 'regex:/^\d{10}$/'],
            'uacs_sub_object_code' => ['required', 'string', 'size:2', 'regex:/^\d{2}$/'],
            'rca_code' => ['required', 'string', 'size:8', 'regex:/^\d{8}$/'],
            'account_title' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'norsu_object_code.required' => 'The NORSU object code is required.',
            'norsu_object_code.size' => 'The NORSU object code must be exactly 12 digits.',
            'norsu_object_code.regex' => 'The NORSU object code must contain exactly 12 digits.',
            'norsu_object_code.unique' => 'This NORSU object/sub-object code combination already exists.',
            'norsu_sub_object_code.required' => 'The NORSU sub-object code is required.',
            'norsu_sub_object_code.size' => 'The NORSU sub-object code must be exactly 2 digits.',
            'norsu_sub_object_code.regex' => 'The NORSU sub-object code must contain exactly 2 digits.',
            'uacs_object_code.required' => 'The UACS object code is required.',
            'uacs_object_code.size' => 'The UACS object code must be exactly 10 digits.',
            'uacs_object_code.regex' => 'The UACS object code must contain exactly 10 digits.',
            'uacs_sub_object_code.required' => 'The UACS sub-object code is required.',
            'uacs_sub_object_code.size' => 'The UACS sub-object code must be exactly 2 digits.',
            'uacs_sub_object_code.regex' => 'The UACS sub-object code must contain exactly 2 digits.',
            'rca_code.required' => 'The RCA code is required.',
            'rca_code.size' => 'The RCA code must be exactly 8 digits.',
            'rca_code.regex' => 'The RCA code must contain exactly 8 digits.',
            'account_title.required' => 'The account title is required.',
        ];
    }
}
