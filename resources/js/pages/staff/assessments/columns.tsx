'use client';

import { Link } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import { createColumnHelper } from '@tanstack/react-table';
import { Pencil, Trash2, Eye } from 'lucide-react';
import { useConfirm } from '@/components/confirm-dialog';
import type {DataTableFeatures} from '@/components/data-table/data-table-features';
import { Badge } from '@/components/ui/badge';
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "@/components/ui/tooltip"

export type AssessmentStatus = 'pending' | 'mailed' | 'rejected' | 'completed';

export type AssessmentType = {
    id: number;
    reference_number: string;
    email: string;
    contact_num: string;
    first_name: string;
    middle_name: string | null;
    last_name: string;
    address: string;
    enrolled_under: string;
    sy_last_attended: string;
    semester: string;
    created_at: string;
    // Assumes the controller eager-loads the `course` relation:
    // AssessmentForm::with('course')->...
    course: {
        id: number;
        course_code: string;
        course_desc: string;
    };
    status: AssessmentStatus | null;
};

const getEnrolledUnderBadgeClass = (enrolled_under: string) => {
    switch (enrolled_under) {
        case 'Undergraduate':
            return 'bg-emerald-100 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800/40 hover:bg-emerald-100 dark:hover:bg-emerald-950/70 px-4 py-3 text-xs';

        case 'Graduate':
            return 'bg-blue-100 text-blue-700 border border-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800/40 hover:bg-blue-100 dark:hover:bg-blue-950/70 px-4 py-3 text-xs';

        case 'School of Law':
            return 'bg-amber-100 text-amber-700 border border-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800/40 hover:bg-amber-100 dark:hover:bg-amber-950/70 px-4 py-3 text-xs';

        default:
            return 'bg-slate-100 text-slate-700 border border-slate-200 dark:bg-slate-800/60 dark:text-slate-300 dark:border-slate-700/40 px-4';
    }
};

const getSemesterBadgeClass = (semester: string) => {
    switch (semester) {
        case 'First Semester':
            return 'bg-emerald-100 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800/40 hover:bg-emerald-100 dark:hover:bg-emerald-950/70 px-4 py-3 text-xs';

        case 'Second Semester':
            return 'bg-blue-100 text-blue-700 border border-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800/40 hover:bg-blue-100 dark:hover:bg-blue-950/70 px-4 py-3 text-xs';

        case 'Summer':
            return 'bg-amber-100 text-amber-700 border border-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800/40 hover:bg-amber-100 dark:hover:bg-amber-950/70 px-4 py-3 text-xs';

        default:
            return 'bg-slate-100 text-slate-700 border border-slate-200 dark:bg-slate-800/60 dark:text-slate-300 dark:border-slate-700/40 px-4';
    }
};

const getStatusBadgeClass = (status: string) => {
    switch (status) {
        case 'pending':
            return 'bg-amber-100 text-amber-700 border border-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800/40 hover:bg-amber-100 dark:hover:bg-amber-950/70 px-4 py-3 text-xs';

        case 'completed':
            return 'bg-emerald-100 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800/40 hover:bg-emerald-100 dark:hover:bg-emerald-950/70 px-4 py-3 text-xs';

        case 'rejected':
            return 'bg-red-100 text-red-700 border border-red-200 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-800/40 hover:bg-red-100 dark:hover:bg-rose-950/70 px-4 py-3 text-xs';

        case 'mailed':
            return 'bg-blue-100 text-blue-700 border border-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800/40 hover:bg-blue-100 dark:hover:bg-blue-950/70 px-4 py-3 text-xs';

        default:
            return 'bg-slate-100 text-slate-700 border border-slate-200 dark:bg-slate-800/60 dark:text-slate-300 dark:border-slate-700/40 px-4';
    }
};

function AssessmentActionsCell({ assessment }: { assessment: AssessmentType }) {
    const confirm = useConfirm();

    const handleDelete = async () => {
        const ok = await confirm({
            title: `Delete assessment ${assessment.reference_number}?`,
            description: 'This action cannot be undone.',
            confirmLabel: 'Delete',
            variant: 'destructive',
        });

        if (ok) {
            router.delete(`/staff/assessments/delete/${assessment.id}`, {
                preserveScroll: true,
            });
        }
    };

    return (
        <div className="flex justify-center">
            <div className="grid w-max grid-cols-2">
                <Link
                    href={`/staff/assessments/edit/${assessment.id}`}
                    className="flex h-8 w-8 items-center justify-center rounded-l-2xl bg-amber-500 text-white transition-colors hover:bg-amber-600 dark:bg-amber-600 dark:hover:bg-amber-500"
                >
                    <Pencil className="h-4 w-4" />
                </Link>
                <button
                    type="button"
                    onClick={handleDelete}
                    className="flex h-8 w-8 items-center justify-center rounded-r-2xl bg-destructive text-destructive-foreground transition-colors hover:bg-destructive/90"
                >
                    <Trash2 className="h-4 w-4" />
                </button>
            </div>
        </div>
    );
}

