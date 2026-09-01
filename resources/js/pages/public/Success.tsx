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
    Home,
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
    supporting_documents: SupportingDocument[];
    created_at: string;
}

interface Props {
    reference_number: string;
    formInput: FormInput;
    dashboardUrl?: string | null;
}

export default function Success({
    reference_number,
    formInput,
    dashboardUrl,
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
        if (!contactNum) return '';

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
                return 'bg-slate-100 text-slate-700 border border-slate-200 px-4';
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
                                        {reference_number}
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
                                                    {formInput.email}
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
                                            <p className="mb-1 text-xs font-medium tracking-wide text-slate-500 uppercase sm:text-sm">
                                                Full Name / Agency / Office /
                                                Project
                                            </p>
                                            <p className="text-base font-semibold wrap-break-word text-slate-900 sm:text-xl">
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
                                            <p className="mb-1 text-xs font-medium tracking-wide text-slate-500 uppercase sm:text-sm">
                                                Office / College
                                            </p>
                                            <p className="text-base font-semibold wrap-break-word text-slate-900 sm:text-xl">
                                                {formInput.office_or_college}
                                            </p>
                                        </div>

                                        {/* Position / Designation */}
                                        <div>
                                            <p className="mb-1 text-xs font-medium tracking-wide text-slate-500 uppercase sm:text-sm">
                                                Position / Designation
                                            </p>
                                            <p className="text-base font-semibold wrap-break-word text-slate-900 sm:text-xl">
                                                {
                                                    formInput.position_or_designation
                                                }
                                            </p>
                                        </div>

                                        {/* Address */}
                                        <div className="col-span-full flex items-start gap-2">
                                            <MapPin className="mt-1 h-4 w-4 shrink-0 text-muted-foreground" />
                                            <div className="min-w-0 flex-1">
                                                <p className="mb-1 text-xs font-medium tracking-wide text-slate-500 uppercase sm:text-sm">
                                                    Address
                                                </p>
                                                <p className="text-base font-semibold wrap-break-word text-slate-900 sm:text-xl">
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
                                            <p className="mb-1 text-xs font-medium tracking-wide text-slate-500 uppercase sm:text-sm">
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
                                            <p className="mb-1 text-xs font-medium tracking-wide text-slate-500 uppercase sm:text-sm">
                                                Amount
                                            </p>
                                            <p className="text-2xl font-bold text-blue-700 sm:text-3xl">
                                                {formatCurrency(
                                                    formInput.amount,
                                                )}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="mb-1 text-xs font-medium tracking-wide text-slate-500 uppercase sm:text-sm">
                                                Membership Type
                                            </p>
                                            <p className="text-base font-semibold wrap-break-word text-slate-900 sm:text-lg">
                                                {
                                                    formInput.membership
                                                        .member_code
                                                }
                                            </p>
                                        </div>
                                        <div>
                                            <p className="mb-1 text-xs font-medium tracking-wide text-slate-500 uppercase sm:text-sm">
                                                Payment Details
                                            </p>
                                            <p className="text-base font-semibold wrap-break-word text-slate-900 sm:text-lg">
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
                                <div className="text-center leading-7 text-slate-600">
                                    <p className="-mt-2 mb-1 text-xs tracking-wide text-slate-500 sm:-mt-4 sm:text-sm">
                                        Submitted on:{' '}
                                        {formatDate(formInput.created_at)}
                                    </p>
                                </div>
                            </CardContent>

                            <CardFooter className="print-hidden no-print flex flex-col gap-6 border-t px-4 pt-6 pb-6 sm:px-6 sm:pb-8">
                                {!auth?.user && (
                                    <div className="flex w-full flex-col items-center justify-between gap-4 rounded-2xl border border-blue-200/80 bg-gradient-to-r from-blue-50 to-indigo-50 p-4 shadow-sm sm:p-5 md:flex-row">
                                        <div className="flex items-start gap-3.5 text-left">
                                            <div className="shrink-0 rounded-xl border border-blue-100 bg-white p-2.5 text-blue-600 shadow-sm">
                                                <svg
                                                    className="h-6 w-6"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        fill="#4285F4"
                                                        d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                                                    />
                                                    <path
                                                        fill="#34A853"
                                                        d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                                                    />
                                                    <path
                                                        fill="#FBBC05"
                                                        d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"
                                                    />
                                                    <path
                                                        fill="#EA4335"
                                                        d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"
                                                    />
                                                </svg>
                                            </div>
                                            <div>
                                                <h4 className="text-base font-bold text-slate-800">
                                                    Save your information for
                                                    next time?
                                                </h4>
                                                <p className="mt-0.5 text-xs text-slate-600 sm:text-sm">
                                                    Sign in with Google to save
                                                    your contact details,
                                                    office, designation, and
                                                    address so you won't need to
                                                    re-enter them on future
                                                    requests!
                                                </p>
                                            </div>
                                        </div>
                                        <Button
                                            asChild
                                            className="flex h-11 w-full shrink-0 items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-5 text-sm font-semibold text-slate-700 shadow-sm transition-all hover:bg-slate-50 md:w-auto"
                                        >
                                            <a
                                                href={`/auth/google?ref=${formInput.reference_number}`}
                                            >
                                                Save Info with Google
                                            </a>
                                        </Button>
                                    </div>
                                )}

                                <div className="flex w-full flex-col justify-center gap-3 sm:w-auto sm:flex-row sm:gap-4">
                                    {auth?.user && dashboardUrl && (
                                        <Button
                                            asChild
                                            className="h-11 w-full rounded-xl bg-slate-800 px-6 text-sm font-semibold text-white shadow-md transition-all duration-200 hover:bg-slate-900 hover:shadow-lg active:scale-[0.98] sm:h-12 sm:w-auto sm:px-8 sm:text-base"
                                        >
                                            <Link href={dashboardUrl}>
                                                Go back to Dashboard
                                            </Link>
                                        </Button>
                                    )}
                                    <Button
                                        asChild
                                        className="h-11 w-full rounded-xl bg-blue-700 px-6 text-sm font-semibold text-white shadow-md transition-all duration-200 hover:bg-blue-800 hover:shadow-lg active:scale-[0.98] sm:h-12 sm:w-auto sm:px-8 sm:text-base"
                                    >
                                        <Link href="/public/opform">
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

Success.layout = (page: React.ReactNode) => <PublicLayout>{page}</PublicLayout>;
