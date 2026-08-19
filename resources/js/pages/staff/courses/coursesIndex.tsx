import { Link } from '@inertiajs/react';

interface Course {
    id: number;
    course_code: string;
    course_desc: string;
}

interface Props {
    courses: Course[];
}

export default function CoursesIndex({ courses }: Props) {
    return (
        <div className="p-6">
            <div className="flex justify-between items-center mb-6">
                <h1 className="text-2xl font-bold">Courses List</h1>
                <Link
                    href={route('courses.create')}
                    className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                >
                    Add New Course
                </Link>
            </div>

            <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200 border">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                        {courses.map((course) => (
                            <tr key={course.id}>
                                <td className="px-6 py-4 whitespace-nowrap font-semibold">{course.course_code}</td>
                                <td className="px-6 py-4 whitespace-nowrap">{course.course_desc}</td>
                                <td className="px-6 py-4 whitespace-nowrap text-right space-x-2">
                                    <Link
                                        href={route('courses.edit', course.id)}
                                        className="text-indigo-600 hover:text-indigo-900"
                                    >
                                        Edit
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}