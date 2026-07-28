<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BankAccountInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Route parameter is bound as 'bankAccount' (see web.php:
        // ->parameters(['bank-accounts' => 'bankAccount'])) — not 'bank_account_info'.
        $accountId = $this->route('bankAccount')?->id;

        return [
            'account_name' => ['required', 'string', 'max:255'],
            'bank_name' => ['required', 'string', 'max:100'],
            'account_num' => [
                'required',
                'string',
                'max:50',
                Rule::unique('bankaccount_infos', 'account_num')->ignore($accountId),
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