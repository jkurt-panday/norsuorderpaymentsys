import { Link, Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';

// table
import { columns } from './columns';
import type { CourseType } from './columns';
import { Button } from '@/components/base/buttons/button';
import { ClientDataTable } from '@/components/data-table/client-data-table';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';

// for client side search
type CoursesProps = {
    courses: CourseType[];
};

export default function CoursesIndex({ courses }: CoursesProps) {
    return (
        <>
            <div className="mx-auto min-h-screen w-full max-w-7xl min-w-0 space-y-4 bg-page-background p-3 sm:p-6">
                <Head title="Courses" />

                <Card className="rounded-2xl border border-border bg-card px-2! py-2! text-card-foreground shadow-sm">
                    <CardHeader className="flex flex-row items-center justify-between p-3">
                        <CardTitle className="bg-[#3399FF] font-['Poppins'] bg-clip-text text-3xl font-black tracking-tight text-transparent sm:text-4xl">
                            Courses
                        </CardTitle>

                        <Link href="/staff/courses/create">
                            <Button className="rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground shadow-sm transition-colors hover:bg-primary/90">
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
                        searchableColumns={[
                            'course_code',
                            'course_desc',
                            'course_college',
                        ]}
                    />
                </div>
            </div>
        </>
    );
}
