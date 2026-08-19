import type { ColumnDef, TableFeatures } from '@tanstack/react-table';
import { MoreHorizontal } from 'lucide-react';

import { Button } from '@/components/ui/button';

export type Course = {
    id: number;
    course_code: string;
    course_desc: string;
    created_at: string;
    updated_at: string;
};

export const columns: ColumnDef<TableFeatures, Course>[] = [
    {
        accessorKey: 'id',
        header: 'ID',
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
                <Button
                    variant="ghost"
                    size="icon"
                    onClick={() => console.log(course)}
                >
                    <MoreHorizontal />
                </Button>
            );
        },
    },
];