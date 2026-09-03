import { useForm, Head, Link } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { FieldDescription } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';

interface Courses {
    id: number;
    course_code: string;
    course_desc: string;
}

interface CoursesEditProps {
    courses: Courses;
}

export default function CoursesEdit({ courses }: CoursesEditProps ) {

    const { data, setData, put, processing, errors } = useForm({
        course_code: courses.course_code,
        course_desc: courses.course_desc,
    });

    const handleSubmit = (e: React.SyntheticEvent<HTMLFormElement>) => {
        e.preventDefault();

        put(`/staff/courses/${courses.id}`, {
            forceFormData: true,
            preserveState: true,
            onSuccess: () => console.log('Success'),
            onError: (errs) => console.error('Validation errors: ', errs),
        });
    };
    
    return (
        <>
            <div className="mx-auto w-full max-w-3xl min-w-0 space-y-4 p-3 sm:p-6">
                <Head title="Add Course" />

                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold text-slate-900">
                        Edit Course
                    </h2>
                    <Link
                        href="/staff/courses"
                        className="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Back
                    </Link>
                </div>

                {/* form */}
                <Card className="w-full">
                    <CardContent className="p-6">
                        <form
                            onSubmit={handleSubmit}
                            className="grid grid-cols-1 gap-6 md:grid-cols-2"
                        >
                            {/* Course Code */}
                            <div className="flex flex-col gap-2">
                                <Label className="text-sm font-medium text-slate-800">
                                    Course Code
                                    <span className="text-red-400">*</span>
                                </Label>

                                <Input
                                    className="rounded-md border-slate-500 focus-visible:ring-slate-500/40"
                                    placeholder="ex. BSCS"
                                    value={data.course_code}
                                    onChange={(e) =>
                                        setData('course_code', e.target.value)
                                    }
                                />

                                <FieldDescription className="mt-1 text-xs text-slate-400">
                                    Unique identifier for this input.
                                </FieldDescription>

                                {errors.course_code && (
                                    <p className="text-sm text-red-500">
                                        {errors.course_code}
                                    </p>
                                )}
                            </div>

                            {/* Course Description */}
                            <div className="flex flex-col gap-2">
                                <Label className="text-sm font-medium text-slate-800">
                                    Course Description
                                    <span className="text-red-400">*</span>
                                </Label>

                                <Input
                                    className="rounded-md border-slate-500 focus-visible:ring-slate-500/40"
                                    placeholder="ex. BoS in Computer Science"
                                    value={data.course_desc}
                                    onChange={(e) =>
                                        setData('course_desc', e.target.value)
                                    }
                                />

                                {errors.course_desc && (
                                    <p className="text-sm text-red-500">
                                        {errors.course_desc}
                                    </p>
                                )}
                            </div>

                            {/* Submit */}
                            <div className="md:col-span-2">
                                <Separator />

                                <div className="mt-4 flex justify-end gap-2">
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="rounded-md bg-blue-900 text-white hover:bg-blue-950"
                                    >
                                        <Save className="h-4 w-4" />

                                        {processing ? (
                                            <span className="flex items-center gap-2">
                                                <svg
                                                    className="h-4 w-4 animate-spin text-white"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <circle
                                                        className="opacity-25"
                                                        cx="12"
                                                        cy="12"
                                                        r="10"
                                                        stroke="currentColor"
                                                        strokeWidth="4"
                                                    />
                                                    <path
                                                        className="opacity-75"
                                                        fill="currentColor"
                                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                                    />
                                                </svg>
                                                Updating...
                                            </span>
                                        ) : (
                                            'Update'
                                        )}
                                    </Button>
                                </div>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
