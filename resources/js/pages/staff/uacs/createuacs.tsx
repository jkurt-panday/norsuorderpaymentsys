import React from 'react';
import { store, index } from '@/actions/App/Http/Controllers/UACSController';
import ResourceForm from '@/components/ResourceForm';
import type { FieldDef } from '@/components/ResourceForm';

const fields: FieldDef[] = [
    {
        name: 'norsu_object_code',
        label: 'NORSU Object Code',
        required: true,
        placeholder: 'Enter 12-digit NORSU object code',
    },
    {
        name: 'norsu_sub_object_code',
        label: 'NORSU Sub-Object Code',
        required: true,
        placeholder: 'Enter 2-digit sub-object code',
    },
    {
        name: 'uacs_object_code',
        label: 'UACS Object Code',
        required: true,
        placeholder: 'Enter 10-digit UACS object code',
    },
    {
        name: 'uacs_sub_object_code',
        label: 'UACS Sub-Object Code',
        required: true,
        placeholder: 'Enter 2-digit sub-object code',
    },
    {
        name: 'rca_code',
        label: 'RCA Code',
        required: true,
        placeholder: 'Enter 8-digit RCA code',
    },
    {
        name: 'account_title',
        label: 'Account Title',
        required: true,
        placeholder: 'Enter account title',
    },
];

export default function CreateUacs() {
    return (
        <ResourceForm
            title="Add UACS"
            backHref={index()}
            fields={fields}
            initialData={{
                norsu_object_code: '',
                norsu_sub_object_code: '',
                uacs_object_code: '',
                uacs_sub_object_code: '',
                rca_code: '',
                account_title: '',
            }}
            submitUrl={store().url}
            method="post"
        />
    );
}
