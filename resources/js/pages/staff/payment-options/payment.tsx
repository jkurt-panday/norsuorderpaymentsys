import React from 'react';
import { CreditCard } from 'lucide-react';
import { create, edit, destroy } from '@/actions/App/Http/Controllers/PaymentDetailOptionController';
import ResourceTable, { type PaginatedData, type ColumnDef } from '@/components/ResourceTable';
// ============ TYPE DEFINITIONS ============
interface PaymentOption {
    id: number;
    payment_desc: string;
    created_at: string;
}

interface PaymentOptionsIndexProps {
    paymentOptions: PaginatedData<PaymentOption>;
}

// ============ HELPERS ============
const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
    });
};

// ============ COLUMNS ============
const columns: ColumnDef<PaymentOption>[] = [
    {
        header: 'ID',
        render: (row) => row.id,
    },
    {
        header: 'Payment Description',
        render: (row) => row.payment_desc,
    },
    {
        header: 'Created At',
        render: (row) => formatDate(row.created_at),
        className: 'px-4 py-3 text-slate-500',
    },
];

// ============ COMPONENT ============
export default function PaymentOptionsIndex({ paymentOptions }: PaymentOptionsIndexProps) {
    return (
        <ResourceTable<PaymentOption>
            title="Payment Detail Options"
            addLabel="Add Payment Details"
            addHref={create()}
            columns={columns}
            resource={paymentOptions}
            editHref={(row) => edit(row.id)}
            deleteUrl={(id) => destroy(id).url}
            emptyIcon={CreditCard}
            emptyMessage="No payment details options found"
            deleteConfirmMessage="Are you sure you want to delete this payment option?"
        />
    );
}