// /home/kurt_/norsuorderpaymentsys/resources/js/pages/public/Success.tsx
import { Link, usePage } from '@inertiajs/react';
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
    Download,
    Home
} from 'lucide-react';
import React, { useEffect, useRef } from 'react';
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
import { setExpiringLocalStorage } from '@/lib/expiring-local-storage';
import PublicLayout from '@/pages/layouts/PublicLayout';

interface SupportingDocument {
    id: number;
    original_filename: string;
    file_url: string;
    file_size: number;
    mime_type: string;
    uploaded_at: string;
}

interface Membership {
    id: number;
    member_code: string;
}

interface PaymentOption {
    id: number;
    payment_desc: string;
}

interface Course {
    id: number | string;
    course_code: string;
    course_desc: string;
}

interface AcademicTerm {
    id: number | string;
    school_year: string;
    semester: string;
}

interface FormInput {
    id: number;
    reference_number: string;
    email: string;
    contact_num: string;
    firstname_or_office: string;
    middlename_or_project: string | null;
    lastname_or_agency: string;
    office_or_college: string;
    position_or_designation: string;
    address: string;
    amount: string;
    request_type: string;
    membership: Membership;
    payment_detail_option: PaymentOption;
    submitted_student_number?: string | null;
    course_id?: number | string | null;
    course?: Course | null;
    academic_term?: number | string | null;
    supporting_documents: SupportingDocument[];
    created_at: string;
}

interface Props {
    reference_number: string;
    formInput: FormInput;
    prefillAcademicTerm?: Pick<AcademicTerm, 'school_year' | 'semester'> | null;
    dashboardUrl?: string | null;
}

