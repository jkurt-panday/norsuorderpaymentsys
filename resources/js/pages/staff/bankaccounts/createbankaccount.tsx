import React from 'react';
import {
    store,
    index,
} from '@/actions/App/Http/Controllers/BankAccountInfoController';
import ResourceForm, { type FieldDef } from '@/components/ResourceForm';

const fields: FieldDef[] = [
    {
        name: 'account_name',
        label: 'Account Name',
        required: true,
        colSpan: 'full',
    },
    {
        name: 'fund_cluster',
        label: 'Fund Cluster',
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
];

export default function CreateBankAccount() {
    return (
        <ResourceForm
            title="Add Bank Account"
            backHref={index()}
            fields={fields}
            initialData={{
                account_name: '',
                fund_cluster: '',
                bank_name: '',
                account_num: '',
            }}
            submitUrl={store().url}
            method="post"
        />
    );
}
