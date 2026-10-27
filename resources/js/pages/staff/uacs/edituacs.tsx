import React from 'react';
import { update, index } from '@/actions/App/Http/Controllers/UACSController';
import ResourceForm from '@/components/ResourceForm';
import type { FieldDef } from '@/components/ResourceForm';

interface UacsRecord {
    id: number;
    classification: string | null;
    sub_class: string | null;
    group: string | null;
    object_code: string | null;
    sub_object_code: string | null;
    uacs: string | null;
    status: string | null;
}

interface EditUacsProps {
    uacs: UacsRecord;
}

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

export default function EditUacs({ uacs }: EditUacsProps) {
    return (
        <ResourceForm
            title="Edit UACS"
            backHref={index()}
            fields={fields}
            initialData={{
                classification: uacs.classification ?? '',
                sub_class: uacs.sub_class ?? '',
                group: uacs.group ?? '',
                object_code: uacs.object_code ?? '',
                sub_object_code: uacs.sub_object_code ?? '',
                uacs: uacs.uacs ?? '',
                status: uacs.status ?? 'Active',
            }}
            submitUrl={update(uacs.id).url}
            method="put"
        />
    );
}
