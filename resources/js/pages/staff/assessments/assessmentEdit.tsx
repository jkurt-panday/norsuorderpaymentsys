import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

interface Course {
    id: number;
    course_code: string;
    course_desc: string;
}

interface AssessmentFormModel {
    id: number;
    reference_number: string;
    email: string;
    contact_num: string;
    first_name: string;
    middle_name?: string;
    last_name: string;
    student_id?: string;
    course_id: string;
    address: string;
    enrolled_under: string;
    sy_last_attended: string;
    semester: string;
    course?: Course;
    created_at?: string;
    updated_at?: string;
}

interface AssessmentEditProps {
    assessment: AssessmentFormModel;
}

export default function AssessmentEdit({ assessment }: AssessmentEditProps) {
    const fullName = [
        assessment.first_name,
        assessment.middle_name,
        assessment.last_name,
    ]
        .filter(Boolean)
        .join(' ');

    return (
        <>
            <Head
                title={`Edit Assessment - ${assessment.reference_number || assessment.id}`}
            />

            <div className="mx-auto min-h-screen w-full max-w-7xl min-w-0 space-y-4 bg-slate-50 p-3 sm:p-6">
                <Card className="rounded-2xl border border-slate-200/70 bg-white px-2! py-2! shadow-sm">
                    <CardHeader className="flex flex-row items-center justify-between p-3">
                        {/*<div className="flex flex-col justify-between gap-2 sm:flex-row sm:items-center sm:justify-between">*/}
                        <CardTitle className="text-xl font-bold text-slate-900">
                            Assessment: {assessment.reference_number}
                        </CardTitle>
                        <Link
                            href="/staff/assessments"
                            className="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Back
                        </Link>
                        {/*</div>*/}
                    </CardHeader>
                </Card>

                <Card className="rounded-2xl border border-slate-200/70 bg-white px-2! py-2! shadow-sm">
                    <CardContent className="space-y-6 p-4 sm:p-6">
                        {/* Student Details Grid */}
                        <div>
                            <h3 className="mb-3 text-sm font-bold tracking-wider text-slate-500 uppercase">
                                Student Information
                            </h3>
                            <div className="grid grid-cols-1 gap-4 rounded-xl border border-slate-100 bg-slate-50 p-4 sm:grid-cols-2 md:grid-cols-3">
                                <div>
                                    <span className="text-xs font-semibold text-slate-400">
                                        Full Name
                                    </span>
                                    <p className="text-sm font-medium text-slate-900">
                                        {fullName || '—'}
                                    </p>
                                </div>
                                <div>
                                    <span className="text-xs font-semibold text-slate-400">
                                        Email
                                    </span>
                                    <p className="text-sm font-medium text-slate-900">
                                        {assessment.email || '—'}
                                    </p>
                                </div>
                                <div>
                                    <span className="text-xs font-semibold text-slate-400">
                                        Contact Number
                                    </span>
                                    <p className="text-sm font-medium text-slate-900">
                                        {assessment.contact_num
                                            ? assessment.contact_num
                                                  .replace(/\D/g, '')
                                                  .replace(/(\d{4})(\d{3})(\d{4})/, '$1 $2 $3')
                                            : '—'}
                                    </p>
                                </div>
                                <div className="sm:col-span-2 md:col-span-3">
                                    <span className="text-xs font-semibold text-slate-400">
                                        Address
                                    </span>
                                    <p className="text-sm font-medium text-slate-900">
                                        {assessment.address || '—'}
                                    </p>
                                </div>
                                <div className="sm:col-span-2 md:col-span-3">
                                    <span className="text-xs font-semibold text-slate-400">
                                        Student ID
                                    </span>
                                    <p className="text-sm font-medium text-slate-900">
                                        {assessment.student_id || '—'}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Course & Academic Details Grid */}
                        <div>
                            <h3 className="mb-3 text-sm font-bold tracking-wider text-slate-500 uppercase">
                                Course & Academic Information
                            </h3>
                            <div className="grid grid-cols-1 gap-4 rounded-xl border border-slate-100 bg-slate-50 p-4 sm:grid-cols-2 md:grid-cols-3">
                                <div>
                                    <span className="text-xs font-semibold text-slate-400">
                                        Course Code
                                    </span>
                                    <p className="text-sm font-medium text-slate-900">
                                        {assessment.course?.course_code ||
                                            `ID: ${assessment.course_id}`}
                                    </p>
                                </div>
                                <div>
                                    <span className="text-xs font-semibold text-slate-400">
                                        Course Description
                                    </span>
                                    <p className="text-sm font-medium text-slate-900">
                                        {assessment.course?.course_desc ||
                                            'No relationship loaded'}
                                    </p>
                                </div>
                                <div>
                                    <span className="text-xs font-semibold text-slate-400">
                                        Enrolled Under
                                    </span>
                                    <p className="text-sm font-medium text-slate-900">
                                        {assessment.enrolled_under || '—'}
                                    </p>
                                </div>
                                <div>
                                    <span className="text-xs font-semibold text-slate-400">
                                        Semester
                                    </span>
                                    <p className="text-sm font-medium text-slate-900">
                                        {assessment.semester || '—'}
                                    </p>
                                </div>
                                <div>
                                    <span className="text-xs font-semibold text-slate-400">
                                        SY Last Attended
                                    </span>
                                    <p className="text-sm font-medium text-slate-900">
                                        {assessment.sy_last_attended || '—'}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Raw Model Dump */}
                        <div>
                            <h3 className="mb-2 text-xs font-bold tracking-wider text-slate-400 uppercase">
                                Raw Passed Props (Debug)
                            </h3>
                            <pre className="max-h-80 overflow-auto rounded-xl bg-slate-950 p-4 font-mono text-xs text-emerald-400 shadow-inner">
                                {JSON.stringify(assessment, null, 2)}
                            </pre>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
