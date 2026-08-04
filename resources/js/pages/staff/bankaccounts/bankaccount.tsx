import React, { useEffect } from 'react';
import { Landmark } from 'lucide-react';
import { toast } from 'sonner';
import {
    create,
    edit,
    destroy,
} from '@/actions/App/Http/Controllers/BankAccountInfoController';
import ResourceTable, {
    type PaginatedData,
    type ColumnDef,
} from '@/components/ResourceTable';
import { Badge } from '@/components/ui/badge';

// ============ TYPE DEFINITIONS ============
// created_at added here even though it's not rendered as a visible column —
// it's still present on the row data from Supabase/Eloquent by default (as
// long as it isn't in the model's $hidden array), and it's needed so the
// "Newest"/"Oldest" sort options below have a real field to reference.
interface BankAccount {
    id: number;
    account_name: string;
    bank_name: string;
    account_num: string;
    created_at: string;
    fund_cluster: string;
    display_number: number;
}

interface FlashProps {
    success?: string;
    error?: string;
}

interface BankAccountsIndexProps {
    bankAccounts: PaginatedData<BankAccount>;
    flash?: FlashProps;
}

// ============ COMPONENT ============
export default function BankAccountsIndex({
    bankAccounts,
    flash,
}: BankAccountsIndexProps) {
    // Surface flash messages from the backend (e.g. delete blocked because the
    // bank account is still in use). Without this, a rejected delete silently
    // redirects back with no visible feedback to the user.
    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    const columns: ColumnDef<BankAccount>[] = [
        {
            header: 'ID',
            render: (row) => row.display_number,
        },
        {
            header: 'Account Name',
            render: (row) => row.account_name,
        },
        {
            header: 'Fund Cluster',
            render: (row) =>
                row.fund_cluster?.trim() ? row.fund_cluster : 'N/A',
        },
        {
            header: 'Bank Name',
            render: (row) => <Badge variant="secondary">{row.bank_name}</Badge>,
        },
        {
            header: 'Account Number',
            render: (row) => (
                <code className="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-700">
                    {row.account_num}
                </code>
            ),
        },
    ];

    return (
        <ResourceTable<BankAccount>
            title="Bank Account Information"
            addLabel="Add Bank Account"
            addHref={create()}
            columns={columns}
            resource={bankAccounts}
            resourceKey="bankAccounts"
            pollInterval={15000}
            editHref={(row) => edit(row.id)}
            deleteUrl={(id) => destroy(id).url}
            emptyIcon={Landmark}
            emptyMessage="No bank accounts found"
            deleteConfirmMessage="Are you sure you want to delete this bank account?"
            sortOptions={[
                { label: 'Newest', sort: 'created_at', direction: 'desc' },
                { label: 'Oldest', sort: 'created_at', direction: 'asc' },
                { label: 'A–Z', sort: 'account_name', direction: 'asc' },
                { label: 'Z–A', sort: 'account_name', direction: 'desc' },
            ]}
        />
    );
}
