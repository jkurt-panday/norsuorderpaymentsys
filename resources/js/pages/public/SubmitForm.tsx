import { router } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';
import { UploadCloud02 } from '@untitledui/icons';
import { Mail, User, ClipboardList, File } from 'lucide-react';
import { useState } from 'react';
import { FileUpload } from '@/components/application/file-upload/file-upload-base';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardHeader,
    CardTitle,
    CardDescription,
    CardContent,
    CardFooter,
} from '@/components/ui/card';
import {
    Combobox,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
} from '@/components/ui/combobox';
import { Field, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import PublicLayout from '@/pages/layouts/PublicLayout';

const reqType = ['New Request', 'Re-issue Request'] as const;
const enlarge =
    'h-12 rounded-xl border-slate-300 bg-white px-4 text-base shadow-sm transition-all duration-200';

interface Membership {
    id: number | string;
    member_code: string;
}

interface PaymentOption {
    id: number | string;
    payment_desc: string;
}

interface Props {
    memberships: Membership[];
    paymentOptions: PaymentOption[];
}

export default function SubmitForm({ memberships, paymentOptions }: Props) {
    // ? form handling
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        contact_num: '',
        firstname_or_office: '',
        middlename_or_project: '',
        lastname_or_agency: '',
        office_or_college: '',
        position_or_designation: '',
        address: '',
        amount: '',
        request_type: '',
        membership_id: '',
        payment_detail_option_id: '',
        // has_documents: false,
        documents: [] as File[],
    });
    // ? form submit
    // Submit handler with file upload
    const handleSubmit = (e: React.SyntheticEvent<HTMLFormElement>) => {
        e.preventDefault();

        const formData = new FormData();

        Object.entries(data).forEach(([key, value]) => {
            if (value !== null && value !== '') {
                formData.append(key, String(value));
            }
        });

        data.documents.forEach((file) => {
            formData.append(`documents[]`, file);
        });

        // Debug: Check what's being sent
        // console.log('FormData entries:');

        // for (const [key, value] of formData.entries()) {
        //     console.log(key, value);
        // }

        // Use post with FormData
        post('/public/submit', formData, {
            onSuccess: (page) => {
                console.log('Success!', page);
                reset();
                // setSupportingDocuments([]);
            },
            onError: (errors) => {
                console.error('Validation errors:', errors);
            },
            forceFormData: true,
            preserveState: true,
        });
    };

    // contact num length
    const isValidContact = data.contact_num.length === 11;

    // Tracks animated upload progress (0-100) for each file, keyed by a stable file identifier
    const [uploadProgress, setUploadProgress] = useState<
        Record<string, number>
    >({});

    // Builds a stable, unique key for a File object so we can track its progress
    const getFileKey = (file: File) =>
        `${file.name}-${file.size}-${file.lastModified}`;

    // Animates a single file's progress from 0 to 100 over ~0.8s
    const animateFileProgress = (file: File) => {
        const key = getFileKey(file);
        setUploadProgress((prev) => ({ ...prev, [key]: 0 }));

        let progress = 0;
        const interval = setInterval(() => {
            progress += 10;
            setUploadProgress((prev) => ({
                ...prev,
                [key]: Math.min(progress, 100),
            }));

            if (progress >= 100) {
                clearInterval(interval);
            }
        }, 80);
    };

    // ? file upload
    // Add state for supporting documents
    // const [supportingDocuments, setSupportingDocuments] = useState<File[]>([]);

    const [documentError, setDocumentError] = useState('');
    const MAX_FILES = 5;

    // handle file drop
    const handleFileDrop = (files: FileList) => {
        const newFiles = Array.from(files);
        // setSupportingDocuments((prev) => [...prev, ...newFiles]);

        if (data.documents.length + newFiles.length > MAX_FILES) {
            setDocumentError(
                `You can upload a maximum of ${MAX_FILES} supporting documents.`,
            );
            return;
        }

        setDocumentError('');

        setData('documents', [...data.documents, ...newFiles]);
        newFiles.forEach(animateFileProgress);
    };

    // Handle file deletion
    const handleFileDelete = (index: number) => {
        // setSupportingDocuments((prev) => prev.filter((_, i) => i !== index));
        setData(
            'documents',
            data.documents.filter((_, i) => i !== index),
        );

        if (data.documents.length <= 1) {
            setData('documents', []);
        }
    };

    // Handle file retry (for failed uploads)
    const handleFileRetry = (index: number) => {
        // Re-upload logic if needed
        console.log('Retry upload for file:', data.documents[index]);
    };

    // ? component ui
    return (
        <>
            <div className="min-h-screen bg-linear-to-b from-blue-500 via-slate-200 to-white px-6 py-12">
                <div className="mx-auto max-w-4xl">
                    <div className="mb-10 rounded-2xl border border-blue-100 bg-white px-8 py-8 text-center shadow-md">
                        <div className="flex justify-center">
                            <img
                                src="/finance_logo1.png"
                                alt="NORSU Logo"
                                width={500}
                                height={500}
                                className="pb-6"
                            />
                        </div>
                        <h1 className="text-4xl font-bold tracking-tight text-blue-900">
                            Order of Payment Request
                        </h1>
                        <p className="mt-3 text-base text-slate-600">
                            Fill out the form below to submit your request.
                        </p>
                    </div>
                    <form noValidate onSubmit={handleSubmit}>
                        <Card className="overflow-hidden rounded-3xl border border-blue-100 bg-white shadow-xl">
                            <CardHeader className="border-b border-slate-100 bg-white pb-6">
                                <CardTitle className="flex items-center gap-3 text-xl font-semibold text-blue-900">
                                    <Mail className="h-5 w-5 text-blue-600" />
                                    Contact Information
                                </CardTitle>
                                <CardDescription>
                                    Fill out the details below
                                </CardDescription>
                                {/*<pre>{JSON.stringify(errors, null, 2)}</pre>*/}
                            </CardHeader>

                            <CardContent className="space-y-6">
                                {/* Section 1: Contact Information */}
                                <div className="space-y-4">
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            {/* email add */}
                                            <Field>
                                                <FieldLabel
                                                    htmlFor="input-field-email"
                                                    className="mb-2 font-medium text-slate-700"
                                                >
                                                    Email Address
                                                </FieldLabel>
                                                <Input
                                                    className={`${enlarge} focus-visible:border-blue-500 focus-visible:ring-2 focus-visible:ring-blue-500/30`}
                                                    id="input-field-email"
                                                    type="email"
                                                    placeholder="jondoe@mail.com"
                                                    value={data.email}
                                                    onChange={(e) =>
                                                        setData(
                                                            'email',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                                {errors.email && (
                                                    <p className="mt-1 text-sm text-red-500">
                                                        {errors.email}
                                                    </p>
                                                )}{' '}
                                            </Field>
                                        </div>
                                        <div className="space-y-2">
                                            {/* contact num */}
                                            <Field>
                                                <FieldLabel
                                                    htmlFor="input-field-contact"
                                                    className="mb-2 font-medium text-slate-700"
                                                >
                                                    Contact Number
                                                </FieldLabel>
                                                <Input
                                                    className={`${enlarge} focus-visible:border-blue-500 focus-visible:ring-2 focus-visible:ring-blue-500/30`}
                                                    id="input-field-contact"
                                                    type="tel"
                                                    placeholder="0912 333 4444"
                                                    maxLength={13} // 11 digits + 2 spaces
                                                    value={data.contact_num.replace(
                                                        /^(\d{0,4})(\d{0,3})(\d{0,4}).*/,
                                                        (_, a, b, c) =>
                                                            [a, b, c]
                                                                .filter(Boolean)
                                                                .join(' '),
                                                    )}
                                                    onChange={(e) => {
                                                        const digits =
                                                            e.target.value
                                                                .replace(
                                                                    /\D/g,
                                                                    '',
                                                                )
                                                                .slice(0, 11);

                                                        setData(
                                                            'contact_num',
                                                            digits,
                                                        );
                                                    }}
                                                />
                                                {errors.contact_num && (
                                                    <p className="mt-1 text-sm text-red-500">
                                                        {errors.contact_num}
                                                    </p>
                                                )}
                                                {data.contact_num.length > 0 &&
                                                    !isValidContact && (
                                                        <p className="mt-1 text-sm text-red-500">
                                                            Contact number must
                                                            contain exactly 11
                                                            digits.
                                                        </p>
                                                    )}
                                            </Field>
                                        </div>
                                    </div>
                                </div>
                                <Separator className="bg-blue-100" />
                                <CardTitle className="flex items-center gap-3 text-xl font-semibold text-blue-900">
                                    <User className="h-5 w-5 text-blue-600" />
                                    Identity Details
                                </CardTitle>
                                {/* Section 2: Additional Info */}
                                <div className="space-y-4">
                                    {/* name sections */}
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 lg:grid-cols-3">
                                        <div className="space-y-2">
                                            {/* first name / office */}
                                            <Field>
                                                <FieldLabel
                                                    htmlFor="input-field-fname"
                                                    className="mb-2 font-medium text-slate-700"
                                                >
                                                    First Name / Office
                                                </FieldLabel>
                                                <Input
                                                    className={`${enlarge} focus-visible:border-blue-500 focus-visible:ring-2 focus-visible:ring-blue-500/30`}
                                                    id="input-field-fname"
                                                    type="text"
                                                    placeholder="Juan"
                                                    value={
                                                        data.firstname_or_office
                                                    }
                                                    onChange={(e) =>
                                                        setData(
                                                            'firstname_or_office',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                                {errors.firstname_or_office && (
                                                    <p className="mt-1 text-sm text-red-500">
                                                        {
                                                            errors.firstname_or_office
                                                        }
                                                    </p>
                                                )}
                                            </Field>
                                        </div>
                                        <div className="space-y-2">
                                            {/* middle name / project */}
                                            <Field>
                                                <FieldLabel
                                                    htmlFor="input-field-mname"
                                                    className="mb-2 font-medium text-slate-700"
                                                >
                                                    Middle Name / Project
                                                </FieldLabel>
                                                <Input
                                                    className={`${enlarge} focus-visible:border-blue-500 focus-visible:ring-2 focus-visible:ring-blue-500/30`}
                                                    id="input-field-mname"
                                                    type="text"
                                                    placeholder="Carlos"
                                                    value={
                                                        data.middlename_or_project
                                                    }
                                                    onChange={(e) =>
                                                        setData(
                                                            'middlename_or_project',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                                {errors.middlename_or_project && (
                                                    <p className="mt-1 text-sm text-red-500">
                                                        {
                                                            errors.middlename_or_project
                                                        }
                                                    </p>
                                                )}
                                            </Field>
                                        </div>
                                        <div className="space-y-2">
                                            {/* last name / agency */}
                                            <Field>
                                                <FieldLabel
                                                    htmlFor="input-field-lname"
                                                    className="mb-2 font-medium text-slate-700"
                                                >
                                                    Last Name / Agency
                                                </FieldLabel>
                                                <Input
                                                    className={`${enlarge} focus-visible:border-blue-500 focus-visible:ring-2 focus-visible:ring-blue-500/30`}
                                                    id="input-field-lname"
                                                    type="text"
                                                    placeholder="Dela Cruz"
                                                    value={
                                                        data.lastname_or_agency
                                                    }
                                                    onChange={(e) =>
                                                        setData(
                                                            'lastname_or_agency',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                                {errors.lastname_or_agency && (
                                                    <p className="mt-1 text-sm text-red-500">
                                                        {
                                                            errors.lastname_or_agency
                                                        }
                                                    </p>
                                                )}
                                            </Field>
                                        </div>
                                    </div>

                                    {/* designation section */}
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-2">
                                        <div className="space-y-2">
                                            {/* office / college */}
                                            <Field>
                                                <FieldLabel
                                                    htmlFor="input-field-off-coll"
                                                    className="mb-2 font-medium text-slate-700"
                                                >
                                                    Office / College
                                                </FieldLabel>
                                                <Input
                                                    className={`${enlarge} focus-visible:border-blue-500 focus-visible:ring-2 focus-visible:ring-blue-500/30`}
                                                    id="input-field-off-coll"
                                                    type="text"
                                                    placeholder="College of Arts and Sciences"
                                                    value={
                                                        data.office_or_college
                                                    }
                                                    onChange={(e) =>
                                                        setData(
                                                            'office_or_college',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                                {errors.office_or_college && (
                                                    <p className="mt-1 text-sm text-red-500">
                                                        {
                                                            errors.office_or_college
                                                        }
                                                    </p>
                                                )}
                                            </Field>
                                        </div>
                                        <div className="space-y-2">
                                            {/* position / designation */}
                                            <Field>
                                                <FieldLabel
                                                    htmlFor="input-field-pos-des"
                                                    className="mb-2 font-medium text-slate-700"
                                                >
                                                    Position / Designation
                                                </FieldLabel>
                                                <Input
                                                    className={`${enlarge} focus-visible:border-blue-500 focus-visible:ring-2 focus-visible:ring-blue-500/30`}
                                                    id="input-field-pos-des"
                                                    placeholder="Teacher"
                                                    type="text"
                                                    value={
                                                        data.position_or_designation
                                                    }
                                                    onChange={(e) =>
                                                        setData(
                                                            'position_or_designation',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                                {errors.position_or_designation && (
                                                    <p className="mt-1 text-sm text-red-500">
                                                        {
                                                            errors.position_or_designation
                                                        }
                                                    </p>
                                                )}
                                            </Field>
                                        </div>
                                    </div>
                                </div>
                                <div className="space-y-4">
                                    {/* address */}
                                    <Field>
                                        <FieldLabel
                                            htmlFor="input-field-address"
                                            className="mb-2 font-medium text-slate-700"
                                        >
                                            Address
                                        </FieldLabel>
                                        <Input
                                            className={`${enlarge} focus-visible:border-blue-500 focus-visible:ring-2 focus-visible:ring-blue-500/30`}
                                            id="input-field-address"
                                            type="text"
                                            placeholder="Kagawasan Ave. Dumaguete City"
                                            value={data.address}
                                            onChange={(e) =>
                                                setData(
                                                    'address',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        {errors.address && (
                                            <p className="mt-1 text-sm text-red-500">
                                                {errors.address}
                                            </p>
                                        )}
                                    </Field>
                                </div>
                                <Separator className="bg-blue-100" />
                                <CardTitle className="flex items-center gap-3 text-xl font-semibold text-blue-900">
                                    <ClipboardList className="h-5 w-5 text-blue-600" />
                                    Request Details
                                </CardTitle>
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-2">
                                    {/* amount */}
                                    <Field>
                                        <FieldLabel
                                            htmlFor="input-field-amount"
                                            className="mb-2 font-medium text-slate-700"
                                        >
                                            Amount
                                        </FieldLabel>
                                        <div className="flex">
                                            <div className="flex h-12 items-center justify-center rounded-l-md border border-r-0 border-blue-200 bg-blue-50 px-4 text-base font-semibold text-blue-700">
                                                &#x20B1;
                                            </div>
                                            <Input
                                                className={`${enlarge} rounded-l-none focus-visible:border-blue-500 focus-visible:ring-2 focus-visible:ring-blue-500/30`}
                                                id="input-field-amount"
                                                type="text"
                                                inputMode="decimal"
                                                placeholder="00.00"
                                                value={data.amount}
                                                onChange={(e) => {
                                                    const val = e.target.value;

                                                    if (
                                                        val === '' ||
                                                        /^\d*\.?\d{0,2}$/.test(
                                                            val,
                                                        )
                                                    ) {
                                                        setData('amount', val);
                                                    }
                                                }}
                                            />
                                        </div>
                                        {errors.amount && (
                                            <p className="mt-1 text-sm text-red-500">
                                                {errors.amount}
                                            </p>
                                        )}
                                    </Field>
                                    {/* request type */}
                                    <Field>
                                        <FieldLabel
                                            htmlFor="input-field-reqtype"
                                            className="mb-2 font-medium text-slate-700"
                                        >
                                            Request Type
                                        </FieldLabel>
                                        <Combobox
                                            required
                                            value={data.request_type}
                                            items={reqType}
                                            onValueChange={(value) =>
                                                setData(
                                                    'request_type',
                                                    value || '',
                                                )
                                            }
                                        >
                                            <ComboboxInput
                                                placeholder="Select a request type"
                                                className={enlarge}
                                                showClear={!!data.request_type}
                                            />
                                            <ComboboxContent>
                                                <ComboboxEmpty>
                                                    No items found.
                                                </ComboboxEmpty>
                                                <ComboboxList>
                                                    {(item) => (
                                                        <ComboboxItem
                                                            key={item}
                                                            value={item}
                                                        >
                                                            {item}
                                                        </ComboboxItem>
                                                    )}
                                                </ComboboxList>
                                            </ComboboxContent>
                                        </Combobox>
                                        {errors.request_type && (
                                            <p className="mt-1 text-sm text-red-500">
                                                {errors.request_type}
                                            </p>
                                        )}
                                    </Field>
                                    {/* membership type */}
                                    <Field>
                                        <FieldLabel
                                            htmlFor="input-field-memtype"
                                            className="mb-2 font-medium text-slate-700"
                                        >
                                            Membership Type
                                        </FieldLabel>
                                        <Combobox
                                            required
                                            items={memberships}
                                            value={
                                                memberships.find(
                                                    (m) =>
                                                        String(m.id) ===
                                                        data.membership_id,
                                                )?.member_code || ''
                                            }
                                            onValueChange={(value) => {
                                                const selected =
                                                    memberships.find(
                                                        (m) =>
                                                            m.member_code ===
                                                            value,
                                                    );

                                                setData(
                                                    'membership_id',
                                                    selected
                                                        ? String(selected.id)
                                                        : '',
                                                );
                                            }}
                                        >
                                            <ComboboxInput
                                                placeholder="Select membership"
                                                className={enlarge}
                                                showClear={!!data.membership_id}
                                            />
                                            <ComboboxContent>
                                                <ComboboxEmpty>
                                                    No items found.
                                                </ComboboxEmpty>
                                                <ComboboxList>
                                                    {(item) => (
                                                        <ComboboxItem
                                                            key={item.id}
                                                            value={
                                                                item.member_code
                                                            }
                                                        >
                                                            {item.member_code}
                                                        </ComboboxItem>
                                                    )}
                                                </ComboboxList>
                                            </ComboboxContent>
                                        </Combobox>
                                        {errors.membership_id && (
                                            <p className="mt-1 text-sm text-red-500">
                                                {errors.membership_id}
                                            </p>
                                        )}
                                    </Field>

                                    {/* payment details */}
                                    <Field>
                                        <FieldLabel
                                            htmlFor="input-field-paydet"
                                            className="mb-2 font-medium text-slate-700"
                                        >
                                            Payment Details
                                        </FieldLabel>
                                        <Combobox
                                            required
                                            items={paymentOptions}
                                            value={
                                                paymentOptions.find(
                                                    (p) =>
                                                        String(p.id) ===
                                                        data.payment_detail_option_id,
                                                )?.payment_desc || ''
                                            }
                                            onValueChange={(value) => {
                                                const selected =
                                                    paymentOptions.find(
                                                        (p) =>
                                                            p.payment_desc ===
                                                            value,
                                                    );

                                                setData(
                                                    'payment_detail_option_id',
                                                    selected
                                                        ? String(selected.id)
                                                        : '',
                                                );
                                            }}
                                        >
                                            <ComboboxInput
                                                itemProp="paymentOption"
                                                placeholder="Select payment details"
                                                showClear={
                                                    !!data.payment_detail_option_id
                                                }
                                                className={enlarge}
                                            />
                                            <ComboboxContent>
                                                <ComboboxEmpty>
                                                    No items found.
                                                </ComboboxEmpty>
                                                <ComboboxList>
                                                    {(item) => (
                                                        <ComboboxItem
                                                            key={item.id}
                                                            value={
                                                                item.payment_desc
                                                            }
                                                        >
                                                            {item.payment_desc}
                                                        </ComboboxItem>
                                                    )}
                                                </ComboboxList>
                                            </ComboboxContent>
                                        </Combobox>
                                        {errors.payment_detail_option_id && (
                                            <p className="mt-1 text-sm text-red-500">
                                                {
                                                    errors.payment_detail_option_id
                                                }
                                            </p>
                                        )}
                                    </Field>
                                </div>
                                <Separator className="bg-blue-100" />
                                <CardTitle className="flex items-center gap-3 text-xl font-semibold text-blue-900">
                                    <File className="h-5 w-5 text-blue-600" />
                                    Supporting Document(s)
                                </CardTitle>
                                <p className="mt-3 text-sm text-slate-600">
                                    e.g. (Assessment Form, Billing Statement,
                                    Computation Documents, Student ID,
                                    Liquidation Form)
                                </p>
                                {documentError && (
                                    <p className="mt-1 text-sm text-red-500">
                                        {documentError}
                                    </p>
                                )}
                                {/* file uploads */}
                                {/* File Upload Drop Zone */}
                                <FileUpload.Root>
                                    <FileUpload.DropZone
                                        className="rounded-2xl border-2 border-dashed border-blue-300 bg-blue-50/40 transition hover:border-blue-500 hover:bg-blue-50"
                                        onDropFiles={handleFileDrop}
                                        onDropUnacceptedFiles={(files) => {
                                            console.log(
                                                'Unaccepted files:',
                                                files,
                                            );
                                        }}
                                        onSizeLimitExceed={(files) => {
                                            console.log(
                                                'Files exceed size limit:',
                                                files,
                                            );
                                        }}
                                        accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp,.svg"
                                        maxSize={10 * 1024 * 1024} // 10MB
                                        allowsMultiple={true}
                                    />

                                    {/* Add a custom upload button */}
                                    <div className="flex justify-center">
                                        <Button
                                            type="button"
                                            className="rounded-xl bg-blue-600 px-6 hover:bg-blue-700"
                                            onClick={() => {
                                                // Find the hidden file input and trigger click
                                                const fileInput =
                                                    document.querySelector(
                                                        'input[type="file"]',
                                                    ) as HTMLInputElement;

                                                if (fileInput) {
                                                    fileInput.click();
                                                }
                                            }}
                                        >
                                            <UploadCloud02 className="mr-2 h-4 w-4" />
                                            Browse Files
                                        </Button>
                                    </div>

                                    {/* File List */}
                                    {data.documents.length > 0 && (
                                        <FileUpload.List>
                                            {data.documents.map(
                                                (file, index) => {
                                                    const fileType =
                                                        file.type.split(
                                                            '/',
                                                        )[0] as
                                                            | 'image'
                                                            | 'application'
                                                            | 'text';
                                                    const fileExtension =
                                                        file.name
                                                            .split('.')
                                                            .pop()
                                                            ?.toLowerCase();
                                                    let iconType:
                                                        | 'pdf'
                                                        | 'doc'
                                                        | 'image'
                                                        | 'empty' = 'empty';

                                                    if (
                                                        fileExtension === 'pdf'
                                                    ) {
                                                        iconType = 'pdf';
                                                    } else if (
                                                        [
                                                            'doc',
                                                            'docx',
                                                        ].includes(
                                                            fileExtension || '',
                                                        )
                                                    ) {
                                                        iconType = 'doc';
                                                    } else if (
                                                        fileType === 'image'
                                                    ) {
                                                        iconType = 'image';
                                                    }

                                                    return (
                                                        <FileUpload.ListItemProgressBar
                                                            key={`${file.name}-${index}`}
                                                            name={file.name}
                                                            size={file.size}
                                                            progress={
                                                                uploadProgress[
                                                                    getFileKey(
                                                                        file,
                                                                    )
                                                                ] ?? 0
                                                            }
                                                            type={iconType}
                                                            // Force light background and make child button icons clearly visible:
                                                            className="rounded-xl border border-blue-100 bg-blue-600 text-blue-100 shadow-sm [&_button:hover]:bg-gray-100! [&_button:hover]:text-gray-900!"
                                                            onDelete={() =>
                                                                handleFileDelete(
                                                                    index,
                                                                )
                                                            }
                                                            onRetry={() =>
                                                                handleFileRetry(
                                                                    index,
                                                                )
                                                            }
                                                        />
                                                    );
                                                },
                                            )}
                                        </FileUpload.List>
                                    )}
                                </FileUpload.Root>
                            </CardContent>

                            <CardFooter className="flex justify-end gap-2 px-8 py-6 pt-2">
                                <Button
                                    variant="outline"
                                    type="button"
                                    className="rounded-xl border-slate-300 hover:bg-slate-100"
                                    onClick={() => {
                                        reset();
                                        // setSupportingDocuments([]);
                                    }}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="rounded-xl bg-blue-600 font-semibold shadow-md hover:bg-blue-800"
                                >
                                    {processing
                                        ? 'Submitting...'
                                        : 'Submit Request'}
                                </Button>
                            </CardFooter>
                        </Card>
                    </form>
                </div>
            </div>
        </>
    );
}

SubmitForm.layout = (page: React.ReactNode) => (
    <PublicLayout>{page}</PublicLayout>
);
