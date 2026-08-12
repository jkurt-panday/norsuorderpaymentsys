// /home/kurt_/norsuorderpaymentsys/resources/js/pages/public/Success.tsx
import { Link } from '@inertiajs/react';
import html2canvas from 'html2canvas';
import jsPDF from 'jspdf';
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
} from 'lucide-react';
import React, { useRef } from 'react';
import { useReactToPrint } from 'react-to-print';
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
}

export default function Success({ reference_number, formInput }: Props) {
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

    // console.log('formInput:', formInput);
    // console.log('supportingDocuments:', formInput?.supporting_documents);
    // console.log('keys:', Object.keys(formInput ?? {}));

    return (
        <>
            <div className="min-h-screen bg-linear-to-b from-blue-500 via-slate-100 to-white py-12">
                <div className="mx-auto max-w-5xl px-6">
                    <div
                        className="print-container container mx-auto max-w-4xl rounded-3xl px-4 py-8"
                        id="print-receipt"
                        ref={receiptRef}
                    >
                        <Card className="print-card overflow-hidden rounded-3xl bg-white shadow-2xl print:shadow-none">
                            <CardHeader className="print-header -mx-6 -mt-6 rounded-t-3xl rounded-b-3xl bg-linear-to-b from-blue-600 to-blue-400 px-8 py-10 text-center text-white">
                                <div className="mx-auto flex items-center justify-center">
                                    <img
                                        src="/finance_logo1.png"
                                        alt="NORSU Logo"
                                        width={500}
                                        height={500}
                                        className="pb-6"
                                    />
                                </div>
                                <div className="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-white/15 backdrop-blur-sm">
                                    <CheckCircle className="h-14 w-14 text-white" />
                                </div>
                                <CardTitle className="text-3xl font-bold text-green-300">
                                    Submission Successful!
                                </CardTitle>
                                <CardDescription className="mt-2 text-blue-100">
                                    Your request has been submitted
                                    successfully.
                                </CardDescription>
                            </CardHeader>

                            <CardContent className="print-content space-y-10 pt-8">
                                {/* Reference Number */}
                                <div className="rounded-2xl border border-blue-100 bg-blue-50 p-8 text-center shadow-sm">
                                    <p className="text-sm text-muted-foreground">
                                        Reference Number
                                    </p>
                                    <p className="font-mono text-4xl font-bold tracking-widest text-blue-700">
                                        {reference_number}
                                    </p>
                                    <p className="text-slate-500">
                                        Please keep this reference number for
                                        tracking your request.
                                    </p>
                                </div>

                                <Separator />

                                {/* Contact Information */}
                                <div>
                                    <h3 className="mb-6 flex items-center gap-3 text-2xl font-semibold text-blue-900">
                                        <User className="h-6 w-6 text-blue-700" />
                                        Contact Information
                                    </h3>
                                    <div className="grid grid-cols-1 gap-x-10 gap-y-6 md:grid-cols-2">
                                        <div className="flex items-start gap-2">
                                            <Mail className="mt-1 h-4 w-4 text-muted-foreground" />
                                            <div>
                                                <p className="mb-1 text-sm font-medium tracking-wide text-slate-500 uppercase">
                                                    Email
                                                </p>
                                                <p className="text-xl font-semibold text-slate-900">
                                                    {formInput.email}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-start gap-2">
                                            <Phone className="mt-1 h-4 w-4 text-muted-foreground" />
                                            <div>
                                                <p className="mb-1 text-sm font-medium tracking-wide text-slate-500 uppercase">
                                                    Contact Number
                                                </p>
                                                <p className="text-xl font-semibold text-slate-900">
                                                    {formInput.contact_num}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <Separator />

                                {/* Identity Details */}
                                <div>
                                    <h3 className="mb-6 flex items-center gap-3 text-2xl font-semibold text-blue-900">
                                        <IdCard className="h-6 w-6 text-blue-700" />
                                        Identity Details
                                    </h3>
                                    <div className="grid grid-cols-1 gap-x-10 gap-y-6 md:grid-cols-2">
                                        <div>
                                            <p className="mb-1 text-sm font-medium tracking-wide text-slate-500 uppercase">
                                                First Name / Office
                                            </p>
                                            <p className="text-xl font-semibold text-slate-900">
                                                {formInput.firstname_or_office}
                                            </p>
                                        </div>
                                        {formInput.middlename_or_project && (
                                            <div>
                                                <p className="mb-1 text-sm font-medium tracking-wide text-slate-500 uppercase">
                                                    Middle Name / Project
                                                </p>
                                                <p className="text-xl font-semibold text-slate-900">
                                                    {
                                                        formInput.middlename_or_project
                                                    }
                                                </p>
                                            </div>
                                        )}
                                        <div>
                                            <p className="mb-1 text-sm font-medium tracking-wide text-slate-500 uppercase">
                                                Last Name / Agency
                                            </p>
                                            <p className="text-xl font-semibold text-slate-900">
                                                {formInput.lastname_or_agency}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="mb-1 text-sm font-medium tracking-wide text-slate-500 uppercase">
                                                Office / College
                                            </p>
                                            <p className="text-xl font-semibold text-slate-900">
                                                {formInput.office_or_college}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="mb-1 text-sm font-medium tracking-wide text-slate-500 uppercase">
                                                Position / Designation
                                            </p>
                                            <p className="text-xl font-semibold text-slate-900">
                                                {
                                                    formInput.position_or_designation
                                                }
                                            </p>
                                        </div>
                                        <div className="col-span-full flex items-start gap-2">
                                            <MapPin className="mt-1 h-4 w-4 text-muted-foreground" />
                                            <div>
                                                <p className="mb-1 text-sm font-medium tracking-wide text-slate-500 uppercase">
                                                    Address
                                                </p>
                                                <p className="text-xl font-semibold text-slate-900">
                                                    {formInput.address}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <Separator />

                                {/* Request Details */}
                                <div>
                                    <h3 className="mb-6 flex items-center gap-3 text-2xl font-semibold text-blue-900">
                                        <Receipt className="h-6 w-6 text-blue-700" />
                                        Request Details
                                    </h3>
                                    <div className="grid grid-cols-1 gap-x-10 gap-y-6 md:grid-cols-2">
                                        <div>
                                            <p className="mb-1 text-sm font-medium tracking-wide text-slate-500 uppercase">
                                                Request Type
                                            </p>
                                            <Badge
                                                className={`rounded-full px-4 py-4 text-lg font-semibold shadow-sm ${getRequestBadgeClass(
                                                    formInput.request_type,
                                                )}`}
                                            >
                                                {formInput.request_type}
                                            </Badge>
                                        </div>
                                        <div>
                                            <p className="mb-1 text-sm font-medium tracking-wide text-slate-500 uppercase">
                                                Amount
                                            </p>
                                            <p className="text-3xl font-bold text-blue-700">
                                                {formatCurrency(
                                                    formInput.amount,
                                                )}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="mb-1 text-sm font-medium tracking-wide text-slate-500 uppercase">
                                                Membership Type
                                            </p>
                                            <p className="text-lg font-semibold text-slate-900">
                                                {
                                                    formInput.membership
                                                        .member_code
                                                }
                                            </p>
                                        </div>
                                        <div>
                                            <p className="mb-1 text-sm font-medium tracking-wide text-slate-500 uppercase">
                                                Payment Details
                                            </p>
                                            <p className="text-lg font-semibold text-slate-900">
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
                                            <h3 className="mb-6 flex items-center gap-3 text-2xl font-semibold text-blue-900">
                                                <FileText className="h-5 w-5" />
                                                Supporting Documents
                                            </h3>
                                            <div className="space-y-2">
                                                {formInput.supporting_documents.map(
                                                    (doc) => (
                                                        <div
                                                            key={doc.id}
                                                            className="flex items-center justify-between rounded-lg bg-blue-400/25 p-3 transition-colors hover:bg-blue-400/40"
                                                        >
                                                            <div className="flex items-center gap-3">
                                                                <FileText className="h-5 w-5 text-muted-foreground" />
                                                                <div>
                                                                    <p className="text-lg font-medium">
                                                                        {
                                                                            doc.original_filename
                                                                        }
                                                                    </p>
                                                                    <p className="text-xs text-muted-foreground">
                                                                        {formatFileSize(
                                                                            doc.file_size,
                                                                        )}{' '}
                                                                        •
                                                                        {
                                                                            doc.mime_type
                                                                        }{' '}
                                                                        •
                                                                        Uploaded:{' '}
                                                                        {formatDate(
                                                                            doc.uploaded_at,
                                                                        )}
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
                                <div className="font-lg text-center leading-7 text-slate-600">
                                    <p className="font-xl -mt-4 mb-1 text-sm tracking-wide text-slate-500">
                                        Submitted on:{' '}
                                        {formatDate(formInput.created_at)}
                                    </p>
                                </div>
                            </CardContent>

                            <CardFooter className="print-hidden no-print flex flex-col gap-4 border-t pt-6">
                                {/*<div className="max-w-2xl text-center text-base leading-7 text-slate-600">
                                    <p>
                                        A confirmation email has been sent to
                                        your email address. Our team will review
                                        your request and contact you shortly.
                                    </p>
                                </div>*/}
                                <div className="flex gap-4">
                                    <Button className="h-12 rounded-xl bg-blue-700 px-8 text-base font-semibold text-white shadow-md transition-all duration-200 hover:bg-blue-800 hover:shadow-lg active:scale-[0.98]">
                                        <Link href="/public/form">
                                            Submit Another Request
                                        </Link>
                                    </Button>
                                    <Button
                                        variant="outline"
                                        onClick={handlePrint}
                                        className="h-12 rounded-xl border-blue-200 px-8 text-base font-semibold text-blue-700 transition-all duration-200 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-800"
                                    >
                                        <Printer className="mr-2 h-5 w-5" />
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
