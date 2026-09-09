<?php

namespace App\Http\Requests;

use App\Models\BankAccountInfo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BankAccountInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        // Route parameter is bound as 'bankAccount' (see web.php:
        // ->parameters(['bank-accounts' => 'bankAccount'])) — not 'bank_account_info'.
        $account = $this->route('bankAccount');
        $accountId = $account instanceof BankAccountInfo
            ? $account->getKey()
            : (is_numeric($account) ? (int) $account : null);

        return [
            'account_name' => ['required', 'string', 'max:255'],
            'bank_name' => ['required', 'string', 'max:100'],
            'account_num' => [
                'required',
                'string',
                'max:50',
                Rule::unique('bankaccount_infos', 'account_num')->ignore($accountId),
            ],
            'fund_cluster' => ['required', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'account_name.required' => 'Account name is required.',
            'bank_name.required' => 'Bank name is required.',
            'account_num.required' => 'Account number is required.',
            'account_num.unique' => 'This Account Number already exists.',
            'fund_cluster.unique' => 'This Fund Cluster already exists',
            'fund_cluster.required' => 'This Fund cluster is required',
        ];
    }
}