const columnHelper = createColumnHelper<DataTableFeatures, AssessmentType>();

export const columns = columnHelper.columns([
    columnHelper.accessor('reference_number', {
        header: () => <div className="text-center">Reference No.</div>,
        cell: ({ row }) => (
            <div className="text-center font-medium text-primary">
                {row.getValue('reference_number')}
            </div>
        ),
    }),

    // Combines first/middle/last into a single readable name column
    columnHelper.display({
        id: 'full_name',
        header: () => <div className="text-center">Name</div>,
        cell: ({ row }) => {
            const { first_name, middle_name, last_name } = row.original;
            const middleInitial = middle_name
                ? `${middle_name.charAt(0)}.`
                : '';

            return (
                <div className="text-center text-foreground">
                    {first_name} {middleInitial} {last_name}
                </div>
            );
        },
    }),

    columnHelper.accessor('email', {
        header: () => <div className="text-center">Email</div>,
        cell: ({ row }) => (
            <div className="text-center text-muted-foreground">
                {row.getValue('email')}
            </div>
        ),
    }),

    // columnHelper.accessor("contact_num", {
    //   header: () => <div className="text-center">Contact No.</div>,
    //   cell: ({ row }) => (
    //     <div className="text-center text-slate-600">{row.getValue("contact_num")}</div>
    //   ),
    // }),

    // Pill badge, same pattern used for Courses' course_code
    columnHelper.display({
        id: 'course',
        header: () => <div className="text-center">Course</div>,
        cell: ({ row }) => (
            <div className="flex justify-center">
                <Tooltip>
                    <TooltipTrigger>
                        <span className="inline-flex rounded-full bg-primary/15 border border-primary/30 px-3 py-1 text-xs font-semibold tracking-wide text-primary">
                            {row.original.course.course_code}
                        </span>
                    </TooltipTrigger>
                    <TooltipContent
                        side='bottom'
                        className='bg-popover border border-border text-popover-foreground text-sm'>
                        {row.original.course.course_desc}
                    </TooltipContent>
                </Tooltip>
            </div>
        ),
    }),

    columnHelper.accessor('enrolled_under', {
        header: () => <div className="text-center">Enrolled Under</div>,
        cell: ({ row }) => {
            const enrolledUnder = row.getValue('enrolled_under') as string;

            return (
                <div className="flex justify-center">
                    <Badge
                        className={getEnrolledUnderBadgeClass(enrolledUnder)}
                    >
                        {enrolledUnder}
                    </Badge>
                </div>
            );
        },
    }),

    columnHelper.accessor('sy_last_attended', {
        header: () => <div className="text-center">SY Last Attended</div>,
        cell: ({ row }) => (
            <div className="text-center text-muted-foreground">
                {row.getValue('sy_last_attended')}
            </div>
        ),
    }),

    columnHelper.accessor('semester', {
        header: () => <div className="text-center">Semester</div>,
        cell: ({ row }) => {
            const semester = row.getValue('semester') as string;

            return (
                <div className="flex justify-center">
                    <Badge className={getSemesterBadgeClass(semester)}>
                        {semester}
                    </Badge>
                </div>
            );
        },
    }),

    columnHelper.accessor('created_at', {
        header: () => <div className="text-center">Submitted</div>,
        cell: ({ row }) => (
            <div className="text-center text-muted-foreground">
                {new Date(row.getValue('created_at')).toLocaleDateString(
                    'en-US',
                    {
                        month: 'short',
                        day: 'numeric',
                        year: 'numeric',
                    },
                )}
            </div>
        ),
    }),

    columnHelper.accessor('status', {
        header: () => <div className="text-center">Status</div>,
        cell: ({ row }) => {
            const status = row.getValue('status') as string | null;
    
            if (!status) {
                return (
                    <div className="flex justify-center">
                        <Badge className={getStatusBadgeClass('')}>
                            Unknown
                        </Badge>
                    </div>
                );
            }
    
            return (
                <div className="flex justify-center">
                    <Badge className={getStatusBadgeClass(status)}>
                        {status.charAt(0).toUpperCase() + status.slice(1)}
                    </Badge>
                </div>
            );
        },
    }),

    columnHelper.display({
        id: 'actions',
        header: () => <div className="text-center">Actions</div>,
        enableSorting: false,
        cell: ({ row }) => <AssessmentActionsCell assessment={row.original} />,
    }),
]);
