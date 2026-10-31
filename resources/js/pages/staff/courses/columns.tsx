import { Link } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import { createColumnHelper } from '@tanstack/react-table';
import { Pencil, Trash2 } from 'lucide-react';

import { useConfirm } from '@/components/confirm-dialog';
import type { DataTableFeatures } from '@/components/data-table/data-table-features';
import { Badge } from '@/components/ui/badge';

export type CourseType = {
    id: number;
    course_code: string;
    course_desc: string;
    course_college: string;
    created_at: string;
    updated_at: string;
};

const columnHelper = createColumnHelper<DataTableFeatures, CourseType>();

function CourseActionsCell({ course }: { course: CourseType }) {
    const confirm = useConfirm();

    const handleDelete = async () => {
        const ok = await confirm({
            title: `Delete "${course.course_code} - ${course.course_desc}"?`,
            description: 'This action cannot be undone.',
            confirmLabel: 'Delete',
            variant: 'destructive',
        });

        if (ok) {
            router.delete(`/staff/courses/${course.id}`, {
                preserveScroll: true,
            });
        }
    };

    return (
        <div className="flex w-max items-center gap-1.5 justify-self-end-safe mr-6">
            <Link
                href={`/staff/courses/${course.id}/edit`}
                className="flex h-8 w-8 items-center justify-center rounded-md bg-amber-600 text-white transition-colors hover:bg-amber-700 dark:bg-amber-500 dark:hover:bg-amber-600 dark:text-neutral-950"
                aria-label="Edit course"
            >
                <Pencil className="h-4 w-4" />
            </Link>

            <button
                type="button"
                onClick={handleDelete}
                className="flex h-8 w-8 items-center justify-center rounded-md bg-red-600 text-white transition-colors hover:bg-red-700"
                aria-label="Delete course"
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
        cell: ({ row }) => row.index + 1,
    }),

    columnHelper.accessor('course_code', {
        header: 'COURSE CODE',
        cell: ({ row }) => (
            <div className="flex justify-left">
                <Badge
                    className=" bg-blue-400 px-4 py-3 text-sm font-bold tracking-wide text-white"
                    variant={'destructive'}>
                    {row.original.course_code}
                </Badge>
            </div>
        ),
    }),

    columnHelper.accessor('course_desc', {
        header: 'COURSE DESCRIPTION',
    }),

    columnHelper.accessor('course_college', {
        header: 'COLLEGE',
    }),

    columnHelper.display({
        id: 'actions',
        header: () => <div className="justify-self-end-safe mr-10">Actions</div>,
        cell: ({ row }) => <CourseActionsCell course={row.original} />,
    }),
]);
