import React from 'react';
import { update, index } from '@/actions/App/Http/Controllers/UacsController';
import ResourceForm from '@/components/ResourceForm';

// ============ TYPE DEFINITIONS ============
interface UacsRecord {
    id: number;
    object_code: string;
    account_title: string;
}

interface EditUacsProps {
    uacs: UacsRecord;
}

// ============ COMPONENT ============
export default function EditUacs({ uacs }: EditUacsProps) {
    return (
        <ResourceForm
            title="Edit UACS"
            backHref={index()}
            fields={[
                {
                    name: 'object_code',
                    label: 'Object Code',
                    required: true,
                },
                {
                    name: 'account_title',
                    label: 'Account Title',
                    required: true,
                },
            ]}
            initialData={{
                object_code: uacs.object_code,
                account_title: uacs.account_title,
            }}
            submitUrl={update(uacs.id).url}
            method="put"
        />
    );
}