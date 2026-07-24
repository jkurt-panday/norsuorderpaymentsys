import React from 'react';
import { store, index } from '@/actions/App/Http/Controllers/BankAccountInfoController';
import ResourceForm from '@/components/ResourceForm';

// ============ COMPONENT ============
export default function CreateBankAccount() {
    return (
        <ResourceForm
            title="Add Bank Account"
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
                account_name: '',
                bank_name: '',
                account_num: '',
            }}
            submitUrl={store().url}
            method="post"
        />
    );
}