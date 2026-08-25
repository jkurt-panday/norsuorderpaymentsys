import type { ColumnDef, TableFeatures } from '@tanstack/react-table';
import { Pencil, Trash2 } from 'lucide-react';
import { createColumnHelper } from '@tanstack/react-table';
import { Link } from '@inertiajs/react';

import type { DataTableFeatures } from '@/components/data-table/data-table-features';

export type CourseType = {
    id: number;
    course_code: string;
    course_desc: string;
    created_at: string;
    updated_at: string;
};

const columnHelper = createColumnHelper<DataTableFeatures, CourseType>();

export const columns = columnHelper.columns([
    columnHelper.display({
        id: 'row_number',
        header: 'ID',
        cell: ({ row }) => row.index + 1,
    }),

    columnHelper.accessor('course_code', {
        header: 'COURSE CODE',
    }),

    columnHelper.accessor('course_desc', {
        header: 'COURSE DESCRIPTION',
    }),

    columnHelper.display({
        id: 'actions',
        header: () => <div className="justify-self-end-safe">Actions</div>,
        cell: ({ row }) => {
            const course = row.original;

            return (
                <div className="grid w-max grid-cols-2 justify-self-end-safe">
                    <Link
                        href={`/staff/courses/${course.id}/edit`}
                        className="flex h-8 w-8 items-center justify-center rounded-l-2xl bg-amber-400 text-white transition-colors hover:bg-amber-500"
                    >
                        <Pencil className="h-4 w-4" />
                    </Link>

                    <Link
                        as="button"
                        method="delete"
                        href={`/staff/courses/${course.id}`}
                        className="flex h-8 w-8 items-center justify-center rounded-r-2xl border-white/20 bg-red-600 text-white transition-colors hover:bg-red-700"
                    >
                        <Trash2 className="h-4 w-4" />
                    </Link>
                </div>
            );
        },
    }),
]);
