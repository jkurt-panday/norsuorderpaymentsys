import { Users } from 'lucide-react';
import React, { useEffect } from 'react';
import {
    create,
    edit,
    destroy,
} from '@/actions/App/Http/Controllers/MembershipController';
import ResourceTable from '@/components/ResourceTable';
import type { PaginatedData, ColumnDef } from '@/components/ResourceTable';
import { flashToast } from '@/utils/flashToast';

// ============ TYPE DEFINITIONS ============
interface Membership {
    id: number;
    member_code: string;
    member_desc: string;
    created_at: string;
    display_number: number;
}

interface FlashProps {
    success?: string;
    error?: string;
}

interface MembershipsIndexProps {
    memberships: PaginatedData<Membership>;
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
export default function MembershipsIndex({
    memberships,
    flash,
}: MembershipsIndexProps) {
    // Surface flash messages from the backend (e.g. delete blocked because the
    // membership is still in use). Without this, a rejected delete silently
    // redirects back with no visible feedback to the user.
    useEffect(() => {
        if (flash?.success) {
            flashToast('success', flash.success);
        }

        if (flash?.error) {
            flashToast('error', flash.error);
        }
    }, [flash]);

    // ============ COLUMNS ============
    // `sortable` is set to the exact backend column name (matches
    // MembershipController's $sortableColumns allowlist) so clicking the
    // header actually submits ?sort=...&direction=... instead of just
    // showing a decorative arrow.
    //
    // ID uses `display_number` — a permanent rank computed server-side via
    // ROW_NUMBER() OVER (ORDER BY id ASC), so it stays fixed per record
    // regardless of whatever sort is currently applied for display.
    const columns: ColumnDef<Membership>[] = [
        {
            header: 'ID',
            sortable: 'id',
            render: (row) => row.display_number,
        },
        {
            header: 'Member Code',
            sortable: 'member_code',
            render: (row) => (
                <span className="inline-block rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-900">
                    {row.member_code}
                </span>
            ),
        },
        {
            header: 'Description',
            sortable: 'member_desc',
            render: (row) => row.member_desc,
        },
        {
            header: 'Created At',
            sortable: 'created_at',
            render: (row) => formatDate(row.created_at),
            className: 'px-4 py-3 text-slate-500',
        },
    ];

    return (
        <ResourceTable<Membership>
            title="Memberships"
            addLabel="Add Membership"
            addHref={create()}
            columns={columns}
            resource={memberships}
            resourceKey="memberships"
            pollInterval={15000}
            editHref={(row) => edit(row.id)}
            deleteUrl={(id) => destroy(id).url}
            emptyIcon={Users}
            emptyMessage="No memberships found"
            deleteConfirmMessage="Are you sure you want to delete this membership?"
            sortOptions={[
                { label: 'Newest', sort: 'created_at', direction: 'desc' },
                { label: 'Oldest', sort: 'created_at', direction: 'asc' },
                { label: 'A–Z', sort: 'member_desc', direction: 'asc' },
                { label: 'Z–A', sort: 'member_desc', direction: 'desc' },
            ]}
        />
    );
}
