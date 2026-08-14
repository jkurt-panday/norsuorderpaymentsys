import { useForm } from '@inertiajs/react';
import PublicLayout from '@/pages/layouts/PublicLayout';

interface Courses {
    id: number | string;
    course_code: string;
    course_desc: string;
}

interface Props {
    courses: Courses[];
}

export default function AssessmentForm({ courses }: Props) {
    return (
        <>
            <p>hello world</p>
        </>
    )
}

AssessmentForm.layout = (page: React.ReactNode) => (
    <PublicLayout>{page}</PublicLayout>
);