import React from 'react';
import { update, index } from '@/actions/App/Http/Controllers/AuthorizedOfficialController';
import ResourceForm from '@/components/ResourceForm';
import type { FieldDef } from '@/components/ResourceForm';

const fields: FieldDef[] = [
    {
        name: 'name',
        label: 'Full Name',
        required: true,
        placeholder: 'e.g. MAURICE ANAVER B. DORDADO',
        helpText: 'Full name of the authorized official, exactly as it should appear on the signature line.',
    },
    {
        name: 'position',
        label: 'Position',
        required: true,
        placeholder: 'e.g. Head of Accounting/Division/Unit',
    },
    {
        name: 'course',
        label: 'Course / Credentials',
        placeholder: 'e.g. CPA',
        helpText: 'Optional. Displayed after the name, e.g. CPA, MBA.',
    },
];

interface AuthorizedOfficialRecord {
    id: number;
    name: string;
    position: string;
    course: string | null;
}

interface Props {
    authorizedOfficial: AuthorizedOfficialRecord;
}

export default function EditAuthorizedOfficial({
    authorizedOfficial,
}: Props) {
    return (
        <ResourceForm
            title="Edit Authorized Official"
            backHref={index()}
            fields={fields}
            initialData={{
                name: authorizedOfficial.name,
                position: authorizedOfficial.position,
                course: authorizedOfficial.course ?? '',
            }}
            submitUrl={update(authorizedOfficial.id).url}
            method="put"
        />
    );
}
