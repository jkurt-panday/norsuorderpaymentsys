import React, { useEffect } from 'react';
import { CreditCard } from 'lucide-react';
import { toast } from 'sonner';
import { create, edit, destroy } from '@/actions/App/Http/Controllers/PaymentDetailOptionController';
import ResourceTable, { type PaginatedData, type ColumnDef } from '@/components/ResourceTable';

// ============ TYPE DEFINITIONS ============
interface PaymentOption {
    id: number;
    payment_desc: string;
    created_at: string;
}

interface FlashProps {
    success?: string;
    error?: string;
}

interface PaymentOptionsIndexProps {
    paymentOptions: PaginatedData<PaymentOption>;
    flash?: FlashProps;
}

// ============ HELPERS ============
const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
    });
};

// ============ COMPONENT ============
export default function PaymentOptionsIndex({ paymentOptions, flash }: PaymentOptionsIndexProps) {
    // Surface flash messages from the backend (e.g. delete blocked because the
    // payment option is still in use). Without this, a rejected delete silently
    // redirects back with no visible feedback to the user.
    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    // ============ COLUMNS ============
    // Defined inside the component so it can close over `paymentOptions.from`
    // for correct sequential numbering across pagination pages.
    const columns: ColumnDef<PaymentOption>[] = [
        {
            header: 'ID',
            render: (row, index) => (paymentOptions.from ?? 1) + index,
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