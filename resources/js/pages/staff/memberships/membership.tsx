import React from 'react';
import { Users } from 'lucide-react';
import ResourceTable, { type PaginatedData, type ColumnDef } from '@/components/ResourceTable';
import { create, edit, destroy } from '@/actions/App/Http/Controllers/MembershipController';
// ============ TYPE DEFINITIONS ============
interface Membership {
    id: number;
    member_code: string;
    member_desc: string;
    created_at: string;
}

interface MembershipsIndexProps {
    memberships: PaginatedData<Membership>;
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
const columns: ColumnDef<Membership>[] = [
    {
        header: 'ID',
        render: (row) => row.id,
    },
    {
        header: 'Member Code',
        render: (row) => (
            <span className="inline-block rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-900">
                {row.member_code}
            </span>
        ),
    },
    {
        header: 'Description',
        render: (row) => row.member_desc,
    },
    {
        header: 'Created At',
        render: (row) => formatDate(row.created_at),
        className: 'px-4 py-3 text-slate-500',
    },
];

// ============ COMPONENT ============
export default function MembershipsIndex({ memberships }: MembershipsIndexProps) {
    return (
        <ResourceTable<Membership>
            title="Memberships"
            addLabel="Add Membership"
            addHref={create()}
            columns={columns}
            resource={memberships}
            editHref={(row) => edit(row.id)}
            deleteUrl={(id) => destroy(id).url}
            emptyIcon={Users}
            emptyMessage="No memberships found"
            deleteConfirmMessage="Are you sure you want to delete this membership?"
        />
    );
}