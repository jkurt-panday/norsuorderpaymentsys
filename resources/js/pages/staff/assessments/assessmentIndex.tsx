import { Head, usePoll } from '@inertiajs/react';
import { AssessmentFilters } from './assessment-filters';
import { columns } from './columns';
import type { AssessmentType } from './columns';
import { ServerDataTable } from '@/components/data-table/server-data-table';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';

type AssessmentProps = {
    assessments: {
        data: AssessmentType[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: {
        search: string;
        sort: string;
        direction: 'asc' | 'desc';
        date_from?: string;
        date_to?: string;
        course_id?: string;
        enrolled_under?: string;
        sy_last_attended?: string;
        semester?: string;
        status?: string;
    };
    filterOptions: {
        courses: { id: number; course_code: string }[];
        enrolledUnder: string[];
        syLastAttended: string[];
        semesters: string[];
        statuses: string[];
    };
};

export default function AssessmentIndex({
    assessments,
    filters,
    filterOptions,
}: AssessmentProps) {
    usePoll(15000, {
        only: ['assessments'],
    });

    return (
        <>
            <div className="mx-auto min-h-screen w-full max-w-7xl min-w-0 space-y-4 bg-page-background p-3 sm:p-6">
                <Head title="Index Assessment" />
                <Card className="rounded-2xl border border-slate-200/70 bg-white px-2! py-2! shadow-sm">
                    <CardHeader className="flex flex-row items-center justify-between p-3">
                        <CardTitle className="bg-[#3399FF] font-['Poppins'] bg-clip-text text-3xl font-black tracking-tight text-transparent sm:text-4xl">
                            Assessments
                        </CardTitle>
                    </CardHeader>
                </Card>

                <ServerDataTable
                    columns={columns}
                    data={assessments.data}
                    route="/staff/assessments"
                    filters={filters}
                    pagination={{
                        current_page: assessments.current_page,
                        last_page: assessments.last_page,
                        per_page: assessments.per_page,
                        total: assessments.total,
                    }}
                    sortableColumns={[
                        'reference_number',
                        'last_name',
                        'created_at',
                    ]}
                    extraFilters={({ values, setValue }) => (
                        <AssessmentFilters
                            values={values}
                            setValue={setValue}
                            courseOptions={filterOptions.courses}
                            enrolledUnderOptions={filterOptions.enrolledUnder}
                            syOptions={filterOptions.syLastAttended}
                            semesterOptions={filterOptions.semesters}
                            statusOptions={filterOptions.statuses}
                        />
                    )}
                />
            </div>
        </>
    );
}
