import { Head } from '@inertiajs/react';
import { AssessmentFilters } from './assessment-filters';
import { columns  } from './columns';
import type {AssessmentType} from './columns';
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
    };
    filterOptions: {
        courses: { id: number; course_code: string }[];
        enrolledUnder: string[];
        syLastAttended: string[];
        semesters: string[];
    };
};

export default function AssessmentIndex({ assessments, filters, filterOptions }: AssessmentProps) {
    return (
        <>
            <div className="mx-auto min-h-screen w-full max-w-7xl min-w-0 space-y-4 bg-slate-50 p-3 sm:p-6">
                <Head title="Index Assessment" />
                <Card className="rounded-2xl border border-slate-200/70 bg-white px-2! py-2! shadow-sm">
                    <CardHeader className="flex flex-row items-center justify-between p-3">
                        <CardTitle className="text-xl font-bold text-slate-900">
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
                    sortableColumns={['reference_number', 'last_name', 'created_at']}
                    extraFilters={({ values, setValue }) => (
                        <AssessmentFilters
                            values={values}
                            setValue={setValue}
                            courseOptions={filterOptions.courses}
                            enrolledUnderOptions={filterOptions.enrolledUnder}
                            syOptions={filterOptions.syLastAttended}
                            semesterOptions={filterOptions.semesters}
                        />
                    )}
                />
            </div>
        </>
    );
}
