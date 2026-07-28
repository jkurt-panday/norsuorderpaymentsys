import React, { useEffect } from 'react';
import { Code } from 'lucide-react';
import { toast } from 'sonner';
import { create, edit, destroy } from '@/actions/App/Http/Controllers/UacsController';
import ResourceTable, { type PaginatedData, type ColumnDef } from '@/components/ResourceTable';
import { Badge } from '@/components/ui/badge';

// ============ TYPE DEFINITIONS ============
interface UacsRecord {
    id: number;
    object_code: string;
    account_title: string;
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
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    // ============ COLUMNS ============
    const columns: ColumnDef<UacsRecord>[] = [
        {
            header: 'ID',
            render: (row, index) => (uacs.from ?? 1) + index,
        },
        {
            header: 'Object Code',
            render: (row) => (
                <Badge variant="secondary" className="bg-slate-800 text-white hover:bg-slate-800">
                    {row.object_code}
                </Badge>
            ),
        },
        {
            header: 'Account Title',
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
            editHref={(row) => edit(row.id)}
            deleteUrl={(id) => destroy(id).url}
            emptyIcon={Code}
            emptyMessage="No UACS records found"
            deleteConfirmMessage="Are you sure you want to delete this UACS record?"
        />
    );
}