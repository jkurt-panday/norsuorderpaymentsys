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
<<<<<<< HEAD
        maxLength: 12,
        numericOnly: true,
=======
>>>>>>> 8614749be41420dd4ec85df37ef853d7b9aa213a
    },
    {
        name: 'norsu_sub_object_code',
        label: 'NORSU Sub-Object Code',
        required: true,
<<<<<<< HEAD
        placeholder: 'Enter 2-digit NORSU sub-object code',
        maxLength: 2,
        numericOnly: true,
=======
        placeholder: 'Enter 2-digit sub-object code',
>>>>>>> 8614749be41420dd4ec85df37ef853d7b9aa213a
    },
    {
        name: 'uacs_object_code',
        label: 'UACS Object Code',
        required: true,
        placeholder: 'Enter 10-digit UACS object code',
<<<<<<< HEAD
        maxLength: 10,
        numericOnly: true,
=======
>>>>>>> 8614749be41420dd4ec85df37ef853d7b9aa213a
    },
    {
        name: 'uacs_sub_object_code',
        label: 'UACS Sub-Object Code',
        required: true,
<<<<<<< HEAD
        placeholder: 'Enter 2-digit UACS sub-object code',
        maxLength: 2,
        numericOnly: true,
=======
        placeholder: 'Enter 2-digit sub-object code',
>>>>>>> 8614749be41420dd4ec85df37ef853d7b9aa213a
    },
    {
        name: 'rca_code',
        label: 'RCA Code',
        required: true,
        placeholder: 'Enter 8-digit RCA code',
<<<<<<< HEAD
        maxLength: 8,
        numericOnly: true,
=======
>>>>>>> 8614749be41420dd4ec85df37ef853d7b9aa213a
    },
    {
        name: 'account_title',
        label: 'Account Title',
        required: true,
        placeholder: 'Enter account title',
<<<<<<< HEAD
        maxLength: 500,
        colSpan: 'full',
=======
>>>>>>> 8614749be41420dd4ec85df37ef853d7b9aa213a
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

