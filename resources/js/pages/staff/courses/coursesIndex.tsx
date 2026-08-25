import { Link, Head } from '@inertiajs/react';
import {
    Card,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/base/buttons/button';
import { Plus } from 'lucide-react';

// table
import { columns, type CourseType } from './columns';
import { ClientDataTable } from '@/components/data-table/client-data-table';

// for client side search
type CoursesProps = {
    courses: CourseType[];
};

export default function CoursesIndex({ courses }: CoursesProps) {
    return (
        <>
            <div className="mx-auto min-h-screen w-full max-w-7xl min-w-0 space-y-4 bg-slate-50 p-3 sm:p-6">
                <Head title="Courses" />

                <Card className="rounded-2xl border border-slate-200/70 bg-white px-2! py-2! shadow-sm">
                    <CardHeader className="flex flex-row items-center justify-between p-3">
                        <CardTitle className="text-xl font-bold text-slate-900">
                            Courses
                        </CardTitle>

                        <Link href="/staff/courses/create">
                            <Button className="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-blue-700">
                                <div className="flex flex-row items-center gap-1.5">
                                    <Plus className="h-4 w-4 stroke-[2.5]" />
                                    <span>Add Course</span>
                                </div>
                            </Button>
                        </Link>
                    </CardHeader>
                </Card>

                <div className="rounded-2xl">
                    <ClientDataTable
                        columns={columns}
                        data={courses}
                        searchableColumns={['course_code', 'course_desc']}
                    />
                </div>
            </div>
        </>
    );
}
