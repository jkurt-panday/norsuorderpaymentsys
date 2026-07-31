import React, { useEffect } from 'react';
import { CreditCard } from 'lucide-react';
import { toast } from 'sonner';
import {
    create,
    edit,
    destroy,
} from '@/actions/App/Http/Controllers/PaymentDetailOptionController';
import ResourceTable, {
    type PaginatedData,
    type ColumnDef,
} from '@/components/ResourceTable';

// ============ TYPE DEFINITIONS ============
interface PaymentOption {
    id: number;
    payment_desc: string;
    created_at: string;
    display_number: number;
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
export default function PaymentOptionsIndex({
    paymentOptions,
    flash,
}: PaymentOptionsIndexProps) {
    // Surface flash messages from the backend (e.g. delete blocked because the
    // payment option is still in use). Without this, a rejected delete silently
    // redirects back with no visible feedback to the user.
    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    // ============ COLUMNS ============
    // `sortable` uses the exact backend column name (must match
    // PaymentDetailOptionController's $sortableColumns allowlist) so
    // clicking a header actually submits ?sort=...&direction=... instead of
    // just showing a decorative arrow.
    const columns: ColumnDef<PaymentOption>[] = [
        {
            header: 'ID',
            render: (row) => row.display_number,
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
            resourceKey="paymentOptions"
            pollInterval={15000}
            editHref={(row) => edit(row.id)}
            deleteUrl={(id) => destroy(id).url}
            emptyIcon={CreditCard}
            emptyMessage="No payment details options found"
            deleteConfirmMessage="Are you sure you want to delete this payment option?"
            sortOptions={[
                { label: 'Newest', sort: 'created_at', direction: 'desc' },
                { label: 'Oldest', sort: 'created_at', direction: 'asc' },
                { label: 'A–Z', sort: 'payment_desc', direction: 'asc' },
                { label: 'Z–A', sort: 'payment_desc', direction: 'desc' },
            ]}
        />
    );
}
