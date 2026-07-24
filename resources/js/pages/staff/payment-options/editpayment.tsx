import React from 'react';
import { update, index } from '@/actions/App/Http/Controllers/PaymentDetailOptionController';
import ResourceForm, { type FieldDef } from '@/components/ResourceForm';

interface PaymentOption {
    id: number;
    payment_desc: string;
}

interface EditPaymentOptionProps {
    paymentOption: PaymentOption;
}

const fields: FieldDef[] = [
    {
        name: 'payment_desc',
        label: 'Payment Description',
        required: true,
        colSpan: 'full',
    },
];

export default function EditPaymentOption({ paymentOption }: EditPaymentOptionProps) {
    return (
        <ResourceForm
            title="Edit Payment Details"
            backHref={index()}
            fields={fields}
            initialData={{ payment_desc: paymentOption.payment_desc }}
            submitUrl={update(paymentOption.id).url}
            method="put"
        />
    );
}