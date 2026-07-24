import React from 'react';
import { Code } from 'lucide-react';
import { create, edit, destroy } from '@/actions/App/Http/Controllers/UacsController';
import ResourceTable, { type PaginatedData, type ColumnDef } from '@/components/ResourceTable';
import { Badge } from '@/components/ui/badge';

// ============ TYPE DEFINITIONS ============
interface UacsRecord {
    id: number;
    object_code: string;
    account_title: string;
}

interface UacsIndexProps {
    uacs: PaginatedData<UacsRecord>;
}

// ============ COLUMNS ============
const columns: ColumnDef<UacsRecord>[] = [
    {
        header: 'ID',
        render: (row) => row.id,
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

// ============ COMPONENT ============
export default function UacsIndex({ uacs }: UacsIndexProps) {
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