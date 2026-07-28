// resources/js/pages/public/Success.tsx
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { CheckCircle } from '@untitledui/icons';
import { route } from 'ziggy-js';
import { router } from '@inertiajs/react';
// import { format } from 'date-fns';

interface SupportingDocument {
    id: number;
    original_filename: string;
    file_size: number;
    mime_type: string;
    uploaded_at: string;
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
    membership: {
        member_code: string;
    };
    paymentDetailOption: {
        payment_desc: string;
    };
    supporting_documents: SupportingDocument[];
    created_at: string;
}

interface Props {
    referenceNumber: string;
    formInput: FormInput;
}

export default function Success({ referenceNumber, formInput }: Props) {
    // Format file size
    const getReadableFileSize = (bytes: number) => {
        if (bytes === 0) return '0 KB';
        const suffixes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return (
            Math.round((bytes / Math.pow(1024, i)) * 100) / 100 +
            ' ' +
            suffixes[i]
        );
    };

    // Format date without date-fns
    const formatDate = (dateString: string) => {
        const date = new Date(dateString);
        const options: Intl.DateTimeFormatOptions = {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        };
        return date.toLocaleDateString('en-US', options);
    };

    const goHome = () => {
        router.visit('/');
    };

    const downloadDocument = (documentId: number) => {
        router.visit(`/api/supporting-documents/${documentId}/download`, {
            method: 'get',
        });
    };

    // Add loading/error state
    if (!formInput) {
        return (
            <div className="min-h-screen bg-gray-50 px-4 py-12 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-4xl text-center">
                    <h1 className="text-2xl font-bold text-gray-900">
                        Submission Successful!
                    </h1>
                    <p className="mt-4 text-gray-600">
                        Reference Number: <strong>{referenceNumber}</strong>
                    </p>
                    <p className="mt-2 text-gray-500">
                        Loading submission details...
                    </p>
                    <Button
                        onClick={() => router.visit('/public/form')}
                        className="mt-6"
                    >
                        Submit Another Request
                    </Button>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-gray-50 px-4 py-12 sm:px-6 lg:px-8">
            <div className="mx-auto max-w-4xl">
                {/* Success Header */}
                <div className="mb-8 text-center">
                    <div className="mb-4 flex justify-center">
                        <div className="rounded-full bg-green-100 p-4">
                            <CheckCircle className="h-16 w-16 stroke-[1.5] text-green-600" />
                        </div>
                    </div>
                    <h1 className="text-3xl font-bold text-gray-900">
                        Submission Successful!
                    </h1>
                    <p className="mt-2 text-gray-600">
                        Your request has been submitted successfully.
                    </p>
                    <div className="mt-4 inline-block rounded-lg bg-blue-50 px-6 py-3">
                        <p className="text-sm text-gray-600">
                            Reference Number
                        </p>
                        <p className="font-mono text-2xl font-bold text-blue-600">
                            {referenceNumber}
                        </p>
                    </div>
                </div>

                {/* Form Data */}
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle>Submission Details</CardTitle>
                        <CardDescription>
                            Submitted on {formatDate(formInput.created_at)}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {/* Personal Information */}
                        <div>
                            <h3 className="mb-3 text-lg font-semibold">
                                Personal Information
                            </h3>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <p className="text-sm text-gray-500">
                                        Full Name
                                    </p>
                                    <p className="font-medium">
                                        {formInput.firstname_or_office}{' '}
                                        {formInput.middlename_or_project || ''}{' '}
                                        {formInput.lastname_or_agency}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">
                                        Email
                                    </p>
                                    <p className="font-medium">
                                        {formInput.email}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">
                                        Contact Number
                                    </p>
                                    <p className="font-medium">
                                        {formInput.contact_num}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">
                                        Address
                                    </p>
                                    <p className="font-medium">
                                        {formInput.address}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <Separator />

                        {/* Professional Information */}
                        <div>
                            <h3 className="mb-3 text-lg font-semibold">
                                Professional Information
                            </h3>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <p className="text-sm text-gray-500">
                                        Office / College
                                    </p>
                                    <p className="font-medium">
                                        {formInput.office_or_college}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">
                                        Position / Designation
                                    </p>
                                    <p className="font-medium">
                                        {formInput.position_or_designation}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">
                                        Membership Type
                                    </p>
                                    <p className="font-medium">
                                        {formInput.membership?.member_code ||
                                            'N/A'}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <Separator />

                        {/* Request Details */}
                        <div>
                            <h3 className="mb-3 text-lg font-semibold">
                                Request Details
                            </h3>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div>
                                    <p className="text-sm text-gray-500">
                                        Request Type
                                    </p>
                                    <p className="font-medium">
                                        {formInput.request_type}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">
                                        Amount
                                    </p>
                                    <p className="font-medium">
                                        ₱{Number(formInput.amount).toFixed(2)}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">
                                        Payment Details
                                    </p>
                                    <p className="font-medium">
                                        {formInput.paymentDetailOption
                                            ?.payment_desc || 'N/A'}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <Separator />

                        {/* Supporting Documents */}
                        <div>
                            <h3 className="mb-3 text-lg font-semibold">
                                Supporting Documents
                            </h3>
                            {formInput.supporting_documents &&
                            formInput.supporting_documents.length > 0 ? (
                                <ul className="space-y-2">
                                    {formInput.supporting_documents.map(
                                        (doc) => (
                                            <li
                                                key={doc.id}
                                                className="flex items-center justify-between rounded-lg bg-gray-50 p-3"
                                            >
                                                <div className="flex items-center gap-3">
                                                    <span className="text-sm font-medium">
                                                        {doc.original_filename}
                                                    </span>
                                                    <span className="text-xs text-gray-500">
                                                        (
                                                        {getReadableFileSize(
                                                            doc.file_size,
                                                        )}
                                                        )
                                                    </span>
                                                    <span className="text-xs text-gray-400">
                                                        {doc.mime_type}
                                                    </span>
                                                </div>
                                                <Button
                                                    variant="link"
                                                    size="sm"
                                                    onClick={() =>
                                                        downloadDocument(doc.id)
                                                    }
                                                    className="text-blue-600"
                                                >
                                                    Download
                                                </Button>
                                            </li>
                                        ),
                                    )}
                                </ul>
                            ) : (
                                <p className="text-gray-500">
                                    No documents uploaded
                                </p>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Actions */}
                <div className="flex justify-center gap-4">
                    <Button onClick={goHome} variant="outline">
                        Go to Home
                    </Button>
                    <Button onClick={() => window.print()} variant="outline">
                        Print Receipt
                    </Button>
                    <Button onClick={() => router.visit('/public/form')}>
                        Submit Another Request
                    </Button>
                </div>
            </div>
        </div>
    );
}
