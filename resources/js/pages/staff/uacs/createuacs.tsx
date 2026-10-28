import React from 'react';
import { store, index } from '@/actions/App/Http/Controllers/UACSController';
import ResourceForm from '@/components/ResourceForm';
import type { FieldDef } from '@/components/ResourceForm';

const fields: FieldDef[] = [
    {
        name: 'classification',
        label: 'Classification',
        placeholder: 'Enter the classification',
    },
    {
        name: 'sub_class',
        label: 'Sub-Class',
        placeholder: 'Enter the sub-class',
    },
    {
        name: 'group',
        label: 'Group',
        placeholder: 'Enter the group',
    },
    {
        name: 'object_code',
        label: 'Object Code',
        placeholder: 'Enter the object code',
    },
    {
        name: 'sub_object_code',
        label: 'Sub-Object Code',
        placeholder: 'Enter the sub-object code',
    },
    {
        name: 'uacs',
        label: 'UACS',
        required: true,
        placeholder: 'Enter the UACS code',
    },
    {
        name: 'status',
        label: 'Status',
        required: true,
        placeholder: 'Select status',
        options: [
            { label: 'Active', value: 'Active' },
            { label: 'Inactive', value: 'Inactive' },
        ],
    },
];

export default function CreateUacs() {
    return (
        <ResourceForm
            title="Add UACS"
            backHref={index()}
            fields={fields}
            initialData={{
                classification: '',
                sub_class: '',
                group: '',
                object_code: '',
                sub_object_code: '',
                uacs: '',
                status: 'Active',
            }}
            submitUrl={store().url}
            method="post"
        />
    );
}
