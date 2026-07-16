<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BankAccountInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');
        $accountId = $this->route('bank_account_info') ? $this->route('bank_account_info')->id : null;

        return [
            'account_name' => ['required', 'string', 'max:255'],
            'bank_name' => ['required', 'string', 'max:100'],
            'account_num' => [
                'required',
                'string',
                'max:50',
                $isUpdate ? "unique:bankaccount_infos,account_num,{$accountId}" : 'unique:bankaccount_infos,account_num',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'account_name.required' => 'Account name is required.',
            'bank_name.required' => 'Bank name is required.',
            'account_num.required' => 'Account number is required.',
            'account_num.unique' => 'This account number already exists.',
        ];
    }
}