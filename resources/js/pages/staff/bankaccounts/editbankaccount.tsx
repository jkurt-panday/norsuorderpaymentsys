import React from 'react';
import { update, index } from '@/actions/App/Http/Controllers/BankAccountInfoController';
import ResourceForm from '@/components/ResourceForm';

// ============ TYPE DEFINITIONS ============
interface BankAccount {
    id: number;
    account_name: string;
    bank_name: string;
    account_num: string;
}

interface EditBankAccountProps {
    bankAccount: BankAccount;
}

// ============ COMPONENT ============
export default function EditBankAccount({ bankAccount }: EditBankAccountProps) {
    return (
        <ResourceForm
            title="Edit Bank Account"
            backHref={index()}
            fields={[
                {
                    name: 'account_name',
                    label: 'Account Name',
                    required: true,
                },
                {
                    name: 'bank_name',
                    label: 'Bank Name',
                    required: true,
                },
                {
                    name: 'account_num',
                    label: 'Account Number',
                    required: true,
                    colSpan: 'full',
                },
            ]}
            initialData={{
                account_name: bankAccount.account_name,
                bank_name: bankAccount.bank_name,
                account_num: bankAccount.account_num,
            }}
            submitUrl={update(bankAccount.id).url}
            method="put"
        />
    );
}