export default function Success({
    reference_number,
    formInput,
    prefillAcademicTerm,
}: Props) {
    const { auth } = usePage<any>().props;

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

    // Format currency
    const formatCurrency = (amount: string) => {
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP',
        })
            .format(parseFloat(amount))
            .replace('₱', '₱  ');
    };

    const formatContactNumber = (contactNum: string) => {
        if (!contactNum) {
return '';
}

        return contactNum.replace(/^(\d{4})(\d{3})(\d{4})$/, '$1 $2 $3');
    };

    // Format file size
    const formatFileSize = (bytes: number) => {
        if (bytes === 0) {
            return '0 Bytes';
        }

        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));

        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    const getRequestBadgeClass = (requestType: string) => {
        switch (requestType) {
            case 'New Request':
                return 'bg-emerald-100 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 px-4';

            case 'Re-issue Request':
                return 'bg-blue-100 text-blue-700 border border-blue-200 hover:bg-blue-100 px-4';

            case 'Other':
                return 'bg-amber-100 text-amber-700 border border-amber-200 hover:bg-amber-100 px-4';

            default:
                return 'bg-slate-100 text-foreground border border-border px-4';
        }
    };

    // ? for printing
    const receiptRef = useRef<HTMLDivElement>(null);

    const handlePrint = () => {
        window.open(
            `/public/success/${formInput.reference_number}/print`,
            '_blank',
        );
    };

    const handleDownload = () => {
        const link = document.createElement('a');
        link.href = `/public/success/${formInput.reference_number}/download`;
        link.download = `OP-Transaction-${formInput.reference_number}.pdf`;
        document.body.appendChild(link);
        link.click();
        link.remove();
    };

    useEffect(() => {
        setExpiringLocalStorage('submit-form-prefill', {
            email: formInput.email ?? '',
            contact_num: formInput.contact_num ?? '',
            firstname_or_office: formInput.firstname_or_office ?? '',
            middlename_or_project: formInput.middlename_or_project ?? '',
            lastname_or_agency: formInput.lastname_or_agency ?? '',
            office_or_college: formInput.office_or_college ?? '',
            position_or_designation: formInput.position_or_designation ?? '',
            address: formInput.address ?? '',
            student_num: formInput.submitted_student_number ?? '',
            course_id: formInput.course_id ? String(formInput.course_id) : '',
            school_year: prefillAcademicTerm?.school_year ?? '',
            semester: prefillAcademicTerm?.semester ?? '',
        });
    }, [formInput, prefillAcademicTerm]);

    return (
        <>
            <div className="min-h-screen bg-linear-to-b from-blue-500 via-slate-100 to-white py-6 sm:py-12">
                <div className="mx-auto max-w-5xl px-3 sm:px-6">
                    <div
                        className="print-container container mx-auto max-w-4xl rounded-2xl px-0 py-4 sm:rounded-3xl sm:px-4 sm:py-8"
                        id="print-receipt"
                        ref={receiptRef}
                    >
                        <Card className="print-card overflow-hidden rounded-2xl bg-card shadow-xl sm:rounded-3xl sm:shadow-2xl print:shadow-none">
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
                                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-card/15 backdrop-blur-sm sm:h-24 sm:w-24">
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
                                        {reference_number}
                                    </p>
                                    <p className="text-xs text-muted-foreground sm:text-sm">
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
                                                <p className="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase sm:text-sm">
                                                    Email
                                                </p>
                                                <p className="text-base font-semibold break-all text-foreground sm:text-xl">
                                                    {formInput.email}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-start gap-2">
                                            <Phone className="mt-1 h-4 w-4 shrink-0 text-muted-foreground" />
                                            <div className="min-w-0 flex-1">
                                                <p className="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase sm:text-sm">
                                                    Contact Number
                                                </p>
                                                <p className="text-base font-semibold break-all text-foreground sm:text-xl">
                                                    {formatContactNumber(
                                                        formInput.contact_num,
                                                    )}
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
                                        {/* Full Name / Office */}
                                        <div className="col-span-full">
                                            <p className="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase sm:text-sm">
                                                Full Name / Agency / Office /
                                                Project
                                            </p>
                                            <p className="text-base font-semibold wrap-break-word text-foreground sm:text-xl">
                                                {[
                                                    formInput.lastname_or_agency
                                                        ? `${formInput.lastname_or_agency},`
                                                        : null,
                                                    formInput.firstname_or_office,
                                                    formInput.middlename_or_project,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' ')}
                                            </p>
                                        </div>

                                        {/* Office / College */}
                                        <div>
                                            <p className="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase sm:text-sm">
                                                Office / College
                                            </p>
                                            <p className="text-base font-semibold wrap-break-word text-foreground sm:text-xl">
                                                {formInput.office_or_college}
                                            </p>
                                        </div>

                                        {/* Position / Designation */}
                                        <div>
                                            <p className="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase sm:text-sm">
                                                Position / Designation
                                            </p>
                                            <p className="text-base font-semibold wrap-break-word text-foreground sm:text-xl">
                                                {
                                                    formInput.position_or_designation
                                                }
                                            </p>
                                        </div>

                                        {/* Address */}
                                        <div className="col-span-full flex items-start gap-2">
                                            <MapPin className="mt-1 h-4 w-4 shrink-0 text-muted-foreground" />
                                            <div className="min-w-0 flex-1">
                                                <p className="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase sm:text-sm">
                                                    Address
                                                </p>
                                                <p className="text-base font-semibold wrap-break-word text-foreground sm:text-xl">
                                                    {formInput.address}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <Separator />

                                {/* Request Details */}
                                <div>
                                    <h3 className="mb-4 flex items-center gap-2 text-xl font-semibold text-blue-900 sm:mb-6 sm:gap-3 sm:text-2xl">
                                        <Receipt className="h-5 w-5 shrink-0 text-blue-700 sm:h-6 sm:w-6" />
                                        Request Details
                                    </h3>
                                    <div className="grid grid-cols-1 gap-x-10 gap-y-4 sm:gap-y-6 md:grid-cols-2">
                                        <div>
                                            <p className="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase sm:text-sm">
                                                Request Type
                                            </p>
                                            <div className="pt-1">
                                                <Badge
                                                    className={`rounded-full px-3 py-4 text-sm font-semibold shadow-sm sm:px-4 sm:py-4 sm:text-lg ${getRequestBadgeClass(
                                                        formInput.request_type,
                                                    )}`}
                                                >
                                                    {formInput.request_type}
                                                </Badge>
                                            </div>
                                        </div>
                                        <div>
                                            <p className="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase sm:text-sm">
                                                Amount
                                            </p>
                                            <p className="text-2xl font-bold text-blue-700 sm:text-3xl">
                                                {formatCurrency(
                                                    formInput.amount,
                                                )}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase sm:text-sm">
                                                Membership Type
                                            </p>
                                            <p className="text-base font-semibold wrap-break-word text-foreground sm:text-lg">
                                                {
                                                    formInput.membership
                                                        .member_code
                                                }
                                            </p>
                                        </div>
                                        <div>
                                            <p className="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase sm:text-sm">
                                                Payment Details
                                            </p>
                                            <p className="text-base font-semibold wrap-break-word text-foreground sm:text-lg">
                                                {formInput.payment_detail_option
                                                    ?.payment_desc || 'N/A'}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <Separator />

                                {/* Supporting Documents */}
                                {formInput.supporting_documents &&
                                    formInput.supporting_documents.length >
                                        0 && (
                                        <div>
                                            <h3 className="mb-4 flex items-center gap-2 text-xl font-semibold text-blue-900 sm:mb-6 sm:gap-3 sm:text-2xl">
                                                <FileText className="h-5 w-5 shrink-0" />
                                                Supporting Documents
                                            </h3>
                                            <div className="space-y-2">
                                                {formInput.supporting_documents.map(
                                                    (doc) => (
                                                        <div
                                                            key={doc.id}
                                                            className="flex flex-col justify-between gap-2 rounded-lg bg-blue-400/25 p-3 transition-colors hover:bg-blue-400/40 sm:flex-row sm:items-center"
                                                        >
                                                            <div className="flex min-w-0 items-start gap-3 sm:items-center">
                                                                <FileText className="mt-1 h-5 w-5 shrink-0 text-muted-foreground sm:mt-0" />
                                                                <div className="min-w-0 flex-1">
                                                                    <p className="truncate text-sm font-medium sm:text-lg">
                                                                        {
                                                                            doc.original_filename
                                                                        }
                                                                    </p>
                                                                    <p className="flex flex-wrap gap-x-1.5 gap-y-0.5 text-xs text-muted-foreground">
                                                                        <span>
                                                                            {formatFileSize(
                                                                                doc.file_size,
                                                                            )}
                                                                        </span>
                                                                        <span>
                                                                            •
                                                                        </span>
                                                                        <span>
                                                                            {
                                                                                doc.mime_type
                                                                            }
                                                                        </span>
                                                                        <span>
                                                                            •
                                                                        </span>
                                                                        <span>
                                                                            Uploaded:{' '}
                                                                            {formatDate(
                                                                                doc.uploaded_at,
                                                                            )}
                                                                        </span>
                                                                    </p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                    )}

                                <Separator />

                                {/* Submission Details */}
                                <div className="text-center leading-7 text-muted-foreground">
                                    <p className="-mt-2 mb-1 text-xs tracking-wide text-muted-foreground sm:-mt-4 sm:text-sm">
                                        Submitted on:{' '}
                                        {formatDate(formInput.created_at)}
                                    </p>
                                </div>
                            </CardContent>

                            <CardFooter className="print-hidden no-print flex flex-col gap-6 border-t px-4 pt-6 pb-6 sm:px-6 sm:pb-8">
                                <div className="grid w-full max-w-2xl grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4">
                                    <Link
                                        href="/public/opform"
                                        className="inline-flex h-11 w-full items-center justify-center rounded-xl bg-blue-600 px-6 text-sm font-semibold text-white shadow-md transition-all duration-200 hover:bg-blue-400 hover:text-white hover:shadow-lg active:scale-[0.98] sm:h-12 sm:px-8 sm:text-base"
                                    >
                                        Submit Another Request
                                    </Link>
                                    <Link
                                        href="/public/assessmentform"
                                        className="inline-flex h-11 w-full items-center justify-center rounded-xl bg-blue-600 px-6 text-sm font-semibold text-white shadow-md transition-all duration-200 hover:bg-blue-400 hover:text-white hover:shadow-lg active:scale-[0.98] sm:h-12 sm:px-8 sm:text-base"
                                    >
                                        Submit Assessment Request
                                    </Link>
                                      <Button
                                          variant="outline"
                                          onClick={handleDownload}
                                         className="h-11 w-full rounded-xl border-blue-200 px-6 text-sm font-semibold text-blue-700 transition-all duration-200 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-800 sm:h-12 sm:px-8 sm:text-base"
                                     >
                                         <Download className="mr-2 h-4 w-4 shrink-0 sm:h-5 sm:w-5" />
                                         Download Transaction
                                     </Button>
                                     <Button
                                         variant="outline"
                                         onClick={handlePrint}
                                        className="h-11 w-full rounded-xl border-blue-200 px-6 text-sm font-semibold text-blue-700 transition-all duration-200 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-800 sm:h-12 sm:px-8 sm:text-base"
                                    >
                                        <Printer className="mr-2 h-4 w-4 shrink-0 sm:h-5 sm:w-5" />
                                        Print Transaction
                                    </Button>
                                    {(() => {
                                        const role = (auth?.user as { role?: string } | undefined)?.role;
                                        const homeHref =
                                            role === 'admin'
                                                ? '/admin/dashboard'
                                                : role === 'staff' || role === 'cashier'
                                                  ? '/staff/staffdashboard'
                                                  : role === 'client'
                                                    ? '/client/dashboard'
                                                    : null;

                                        if (!homeHref) {
                                            return null;
                                        }

                                        return (
                                            <Link
                                                href={homeHref}
                                                className="inline-flex h-11 w-full items-center justify-center rounded-xl border border-border px-6 text-sm font-semibold text-foreground transition-all duration-200 hover:bg-slate-50 sm:h-12 sm:px-8 sm:text-base"
                                            >
                                                <Home className="mr-2 h-4 w-4 shrink-0 sm:h-5 sm:w-5" />
                                                Go back to Home
                                            </Link>
                                        );
                                    })()}
                                </div>
                            </CardFooter>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

Success.layout = (page: React.ReactNode) => <PublicLayout>{page}</PublicLayout>;
