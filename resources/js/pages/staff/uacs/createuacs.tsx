import React from 'react';
import { store, index } from '@/actions/App/Http/Controllers/UACSController';
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
                    placeholder: 'Enter the object code (e.g. 10101010)',
                },
                {
                    name: 'account_title',
                    label: 'Account Title',
                    required: true,
                    placeholder:
                        'Enter the account title (e.g. Salaries and Wages)',
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
