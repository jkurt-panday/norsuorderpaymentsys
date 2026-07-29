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
    payment_detail_option: PaymentOption;
    supportingDocuments: SupportingDocument[];
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
        }).format(parseFloat(amount));
    };

    // Format file size
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
    // The print:hidden classes below hide interactive/irrelevant UI
    // (buttons, action links) so the printed/saved output only shows the
    // actual transcript of the submission.
    const handlePrint = () => {
        window.print();
    };

    return (
        <div className="container mx-auto max-w-4xl px-4 py-8">
            {/* Print-only styles: tidy up the printed page by removing shadows/
                borders that only make sense on-screen, and ensure the card
                isn't clipped to a fixed height when content overflows. */}
            <style>{`
                @media print {
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
                }
            `}</style>

            <div id="success-print-area">
                <Card className="border-green-200 shadow-lg print:border-0 print:shadow-none">
                    <CardHeader className="border-b border-green-200 bg-green-50 text-center print:bg-white">
                        <div className="mb-4 flex justify-center print:hidden">
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
                        <div className="rounded-lg bg-muted/50 p-4 text-center">
                            <p className="text-sm text-muted-foreground">
                                Reference Number
                            </p>
                            <p className="font-mono text-2xl font-bold text-primary">
                                {reference_number}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Please keep this reference number for tracking your
                                request.
                            </p>
                        </div>

                        <Separator />

                        {/* Contact Information */}
                        <div>
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
                        <div>
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
                        <div>
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
                                    <p className="text-xl font-bold text-primary">
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
                                        {formInput.payment_detail_option
                                            ?.payment_desc || 'N/A'}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <Separator />

                        {/* Supporting Documents */}
                        {formInput.supportingDocuments &&
                            formInput.supportingDocuments.length > 0 && (
                                <div>
                                    <h3 className="mb-3 flex items-center gap-2 text-lg font-semibold">
                                        <FileText className="h-5 w-5" />
                                        Supporting Documents
                                    </h3>
                                    <div className="space-y-2">
                                        {formInput.supportingDocuments.map(
                                            (doc) => (
                                                <div
                                                    key={doc.id}
                                                    className="flex items-center justify-between rounded-lg bg-muted/30 p-3 transition-colors hover:bg-muted/50"
                                                >
                                                    <div className="flex items-center gap-3">
                                                        <FileText className="h-5 w-5 text-muted-foreground" />
                                                        <div>
                                                            <p className="text-sm font-medium">
                                                                {
                                                                    doc.original_filename
                                                                }
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
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
                        <div className="text-sm text-muted-foreground">
                            <p>Submitted on: {formatDate(formInput.created_at)}</p>
                        </div>
                    </CardContent>

                    <CardFooter className="flex flex-col gap-4 border-t pt-6">
                        <div className="max-w-xl text-center text-sm text-muted-foreground">
                            <p>
                                A confirmation email has been sent to your email
                                address. Our team will review your request and
                                contact you shortly.
                            </p>
                        </div>
                        <div className="flex flex-wrap justify-center gap-4 print:hidden">
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