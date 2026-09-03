import { Link } from '@inertiajs/react';
import {
    CheckCircle,
    FileText,
    Mail,
    Phone,
    MapPin,
    User,
    Receipt,
    IdCard,
    Printer,
    Hash,
} from 'lucide-react';
import React, { useRef } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
    CardFooter,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import PublicLayout from '@/pages/layouts/PublicLayout';

interface Courses {
    id: number | string;
    course_code: string;
    course_desc: string;
}

interface AssessmentForm {
    id: number | string;
    reference_number: string;
    email: string;
    contact_num: string;
    first_name: string;
    middle_name: string | null;
    last_name: string;
    student_id?: string;
    course?: Courses;
    address: string;
    enrolled_under: string;
    sy_last_attended: string;
    semester: string;
    created_at: string;
}

interface Props {
    assessmentForm: AssessmentForm;     // must be the same format as the one being sent
}

export default function AssessmentSuccess({ assessmentForm }: Props) {
    // Format date
    const formatDate = (date: string) => {
        return new Date(date).toLocaleDateString('en-PH', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const formatContactNumber = (contactNum: string) => {
        if (!contactNum) {
return '';
}
    
        return contactNum.replace(
            /^(\d{4})(\d{3})(\d{4})$/,
            '$1 $2 $3',
        );
    };

    const getEnrolledUnderBadgeClass = (enrolled_under: string) => {
        switch (enrolled_under) {
            case 'Undergraduate':
                return 'bg-emerald-100 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 px-4';

            case 'Graduate':
                return 'bg-blue-100 text-blue-700 border border-blue-200 hover:bg-blue-100 px-4';

            case 'School of Law':
                return 'bg-amber-100 text-amber-700 border border-amber-200 hover:bg-amber-100 px-4';

            default:
                return 'bg-slate-100 text-slate-700 border border-slate-200 px-4';
        }
    };

    const getSemesterBadgeClass = (semester: string) => {
        switch (semester) {
            case 'First Semester':
                return 'bg-emerald-100 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 px-4';

            case 'Second Semester':
                return 'bg-blue-100 text-blue-700 border border-blue-200 hover:bg-blue-100 px-4';

            case 'Summer':
                return 'bg-amber-100 text-amber-700 border border-amber-200 hover:bg-amber-100 px-4';

            default:
                return 'bg-slate-100 text-slate-700 border border-slate-200 px-4';
        }
    };

    // ? for printing
    const receiptRef = useRef<HTMLDivElement>(null);

    const handlePrint = () => {
        window.open(
            `/public/assessment_complete/${assessmentForm.reference_number}/print`,
            '_blank',
        );
    };

    return (
        <>
            <div className="min-h-screen bg-linear-to-b from-blue-500 via-slate-100 to-white py-6 sm:py-12">
                <div className="mx-auto max-w-5xl px-3 sm:px-6">
                    <div
                        className="print-container container mx-auto max-w-4xl rounded-2xl px-0 py-4 sm:rounded-3xl sm:px-4 sm:py-8"
                        id="print-receipt"
                        ref={receiptRef}
                    >
                        <Card className="print-card overflow-hidden rounded-2xl bg-white shadow-xl sm:rounded-3xl sm:shadow-2xl print:shadow-none">
                            <CardHeader className="print-header -mx-6 -mt-6 rounded-t-2xl rounded-b-2xl bg-linear-to-b from-blue-600 to-blue-400 px-4 py-8 text-center text-white sm:rounded-t-3xl sm:rounded-b-3xl sm:px-8 sm:py-10">
                                <div className="mx-auto flex items-center justify-center">
                                    <img
                                        src="/finance_logo1.png"
                                        alt="NORSU Logo"
                                        width={500}
                                        height={500}
                                        className="h-auto max-w-70 pb-4 sm:max-w-md sm:pb-6"
                                    />
                                </div>
                                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-white/15 backdrop-blur-sm sm:h-24 sm:w-24">
                                    <CheckCircle className="h-10 w-10 text-white sm:h-14 sm:w-14" />
                                </div>
                                <CardTitle className="mt-2 text-2xl font-bold text-green-300 sm:text-3xl">
                                    Submission Successful!
                                </CardTitle>
                                <CardDescription className="mt-1 text-sm text-blue-100 sm:mt-2 sm:text-base">
                                    Your request has been submitted
                                    successfully.
                                </CardDescription>
                            </CardHeader>

                            <CardContent className="print-content space-y-6 px-4 pt-6 sm:space-y-10 sm:px-6 sm:pt-8">
                                {/* Reference Number */}
                                <div className="rounded-xl border border-blue-100 bg-blue-50 p-4 text-center shadow-sm sm:rounded-2xl sm:p-8">
                                    <p className="text-xs text-muted-foreground sm:text-sm">
                                        Reference Number
                                    </p>
                                    <p className="my-1 font-mono text-2xl font-bold tracking-wider break-all text-blue-700 sm:my-2 sm:text-4xl sm:tracking-widest">
                                        {assessmentForm.reference_number}
                                    </p>
                                    <p className="text-xs text-slate-500 sm:text-sm">
                                        Please keep this reference number for
                                        tracking your request.
                                    </p>
                                </div>

                                <Separator />

                                {/* Contact Information */}
                                <div>
                                    <h3 className="mb-4 flex items-center gap-2 text-xl font-semibold text-blue-900 sm:mb-6 sm:gap-3 sm:text-2xl">
                                        <User className="h-5 w-5 shrink-0 text-blue-700 sm:h-6 sm:w-6" />
                                        Contact Information
                                    </h3>
                                    <div className="grid grid-cols-1 gap-x-10 gap-y-4 sm:gap-y-6 md:grid-cols-2">
                                        <div className="flex items-start gap-2">
                                            <Mail className="mt-1 h-4 w-4 shrink-0 text-muted-foreground" />
                                            <div className="min-w-0 flex-1">
                                                <p className="mb-1 text-xs font-medium tracking-wide text-slate-500 uppercase sm:text-sm">
                                                    Email
                                                </p>
                                                <p className="text-base font-semibold break-all text-slate-900 sm:text-xl">
                                                    {assessmentForm.email}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-start gap-2">
                                            <Phone className="mt-1 h-4 w-4 shrink-0 text-muted-foreground" />
                                            <div className="min-w-0 flex-1">
                                                <p className="mb-1 text-xs font-medium tracking-wide text-slate-500 uppercase sm:text-sm">
                                                    Contact Number
                                                </p>
                                                <p className="text-base font-semibold break-all text-slate-900 sm:text-xl">
                                                    {formatContactNumber(assessmentForm.contact_num)}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <Separator />

                                {/* Identity Details */}
                                <div>
                                    <h3 className="mb-4 flex items-center gap-2 text-xl font-semibold text-blue-900 sm:mb-6 sm:gap-3 sm:text-2xl">
                                        <IdCard className="h-5 w-5 shrink-0 text-blue-700 sm:h-6 sm:w-6" />
                                        Identity Details
                                    </h3>
                                
                                    <div className="grid grid-cols-1 gap-x-10 gap-y-4 sm:gap-y-6 md:grid-cols-2">
                                        {/* Full Name */}
                                        <div className="col-span-full">
                                            <p className="mb-1 text-xs font-medium tracking-wide text-slate-500 uppercase sm:text-sm">
                                                Full Name
                                            </p>
                                            <p className="text-base font-semibold wrap-break-word text-slate-900 sm:text-xl">
                                                {[
                                                    assessmentForm.last_name
                                                        ? `${assessmentForm.last_name},`
                                                        : null,
                                                    assessmentForm.first_name,
                                                    assessmentForm.middle_name,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' ')}
                                            </p>
                                        </div>

                                        {/* Student id */}
                                        <div className="col-span-full flex items-start gap-2">
                                            <Hash className="mt-1 h-4 w-4 shrink-0 text-muted-foreground" />
                                
                                            <div className="min-w-0 flex-1">
                                                <p className="mb-1 text-xs font-medium tracking-wide text-slate-500 uppercase sm:text-sm">
                                                    Student ID
                                                </p>
                                                <p className="text-base font-semibold wrap-break-word text-slate-900 sm:text-xl">
                                                    {assessmentForm.student_id}
                                                </p>
                                            </div>
                                        </div>
                                
                                        {/* Address */}
                                        <div className="col-span-full flex items-start gap-2">
                                            <MapPin className="mt-1 h-4 w-4 shrink-0 text-muted-foreground" />
                                
                                            <div className="min-w-0 flex-1">
                                                <p className="mb-1 text-xs font-medium tracking-wide text-slate-500 uppercase sm:text-sm">
                                                    Address
                                                </p>
                                                <p className="text-base font-semibold wrap-break-word text-slate-900 sm:text-xl">
                                                    {assessmentForm.address}
                                                </p>
                                            </div>
                                        </div>
                                
                                        {/* Course */}
                                        <div className="col-span-full md:col-span-1">
                                            <p className="mb-1 text-xs font-medium tracking-wide text-slate-500 uppercase sm:text-sm">
                                                Course
                                            </p>
                                            <p className="text-base font-semibold wrap-break-word text-slate-900 sm:text-xl">
                                                {assessmentForm.course?.course_desc || ''}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <Separator />
                                
                                {/* Assessment Details */}
                                <div>
                                    <h3 className="mb-4 flex items-center gap-2 text-xl font-semibold text-blue-900 sm:mb-6 sm:gap-3 sm:text-2xl">
                                        <Receipt className="h-5 w-5 shrink-0 text-blue-700 sm:h-6 sm:w-6" />
                                        Assessment Details
                                    </h3>
                                
                                    <div className="grid grid-cols-1 gap-x-10 gap-y-4 sm:gap-y-6 md:grid-cols-3">
                                        {/* Enrolled Under */}
                                        <div>
                                            <p className="mb-1 text-xs font-medium tracking-wide text-slate-500 uppercase sm:text-sm">
                                                Enrolled Under:
                                            </p>
                                
                                            <div className="pt-1">
                                                <Badge
                                                    className={`rounded-full px-3 py-4 text-sm font-semibold shadow-sm sm:px-4 sm:py-4 sm:text-lg ${getEnrolledUnderBadgeClass(
                                                        assessmentForm.enrolled_under,
                                                    )}`}
                                                >
                                                    {assessmentForm.enrolled_under}
                                                </Badge>
                                            </div>
                                        </div>
                                
                                        {/* Semester */}
                                        <div>
                                            <p className="mb-1 text-xs font-medium tracking-wide text-slate-500 uppercase sm:text-sm">
                                                Semester
                                            </p>
                                
                                            <div className="pt-1">
                                                <Badge
                                                    className={`rounded-full px-3 py-4 text-sm font-semibold shadow-sm sm:px-4 sm:py-4 sm:text-lg ${getSemesterBadgeClass(
                                                        assessmentForm.semester,
                                                    )}`}
                                                >
                                                    {assessmentForm.semester}
                                                </Badge>
                                            </div>
                                        </div>

                                        {/* Semester */}
                                        <div>
                                            <p className="mb-1 text-xs font-medium tracking-wide text-slate-500 uppercase sm:text-sm">
                                                Semester
                                            </p>
                                
                                            <div className="pt-1">
                                                <Badge
                                                    className={`rounded-full px-3 py-4 text-sm font-semibold shadow-sm sm:px-4 sm:py-4 sm:text-lg bg-slate-100 text-slate-700 border border-slate-500 px-4`}
                                                >
                                                    {assessmentForm.sy_last_attended}
                                                </Badge>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <Separator />
                                
                                {/* Submission Details */}
                                <div className="text-center leading-7 text-slate-600">
                                    <p className="-mt-2 mb-1 text-xs tracking-wide text-slate-500 sm:-mt-4 sm:text-sm">
                                        Submitted on:{' '}
                                        {formatDate(assessmentForm.created_at)}
                                    </p>
                                </div>
                            </CardContent>
                            <CardFooter className="print-hidden no-print flex flex-col gap-4 border-t px-4 pt-6 pb-6 sm:px-6 sm:pb-8">
                                <div className="flex w-full flex-col justify-center gap-3 sm:w-auto sm:flex-row sm:gap-4">
                                    <Button className="h-11 w-full rounded-xl bg-blue-700 px-6 text-sm font-semibold text-white shadow-md transition-all duration-200 hover:bg-blue-800 hover:shadow-lg active:scale-[0.98] sm:h-12 sm:w-auto sm:px-8 sm:text-base">
                                        <Link
                                            href="/public/assessmentform"
                                            className="flex h-full w-full items-center justify-center"
                                        >
                                            Submit Another Request
                                        </Link>
                                    </Button>
                                    <Button
                                        variant="outline"
                                        onClick={handlePrint}
                                        className="h-11 w-full rounded-xl border-blue-200 px-6 text-sm font-semibold text-blue-700 transition-all duration-200 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-800 sm:h-12 sm:w-auto sm:px-8 sm:text-base"
                                    >
                                        <Printer className="mr-2 h-4 w-4 shrink-0 sm:h-5 sm:w-5" />
                                        Print Receipt
                                    </Button>
                                </div>
                            </CardFooter>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

AssessmentSuccess.layout = (page: React.ReactNode) => (
    <PublicLayout>{page}</PublicLayout>
);
