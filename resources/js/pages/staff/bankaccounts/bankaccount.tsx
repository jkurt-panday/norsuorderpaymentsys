import React from 'react';
import { Landmark } from 'lucide-react';
import { create, edit, destroy } from '@/actions/App/Http/Controllers/BankAccountInfoController';
import ResourceTable, { type PaginatedData, type ColumnDef } from '@/components/ResourceTable';
import { Badge } from '@/components/ui/badge';

// ============ TYPE DEFINITIONS ============
interface BankAccount {
    id: number;
    account_name: string;
    bank_name: string;
    account_num: string;
}

interface BankAccountsIndexProps {
    bankAccounts: PaginatedData<BankAccount>;
}

// ============ COLUMNS ============
const columns: ColumnDef<BankAccount>[] = [
    {
        header: 'ID',
        render: (row) => row.id,
    },
    {
        header: 'Account Name',
        render: (row) => row.account_name,
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

// ============ COMPONENT ============
export default function BankAccountsIndex({ bankAccounts }: BankAccountsIndexProps) {
    return (
        <ResourceTable<BankAccount>
            title="Bank Account Information"
            addLabel="Add Bank Account"
            addHref={create()}
            columns={columns}
            resource={bankAccounts}
            editHref={(row) => edit(row.id)}
            deleteUrl={(id) => destroy(id).url}
            emptyIcon={Landmark}
            emptyMessage="No bank accounts found"
            deleteConfirmMessage="Are you sure you want to delete this bank account?"
        />
    );
}