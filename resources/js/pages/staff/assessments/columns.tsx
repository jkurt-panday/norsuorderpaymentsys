'use client';

import { createColumnHelper } from '@tanstack/react-table';
import { Pencil, Trash2, Eye } from 'lucide-react';
import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { type DataTableFeatures } from '@/components/data-table/data-table-features';

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
};

const getEnrolledUnderBadgeClass = (enrolled_under: string) => {
    switch (enrolled_under) {
        case 'Undergraduate':
            return 'bg-emerald-100 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 px-4 py-3 text-xs';

        case 'Graduate':
            return 'bg-blue-100 text-blue-700 border border-blue-200 hover:bg-blue-100 px-4 py-3 text-xs';

        case 'School of Law':
            return 'bg-amber-100 text-amber-700 border border-amber-200 hover:bg-amber-100 px-4 py-3 text-xs';

        default:
            return 'bg-slate-100 text-slate-700 border border-slate-200 px-4';
    }
};

const getSemesterBadgeClass = (semester: string) => {
    switch (semester) {
        case 'First Semester':
            return 'bg-emerald-100 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 px-4 py-3 text-xs';

        case 'Second Semester':
            return 'bg-blue-100 text-blue-700 border border-blue-200 hover:bg-blue-100 px-4 py-3 text-xs';

        case 'Summer':
            return 'bg-amber-100 text-amber-700 border border-amber-200 hover:bg-amber-100 px-4 py-3 text-xs';

        default:
            return 'bg-slate-100 text-slate-700 border border-slate-200 px-4';
    }
};

const columnHelper = createColumnHelper<DataTableFeatures, AssessmentType>();

export const columns = columnHelper.columns([
    columnHelper.accessor('reference_number', {
        header: () => <div className="text-center">Reference No.</div>,
        cell: ({ row }) => (
            <div className="text-center font-medium text-blue-500">
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
                <div className="text-center text-slate-700">
                    {first_name} {middle_name} {last_name}
                </div>
            );
        },
    }),

    columnHelper.accessor('email', {
        header: () => <div className="text-center">Email</div>,
        cell: ({ row }) => (
            <div className="text-center text-slate-600">
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
                <span className="inline-flex rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold tracking-wide text-black">
                    {row.original.course.course_code}
                </span>
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
            <div className="text-center text-slate-600">
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
            <div className="text-center text-slate-400">
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

    columnHelper.display({
        id: 'actions',
        header: () => <div className="text-center">Actions</div>,
        enableSorting: false,
        cell: ({ row }) => {
            const assessment = row.original;
            return (
                <div className="flex justify-center">
                    <div className="grid w-max grid-cols-3">
                        <Link
                            href={`/staff/assessments/${assessment.id}`}
                            className="flex h-8 w-8 items-center justify-center rounded-l-2xl bg-slate-500 text-white transition-colors hover:bg-slate-600"
                        >
                            <Eye className="h-4 w-4" />
                        </Link>
                        <Link
                            href={`/staff/assessments/${assessment.id}/edit`}
                            className="flex h-8 w-8 items-center justify-center bg-amber-400 text-white transition-colors hover:bg-amber-500"
                        >
                            <Pencil className="h-4 w-4" />
                        </Link>
                        <Link
                            as="button"
                            method="delete"
                            href={`/staff/assessments/${assessment.id}`}
                            className="flex h-8 w-8 items-center justify-center rounded-r-2xl bg-red-600 text-white transition-colors hover:bg-red-700"
                        >
                            <Trash2 className="h-4 w-4" />
                        </Link>
                    </div>
                </div>
            );
        },
    }),
]);
