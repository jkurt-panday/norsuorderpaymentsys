import React from 'react';
import { store, index } from '@/actions/App/Http/Controllers/UacsController';
import ResourceForm from '@/components/ResourceForm';

// ============ COMPONENT ============
export default function CreateUacs() {
    return (
        <ResourceForm
            title="Add UACS"
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
                object_code: '',
                account_title: '',
            }}
            submitUrl={store().url}
            method="post"
        />
    );
}