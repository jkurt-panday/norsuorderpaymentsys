import { Link, router } from '@inertiajs/react';
import { createColumnHelper } from '@tanstack/react-table';
import { Pencil, Trash2 } from 'lucide-react';

import { useConfirm } from '@/components/confirm-dialog';
import type { DataTableFeatures } from '@/components/data-table/data-table-features';
import { Badge } from '@/components/ui/badge';

export type UacsType = {
    id: number;
    classification: string | null;
    sub_class: string | null;
    group: string | null;
    object_code: string | null;
    sub_object_code: string | null;
    uacs: string | null;
    status: string | null;
    created_at: string;
    updated_at: string;
    display_number?: number;
};

const columnHelper = createColumnHelper<DataTableFeatures, UacsType>();

function UacsActionsCell({ uacs }: { uacs: UacsType }) {
    const confirm = useConfirm();

    const handleDelete = async () => {
        const ok = await confirm({
            title: `Delete UACS ${uacs.uacs ?? uacs.object_code ?? ''}?`,
            description: 'This action cannot be undone.',
            confirmLabel: 'Delete',
            variant: 'destructive',
        });

        if (ok) {
            router.delete(`/staff/uacs/${uacs.id}`, {
                preserveScroll: true,
            });
        }
    };

    return (
        <div className="grid w-max grid-cols-2 justify-self-end-safe mr-6">
            <Link
                href={`/staff/uacs/${uacs.id}/edit`}
                className="flex h-8 w-8 items-center justify-center rounded-l-2xl bg-amber-400 text-white transition-colors hover:bg-amber-500"
                aria-label="Edit UACS"
            >
                <Pencil className="h-4 w-4" />
            </Link>
            <button
                type="button"
                onClick={handleDelete}
                className="flex h-8 w-8 items-center justify-center rounded-r-2xl bg-red-600 text-white transition-colors hover:bg-red-700"
                aria-label="Delete UACS"
            >
                <Trash2 className="h-4 w-4" />
            </button>
        </div>
    );
}

export const columns = columnHelper.columns([
    columnHelper.display({
        id: 'row_number',
        header: 'ID',
        cell: ({ row }) => row.original.display_number ?? row.index + 1,
    }),
    columnHelper.accessor('classification', {
        header: 'CLASSIFICATION',
    }),
    columnHelper.accessor('sub_class', {
        header: 'SUB-CLASS',
    }),
    columnHelper.accessor('group', {
        header: 'GROUP',
    }),
    columnHelper.accessor('object_code', {
        header: 'OBJECT CODE',
        cell: ({ row }) => row.original.object_code ?? '—',
    }),
    columnHelper.accessor('sub_object_code', {
        header: 'SUB-OBJECT CODE',
        cell: ({ row }) => row.original.sub_object_code ?? '—',
    }),
    columnHelper.accessor('uacs', {
        header: 'UACS',
        cell: ({ row }) => (
            <Badge className="bg-blue-400 px-4 py-3 font-sans text-[.9rem] font-semibold tracking-wide text-white hover:bg-blue-500">
                {row.original.uacs ?? '—'}
            </Badge>
        ),
    }),
    columnHelper.accessor('status', {
        header: 'STATUS',
        cell: ({ row }) => (
            <Badge
                variant={
                    row.original.status === 'Active' ? 'default' : 'secondary'
                }
                className="px-4 py-3 font-sans text-medium font-semibold tracking-wide"
            >
                {row.original.status ?? '—'}
            </Badge>
        ),
    }),
    columnHelper.display({
        id: 'actions',
        header: 'ACTIONS',
        cell: ({ row }) => <UacsActionsCell uacs={row.original} />,
    }),
]);
