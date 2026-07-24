import React from 'react';
import { update, index } from '@/actions/App/Http/Controllers/MembershipController';
import ResourceForm, { type FieldDef } from '@/components/ResourceForm';

interface Membership {
    id: number;
    member_code: string;
    member_desc: string;
}

interface EditMembershipProps {
    membership: Membership;
}

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

export default function EditMembership({ membership }: EditMembershipProps) {
    return (
        <ResourceForm
            title="Edit Membership"
            backHref={index()}
            fields={fields}
            initialData={{
                member_code: membership.member_code,
                member_desc: membership.member_desc,
            }}
            submitUrl={update(membership.id).url}
            method="put"
        />
    );
}