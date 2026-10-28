import { Link, router } from '@inertiajs/react';
import { createColumnHelper } from '@tanstack/react-table';
import { Pencil, Trash2 } from 'lucide-react';

import { useConfirm } from '@/components/confirm-dialog';
import type { DataTableFeatures } from '@/components/data-table/data-table-features';

export type UacsType = {
    id: number;
    norsu_object_code: string | null;
    norsu_sub_object_code: string | null;
    uacs_object_code: string | null;
    uacs_sub_object_code: string | null;
    rca_code: string | null;
    account_title: string | null;
    created_at: string;
    updated_at: string;
    display_number?: number;
};

const columnHelper = createColumnHelper<DataTableFeatures, UacsType>();

function UacsActionsCell({ uacs }: { uacs: UacsType }) {
    const confirm = useConfirm();

    const handleDelete = async () => {
        const ok = await confirm({
            title: `Delete UACS ${uacs.uacs_object_code ?? uacs.norsu_object_code ?? ''}?`,
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
        <div className="mr-6 grid w-max grid-cols-2 justify-self-end-safe">
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
        enableHiding: false,
        cell: ({ row }) => row.original.display_number ?? row.index + 1,
    }),
    columnHelper.accessor('norsu_object_code', {
        header: 'NORSU OBJECT CODE',
        cell: ({ row }) => row.original.norsu_object_code ?? '—',
    }),
    columnHelper.accessor('norsu_sub_object_code', {
        header: 'NORSU SUB-OBJECT CODE',
        cell: ({ row }) => row.original.norsu_sub_object_code ?? '—',
    }),
    columnHelper.accessor('uacs_object_code', {
        header: 'UACS OBJECT CODE',
        cell: ({ row }) => row.original.uacs_object_code ?? '—',
    }),
    columnHelper.accessor('uacs_sub_object_code', {
        header: 'UACS SUB-OBJECT CODE',
        cell: ({ row }) => row.original.uacs_sub_object_code ?? '—',
    }),
    columnHelper.accessor('rca_code', {
        header: 'RCA CODE',
        cell: ({ row }) => row.original.rca_code ?? '—',
    }),
    columnHelper.accessor('account_title', {
        header: 'ACCOUNT TITLE',
        cell: ({ row }) => row.original.account_title ?? '—',
    }),
    columnHelper.display({
        id: 'actions',
        header: () => (
            <div className="mr-10 justify-self-end-safe">Actions</div>
        ),
        enableHiding: false,
        cell: ({ row }) => <UacsActionsCell uacs={row.original} />,
    }),
]);
