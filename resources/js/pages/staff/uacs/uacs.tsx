import React, { useEffect } from 'react';
import { Code } from 'lucide-react';
import { flashToast } from '@/utils/flashToast';
import {
    create,
    edit,
    destroy,
} from '@/actions/App/Http/Controllers/UACSController';
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
interface UacsRecord {
    id: number;
    object_code: string;
    account_title: string;
    created_at: string;
    display_number: number;
}

interface FlashProps {
    success?: string;
    error?: string;
}

interface UacsIndexProps {
    uacs: PaginatedData<UacsRecord>;
    flash?: FlashProps;
}

// ============ COMPONENT ============
export default function UacsIndex({ uacs, flash }: UacsIndexProps) {
    // Surface flash messages from the backend (e.g. delete blocked because the
    // UACS code is still in use). Without this, a rejected delete silently
    // redirects back with no visible feedback to the user.
    useEffect(() => {
        if (flash?.success) flashToast('success', flash.success);
        if (flash?.error) flashToast('error', flash.error);
    }, [flash]);

    const columns: ColumnDef<UacsRecord>[] = [
        {
            header: 'ID',
            sortable: 'id',
            render: (row) => row.display_number,
        },
        {
            header: 'Object Code',
            sortable: 'object_code',
            render: (row) => (
                <Badge
                    variant="secondary"
                    className="bg-slate-800 text-white hover:bg-slate-800"
                >
                    {row.object_code}
                </Badge>
            ),
        },
        {
            header: 'Account Title',
            sortable: 'account_title',
            render: (row) => row.account_title,
        },
    ];

    return (
        <ResourceTable<UacsRecord>
            title="UACS Account Information"
            addLabel="Add UACS"
            addHref={create()}
            columns={columns}
            resource={uacs}
            resourceKey="uacs"
            pollInterval={15000}
            editHref={(row) => edit(row.id)}
            deleteUrl={(id) => destroy(id).url}
            emptyIcon={Code}
            emptyMessage="No UACS records found"
            deleteConfirmMessage="Are you sure you want to delete this UACS record?"
            sortOptions={[
                { label: 'Newest', sort: 'created_at', direction: 'desc' },
                { label: 'Oldest', sort: 'created_at', direction: 'asc' },
                { label: 'A–Z', sort: 'account_title', direction: 'asc' },
                { label: 'Z–A', sort: 'account_title', direction: 'desc' },
            ]}
        />
    );
}
