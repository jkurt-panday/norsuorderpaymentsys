<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UacsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');
        $uacsId = $this->route('uacs') ? $this->route('uacs')->id : null;

        return [
            'account_title' => ['required', 'string', 'max:255'],
            'object_code' => [
                'required',
                'string',
                'max:50',
                $isUpdate ? "unique:uacs,object_code,{$uacsId}" : 'unique:uacs,object_code',
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