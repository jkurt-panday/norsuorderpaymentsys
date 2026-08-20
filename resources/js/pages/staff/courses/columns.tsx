import type { ColumnDef, TableFeatures } from '@tanstack/react-table';
import { Pencil, Trash2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/react';

export type Course = {
    id: number;
    course_code: string;
    course_desc: string;
    created_at: string;
    updated_at: string;
};

export const columns: ColumnDef<TableFeatures, Course>[] = [
    // {
    //     accessorKey: 'id',
    //     header: 'ID',
    // },
    {
        id: 'row_number',
        header: 'ID',
        cell: ({ row }) => row.index + 1,
    },

    {
        accessorKey: 'course_code',
        header: 'Course Code',
    },

    {
        accessorKey: 'course_desc',
        header: 'Course Description',
    },

    {
        id: 'actions',
        header: 'Actions',
        cell: ({ row }) => {
            const course = row.original;

            return (
                <div className="grid w-max grid-cols-2">
                    <Link
                          href={`/staff/courses/${course.id}/edit`}
                    className="flex h-8 w-8 items-center justify-center rounded-l-2xl bg-amber-400 text-white transition-colors hover:bg-amber-500"
                  >
                    <Pencil className="h-4 w-4" />
                  </Link>
                
                  <Link
                    className="flex h-8 w-8 items-center justify-center rounded-r-2xl border-white/20 bg-red-600 text-white transition-colors hover:bg-red-700"
                    onClick={() => console.log(course)}
                  >
                    <Trash2 className="h-4 w-4" />
                  </Link>
                </div>
            );
        },
    },
];
