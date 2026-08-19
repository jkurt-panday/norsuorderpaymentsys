import { Link, Head } from '@inertiajs/react';
import {
    Card,
    CardHeader,
    CardTitle,
    CardDescription,
    CardContent,
    CardFooter,
} from '@/components/ui/card';
import { Button } from '@/components/base/buttons/button';
import { Plus } from 'lucide-react';

// table
import { columns, type Course } from './columns';
import { DataTable } from './data-table';

// interface Course {
//     id: number;
//     course_code: string;
//     course_desc: string;
// }

type CoursesProps = {
    courses: {
        data: Course[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
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

                        <Button className="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-blue-700">
                            <div className="flex flex-row items-center gap-1.5">
                                <Plus className="h-4 w-4 stroke-[2.5]" />
                                <span>Add Course</span>
                            </div>
                        </Button>
                    </CardHeader>
                </Card>

                <DataTable columns={columns} data={courses.data} />
            </div>
        </>
    );
}

// <div className="p-6">
//     <div className="flex justify-between items-center mb-6">
//         <h1 className="text-2xl font-bold">Courses List</h1>
//         <Link
//             href={route('courses.create')}
//             className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
//         >
//             Add New Course
//         </Link>
//     </div>

//     <div className="overflow-x-auto">
//         <table className="min-w-full divide-y divide-gray-200 border">
//             <thead className="bg-gray-50">
//                 <tr>
//                     <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
//                     <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
//                     <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
//                 </tr>
//             </thead>
//             <tbody className="bg-white divide-y divide-gray-200">
//                 {courses.map((course) => (
//                     <tr key={course.id}>
//                         <td className="px-6 py-4 whitespace-nowrap font-semibold">{course.course_code}</td>
//                         <td className="px-6 py-4 whitespace-nowrap">{course.course_desc}</td>
//                         <td className="px-6 py-4 whitespace-nowrap text-right space-x-2">
//                             <Link
//                                 href={route('courses.edit', course.id)}
//                                 className="text-indigo-600 hover:text-indigo-900"
//                             >
//                                 Edit
//                             </Link>
//                         </td>
//                     </tr>
//                 ))}
//             </tbody>
//         </table>
//     </div>
// </div>
