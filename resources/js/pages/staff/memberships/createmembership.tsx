import React from 'react';
import { store, index } from '@/actions/App/Http/Controllers/MembershipController';
import ResourceForm, { type FieldDef } from '@/components/ResourceForm';

const fields: FieldDef[] = [
    {
        name: 'member_code',
        label: 'Member Code',
        required: true,
        helpText: 'Unique identifier for this membership type',
    },
    {
        name: 'member_desc',
        label: 'Description',
        required: true,
    },
];

export default function CreateMembership() {
    return (
        <ResourceForm
            title="Add Membership"
            backHref={index()}
            fields={fields}
            initialData={{ member_code: '', member_desc: '' }}
            submitUrl={store().url}
            method="post"
        />
    );
}