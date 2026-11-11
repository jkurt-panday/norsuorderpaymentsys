import React from 'react';
import { update, index } from '@/actions/App/Http/Controllers/UACSController';
import ResourceForm from '@/components/ResourceForm';
import type { FieldDef } from '@/components/ResourceForm';

interface UacsRecord {
    id: number;
    norsu_object_code: string | null;
    norsu_sub_object_code: string | null;
    uacs_object_code: string | null;
    uacs_sub_object_code: string | null;
    rca_code: string | null;
    account_title: string | null;
}

interface EditUacsProps {
    uacs: UacsRecord;
}

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

export default function EditUacs({ uacs }: EditUacsProps) {
    return (
        <ResourceForm
            title="Edit UACS"
            backHref={index()}
            fields={fields}
            initialData={{
                norsu_object_code: uacs.norsu_object_code ?? '',
                norsu_sub_object_code: uacs.norsu_sub_object_code ?? '',
                uacs_object_code: uacs.uacs_object_code ?? '',
                uacs_sub_object_code: uacs.uacs_sub_object_code ?? '',
                rca_code: uacs.rca_code ?? '',
                account_title: uacs.account_title ?? '',
            }}
            submitUrl={update(uacs.id).url}
            method="put"
        />
    );
}

