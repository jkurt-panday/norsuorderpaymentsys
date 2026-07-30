// /home/kurt_/norsuorderpaymentsys/resources/js/pages/public/Success.tsx

import React from 'react';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
    CardFooter,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Badge } from '@/components/ui/badge';
import { Link } from '@inertiajs/react';
import PublicLayout from '@/pages/layouts/PublicLayout';
import {
    CheckCircle,
    Download,
    FileText,
    Mail,
    Phone,
    MapPin,
    User,
    Building,
    Hash,
    CreditCard,
    Receipt,
    Printer,
} from 'lucide-react';

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
    paymentDetailOption: PaymentOption; // was payment_detail_option
    supportingDocuments: SupportingDocument[];
    created_at: string;
}

interface Props {
    reference_number?: string;
    formInput: FormInput;
}

export default function Success({ reference_number, formInput }: Props) {
    // Fall back to formInput.reference_number if the top-level prop wasn't
    // passed by the controller — this is why the reference number box was
    // rendering blank.
    const displayReferenceNumber = reference_number || formInput.reference_number;

    const formatDate = (date: string) => {
        return new Date(date).toLocaleDateString('en-PH', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const formatCurrency = (amount: string) => {
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP',
        }).format(parseFloat(amount));
    };

    const formatFileSize = (bytes: number) => {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    // Opens the browser's native print dialog. From there the user can print
    // physically, or choose "Save as PDF" (available on virtually every
    // modern OS/browser print dialog) to download a copy of this record.
    const handlePrint = () => {
        window.print();
    };

    return (
        <div className="container mx-auto max-w-4xl px-4 py-8 print:max-w-none print:p-0">
            <style>{`
                @media print {
                    @page {
                        size: auto;
                        margin: 0.35in;
                    }
                    * {
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                        color-adjust: exact !important;
                    }
                    body * {
                        visibility: hidden;
                    }
                    #success-print-area,
                    #success-print-area * {
                        visibility: visible;
                    }
                    #success-print-area {
                        position: absolute;
                        left: 0;
                        top: 0;
                        width: 100%;
                    }
                    /* Everything below shrinks the on-screen sizing down to
                       something that actually fits a single printed page —
                       without this, the normal text-3xl headings, p-6
                       padding, and space-y-6 gaps overflow onto a second
                       page even though the content itself is short. */
                    #success-print-area {
                        font-size: 13px !important;
                        line-height: 1.4 !important;
                    }
                    #success-print-area h1,
                    #success-print-area h2,
                    #success-print-area h3 {
                        font-size: 14.5px !important;
                        margin-bottom: 5px !important;
                    }
                    #success-print-area .text-2xl { font-size: 19px !important; }
                    #success-print-area .text-xl  { font-size: 15px !important; }
                    #success-print-area .text-lg  { font-size: 14px !important; }
                    #success-print-area .text-sm  { font-size: 12px !important; }
                    #success-print-area .text-xs  { font-size: 10.5px !important; }
                    #success-print-area .h-5.w-5   { height: 14px !important; width: 14px !important; }
                    #success-print-area .h-4.w-4   { height: 12px !important; width: 12px !important; }
                    #success-print-area .p-6  { padding: 6px !important; }
                    #success-print-area .p-4  { padding: 5px !important; }
                    #success-print-area .p-3  { padding: 4px !important; }
                    #success-print-area .pt-6 { padding-top: 6px !important; }
                    #success-print-area .space-y-6 > * + * { margin-top: 6px !important; }
                    #success-print-area .space-y-2 > * + * { margin-top: 2px !important; }
                    #success-print-area .gap-4 { gap: 4px !important; }
                    #success-print-area .gap-2 { gap: 3px !important; }
                    #success-print-area .mb-4  { margin-bottom: 4px !important; }
                    #success-print-area .mb-3  { margin-bottom: 3px !important; }
                    #success-print-area .mb-1  { margin-bottom: 1px !important; }
                    #success-print-area .mt-1  { margin-top: 1px !important; }
                    #success-print-area .rounded-lg,
                    #success-print-area .rounded-full {
                        padding-top: 3px !important;
                        padding-bottom: 3px !important;
                    }
                    /* md:grid-cols-2 is a viewport-width breakpoint (≥768px).
                       The printed page's usable width (Letter minus margins)
                       renders narrower than that, so the breakpoint never
                       activates and the grid silently collapses to a single
                       stacked column. Force two columns unconditionally
                       during print so it matches the on-screen layout. */
                    #success-print-area .grid {
                        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                    }
                    #success-print-area .col-span-full {
                        grid-column: 1 / -1 !important;
                    }
                }
            `}</style>

            <div id="success-print-area">
                <Card className="border-green-200 shadow-lg print:border-0 print:shadow-none">
                    <CardHeader className="border-b border-green-200 bg-green-50 text-center print:hidden">
                        <div className="mb-4 flex justify-center">
                            <CheckCircle className="h-16 w-16 text-green-500" />
                        </div>
                        <CardTitle className="text-3xl font-bold text-green-700">
                            Submission Successful!
                        </CardTitle>
                        <CardDescription className="text-lg">
                            Your request has been submitted successfully.
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="space-y-6 pt-6">
                        {/* Reference Number */}
                        <div className="rounded-lg bg-muted/50 p-4 text-center print:break-inside-avoid">
                            <p className="text-sm text-muted-foreground">
                                Reference Number
                            </p>
                            <p className="font-mono text-2xl font-bold text-primary">
                                {displayReferenceNumber}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground print:hidden">
                                Please keep this reference number for tracking your
                                request.
                            </p>
                        </div>

                        <Separator />

                        {/* Contact Information */}
                        <div className="print:break-inside-avoid">
                            <h3 className="mb-3 flex items-center gap-2 text-lg font-semibold">
                                <User className="h-5 w-5" />
                                Contact Information
                            </h3>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="flex items-start gap-2">
                                    <Mail className="mt-1 h-4 w-4 text-muted-foreground" />
                                    <div>
                                        <p className="text-sm text-muted-foreground">
                                            Email
                                        </p>
                                        <p className="font-medium">
                                            {formInput.email}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-start gap-2">
                                    <Phone className="mt-1 h-4 w-4 text-muted-foreground" />
                                    <div>
                                        <p className="text-sm text-muted-foreground">
                                            Contact Number
                                        </p>
                                        <p className="font-medium">
                                            {formInput.contact_num}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <Separator />

                        {/* Identity Details */}
                        <div className="print:break-inside-avoid">
                            <h3 className="mb-3 flex items-center gap-2 text-lg font-semibold">
                                <Building className="h-5 w-5" />
                                Identity Details
                            </h3>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        First Name / Office
                                    </p>
                                    <p className="font-medium">
                                        {formInput.firstname_or_office}
                                    </p>
                                </div>
                                {formInput.middlename_or_project && (
                                    <div>
                                        <p className="text-sm text-muted-foreground">
                                            Middle Name / Project
                                        </p>
                                        <p className="font-medium">
                                            {formInput.middlename_or_project}
                                        </p>
                                    </div>
                                )}
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        Last Name / Agency
                                    </p>
                                    <p className="font-medium">
                                        {formInput.lastname_or_agency}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        Office / College
                                    </p>
                                    <p className="font-medium">
                                        {formInput.office_or_college}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        Position / Designation
                                    </p>
                                    <p className="font-medium">
                                        {formInput.position_or_designation}
                                    </p>
                                </div>
                                <div className="col-span-full flex items-start gap-2">
                                    <MapPin className="mt-1 h-4 w-4 text-muted-foreground" />
                                    <div>
                                        <p className="text-sm text-muted-foreground">
                                            Address
                                        </p>
                                        <p className="font-medium">
                                            {formInput.address}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <Separator />

                        {/* Request Details */}
                        <div className="print:break-inside-avoid">
                            <h3 className="mb-3 flex items-center gap-2 text-lg font-semibold">
                                <Receipt className="h-5 w-5" />
                                Request Details
                            </h3>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        Request Type
                                    </p>
                                    <Badge variant="secondary" className="mt-1">
                                        {formInput.request_type}
                                    </Badge>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        Amount
                                    </p>
                                    <p className="text-xl font-bold text-primary print:text-black">
                                        {formatCurrency(formInput.amount)}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        Membership Type
                                    </p>
                                    <p className="font-medium">
                                        {formInput.membership.member_code}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        Payment Details
                                    </p>
                                    <p className="font-medium">
                                         {formInput.paymentDetailOption?.payment_desc || 'N/A'}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <Separator />

                        {/* Supporting Documents */}
                        {formInput.supportingDocuments &&
                            formInput.supportingDocuments.length > 0 && (
                                <div className="print:break-inside-avoid">
                                    <h3 className="mb-3 flex items-center gap-2 text-lg font-semibold">
                                        <FileText className="h-5 w-5" />
                                        Supporting Documents
                                    </h3>
                                    <div className="space-y-2">
                                        {formInput.supportingDocuments.map(
                                            (doc) => (
                                                <div
                                                    key={doc.id}
                                                    className="flex items-center justify-between rounded-lg bg-muted/30 p-3 transition-colors hover:bg-muted/50 print:break-inside-avoid"
                                                >
                                                    <div className="flex items-center gap-3">
                                                        <FileText className="h-5 w-5 text-muted-foreground" />
                                                        <div>
                                                            <p className="text-sm font-medium">
                                                                {
                                                                    doc.original_filename
                                                                }
                                                            </p>
                                                            <p className="text-xs text-muted-foreground print:hidden">
                                                                {formatFileSize(
                                                                    doc.file_size,
                                                                )}{' '}
                                                                •{doc.mime_type} •
                                                                Uploaded:{' '}
                                                                {formatDate(
                                                                    doc.uploaded_at,
                                                                )}
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="print:hidden"
                                                        onClick={() =>
                                                            window.open(
                                                                doc.file_url,
                                                                '_blank',
                                                            )
                                                        }
                                                    >
                                                        <Download className="mr-1 h-4 w-4" />
                                                        View
                                                    </Button>
                                                </div>
                                            ),
                                        )}
                                    </div>
                                </div>
                            )}

                        <Separator />

                        {/* Submission Details */}
                        <div className="text-sm text-muted-foreground print:break-inside-avoid">
                            <p>Submitted on: {formatDate(formInput.created_at)}</p>
                        </div>
                    </CardContent>

                    <CardFooter className="flex flex-col gap-4 border-t pt-6 print:hidden">
                        <div className="max-w-xl text-center text-sm text-muted-foreground">
                            <p>
                                A confirmation email has been sent to your email
                                address. Our team will review your request and
                                contact you shortly.
                            </p>
                        </div>
                        <div className="flex flex-wrap justify-center gap-4">
                            <Button variant="secondary" onClick={handlePrint}>
                                <Printer className="mr-2 h-4 w-4" />
                                Print / Save as PDF
                            </Button>
                            <Button asChild variant="outline">
                                <Link href="/public/submit">
                                    Submit Another Request
                                </Link>
                            </Button>
                            <Button asChild>
                                <Link href="/">Go to Home</Link>
                            </Button>
                        </div>
                    </CardFooter>
                </Card>
            </div>
        </div>
    );
}

Success.layout = (page: React.ReactNode) => <PublicLayout>{page}</PublicLayout>;