import React from 'react';
import { store, index } from '@/actions/App/Http/Controllers/PaymentDetailOptionController';
import ResourceForm, { type FieldDef } from '@/components/ResourceForm';

const fields: FieldDef[] = [
    {
        name: 'payment_desc',
        label: 'Payment Description',
        required: true,
        colSpan: 'full',
    },
];

export default function CreatePaymentOption() {
    return (
        <ResourceForm
            title="Add Payment Details"
            backHref={index()}
            fields={fields}
            initialData={{ payment_desc: '' }}
            submitUrl={store().url}
            method="post"
        />
    );
}