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
import { useForm } from '@inertiajs/react';
import { route } from 'ziggy-js';
import { router } from '@inertiajs/react';
import { UploadCloud02 } from '@untitledui/icons';
import { SmartphoneCharging } from 'lucide-react';

const reqType = ['New Request', 'Re-issue Request', 'Other'] as const;
const enlarge = 'h-12 px-4 text-base';

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
    // functions
    const [value, setValue] = useState('');

    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const val = e.target.value;

        // Allows only non-negative numbers with up to 2 decimal places
        if (val === '' || /^\d*\.?\d{0,2}$/.test(val)) {
            setValue(val);
        }
    };

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
        has_documents: false,
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

        supportingDocuments.forEach((file) => {
            formData.append(`documents[]`, file);
        });

        // Debug: Check what's being sent
        console.log('FormData entries:');
        for (let [key, value] of formData.entries()) {
            console.log(key, value);
        }

        // Use post with FormData
        router.post('/public/form', formData, {
            onSuccess: (page) => {
                console.log('Success!', page);
                reset();
                setSupportingDocuments([]);
                setValue('');
            },
            onError: (errors) => {
                console.error('Validation errors:', errors);
            },
            forceFormData: true,
            preserveState: false,
        });
    };

    // ? file upload
    // Add state for supporting documents
    const [supportingDocuments, setSupportingDocuments] = useState<File[]>([]);

    // handle file drop
    const handleFileDrop = (files: FileList) => {
        const newFiles = Array.from(files);
        setSupportingDocuments((prev) => [...prev, ...newFiles]);
        setData('has_documents', true);
    };

    // Handle file deletion
    const handleFileDelete = (index: number) => {
        setSupportingDocuments((prev) => prev.filter((_, i) => i !== index));
        if (supportingDocuments.length <= 1) {
            setData('has_documents', false);
        }
    };

    // Handle file retry (for failed uploads)
    const handleFileRetry = (index: number) => {
        // Re-upload logic if needed
        console.log('Retry upload for file:', supportingDocuments[index]);
    };

    // ? component ui
    return (
        <>
            <form onSubmit={handleSubmit}>
                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Contact Information
                        </CardTitle>
                        <CardDescription>
                            Fill out the details below
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="space-y-6">
                        {/* Section 1: Contact Information */}
                        <div className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    {/* email add */}
                                    <Field>
                                        <FieldLabel htmlFor="input-field-email">
                                            Email Address
                                        </FieldLabel>
                                        <Input
                                            className={enlarge}
                                            id="input-field-email"
                                            type="email"
                                            placeholder="jondoe@mail.com"
                                            value={data.email}
                                            onChange={(e) =>
                                                setData('email', e.target.value)
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
                                        <FieldLabel htmlFor="input-field-contact">
                                            Contact Number
                                        </FieldLabel>
                                        <Input
                                            className={enlarge}
                                            id="input-field-contact"
                                            type="tel"
                                            placeholder="0912 333 4444"
                                            maxLength={11}
                                            minLength={11}
                                            value={data.contact_num}
                                            onChange={(e) =>
                                                setData(
                                                    'contact_num',
                                                    e.target.value,
                                                )
                                            }
                                        />

                                        {errors.contact_num && (
                                            <p className="mt-1 text-sm text-red-500">
                                                {errors.contact_num}
                                            </p>
                                        )}
                                    </Field>
                                </div>
                            </div>
                        </div>
                        <Separator />
                        <CardTitle className="text-2xl">
                            Identity Details
                        </CardTitle>
                        {/* Section 2: Additional Info */}
                        <div className="space-y-4">
                            {/* name sections */}
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 lg:grid-cols-3">
                                <div className="space-y-2">
                                    {/* first name / office */}
                                    <Field>
                                        <FieldLabel htmlFor="input-field-fname">
                                            First Name / Office
                                        </FieldLabel>
                                        <Input
                                            className={enlarge}
                                            id="input-field-fname"
                                            type="text"
                                            value={data.firstname_or_office}
                                            onChange={(e) =>
                                                setData(
                                                    'firstname_or_office',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        {errors.firstname_or_office && (
                                            <p className="mt-1 text-sm text-red-500">
                                                {errors.firstname_or_office}
                                            </p>
                                        )}
                                    </Field>
                                </div>
                                <div className="space-y-2">
                                    {/* middle name / project */}
                                    <Field>
                                        <FieldLabel htmlFor="input-field-mname">
                                            Middle Name / Project
                                        </FieldLabel>
                                        <Input
                                            className={enlarge}
                                            id="input-field-mname"
                                            type="text"
                                            value={data.middlename_or_project}
                                            onChange={(e) =>
                                                setData(
                                                    'middlename_or_project',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        {errors.middlename_or_project && (
                                            <p className="mt-1 text-sm text-red-500">
                                                {errors.middlename_or_project}
                                            </p>
                                        )}
                                    </Field>
                                </div>
                                <div className="space-y-2">
                                    {/* last name / agency */}
                                    <Field>
                                        <FieldLabel htmlFor="input-field-lname">
                                            Last Name / Agency
                                        </FieldLabel>
                                        <Input
                                            className={enlarge}
                                            id="input-field-lname"
                                            type="text"
                                            value={data.lastname_or_agency}
                                            onChange={(e) =>
                                                setData(
                                                    'lastname_or_agency',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        {errors.lastname_or_agency && (
                                            <p className="mt-1 text-sm text-red-500">
                                                {errors.lastname_or_agency}
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
                                        <FieldLabel htmlFor="input-field-off-coll">
                                            Office / College
                                        </FieldLabel>
                                        <Input
                                            className={enlarge}
                                            id="input-field-off-coll"
                                            type="text"
                                            value={data.office_or_college}
                                            onChange={(e) =>
                                                setData(
                                                    'office_or_college',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        {errors.office_or_college && (
                                            <p className="mt-1 text-sm text-red-500">
                                                {errors.office_or_college}
                                            </p>
                                        )}
                                    </Field>
                                </div>
                                <div className="space-y-2">
                                    {/* position / designation */}
                                    <Field>
                                        <FieldLabel htmlFor="input-field-pos-des">
                                            Position / Designation
                                        </FieldLabel>
                                        <Input
                                            className={enlarge}
                                            id="input-field-pos-des"
                                            type="text"
                                            value={data.position_or_designation}
                                            onChange={(e) =>
                                                setData(
                                                    'position_or_designation',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        {errors.position_or_designation && (
                                            <p className="mt-1 text-sm text-red-500">
                                                {errors.position_or_designation}
                                            </p>
                                        )}
                                    </Field>
                                </div>
                            </div>
                        </div>
                        <div className="space-y-4">
                            {/* address */}
                            <Field>
                                <FieldLabel htmlFor="input-field-address">
                                    Address
                                </FieldLabel>
                                <Input
                                    className={enlarge}
                                    id="input-field-address"
                                    type="text"
                                    value={data.address}
                                    onChange={(e) =>
                                        setData('address', e.target.value)
                                    }
                                />
                                {errors.address && (
                                    <p className="mt-1 text-sm text-red-500">
                                        {errors.address}
                                    </p>
                                )}
                            </Field>
                        </div>
                        <Separator />
                        <CardTitle className="text-2xl">
                            Request Details
                        </CardTitle>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-2">
                            {/* amount */}
                            <Field>
                                <FieldLabel htmlFor="input-field-amount">
                                    Amount
                                </FieldLabel>
                                <div className="flex">
                                    <div className="flex h-12 items-center justify-center rounded-l-md border border-r-0 bg-muted px-4 text-base text-muted-foreground">
                                        &#x20B1;
                                    </div>
                                    <Input
                                        className={`${enlarge} rounded-l-none`}
                                        id="input-field-amount"
                                        type="text"
                                        inputMode="decimal"
                                        placeholder="00.00"
                                        value={data.amount}
                                        onChange={(e) => {
                                            const val = e.target.value;
                                            if (
                                                val === '' ||
                                                /^\d*\.?\d{0,2}$/.test(val)
                                            ) {
                                                setData('amount', val);
                                            }
                                        }}
                                    />
                                    {errors.amount && (
                                        <p className="mt-1 text-sm text-red-500">
                                            {errors.amount}
                                        </p>
                                    )}
                                </div>
                            </Field>
                            {/* request type */}
                            <Field>
                                <FieldLabel htmlFor="input-field-reqtype">
                                    Request Type
                                </FieldLabel>
                                <Combobox
                                    required
                                    value={data.request_type}
                                    items={reqType}
                                    onValueChange={(value) =>
                                        setData('request_type', value || '')
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
                                <FieldLabel htmlFor="input-field-memtype">
                                    Membership Type
                                </FieldLabel>
                                <Combobox
                                    required
                                    items={memberships}
                                    value={
                                        memberships.find(
                                            m => String(m.id) === data.membership_id
                                        )?.member_code
                                    }
                                    onValueChange={(value) => {
                                        const selected = memberships.find(
                                            m => m.member_code === value
                                        );

                                        setData(
                                            'membership_id',
                                            selected ? String(selected.id) : ""
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
                                                    value={item.member_code}
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
                                <FieldLabel htmlFor="input-field-paydet">
                                    Payment Details
                                </FieldLabel>
                                <Combobox
                                    required
                                    items={paymentOptions}
                                    value={
                                        paymentOptions.find(
                                            p => String(p.id) === data.payment_detail_option_id
                                        )?.payment_desc
                                    }
                                    onValueChange={(value) => {
                                        const selected = paymentOptions.find(
                                            p => p.payment_desc === value
                                        );

                                        setData(
                                            'payment_detail_option_id',
                                            selected ? String(selected.id) : ""
                                        );
                                    }}
                                >
                                    <ComboboxInput
                                        itemProp="paymentOption"
                                        placeholder="Select payment details"
                                        showClear={!!data.payment_detail_option_id}
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
                                                    value={item.payment_desc}
                                                >
                                                    {item.payment_desc}
                                                </ComboboxItem>
                                            )}
                                        </ComboboxList>
                                    </ComboboxContent>
                                </Combobox>
                            </Field>
                        </div>
                        <Separator />
                        <CardTitle className="text-2xl">
                            Supporting Documents
                        </CardTitle>
                        {/* file uploads */}
                        {/* File Upload Drop Zone */}
                        <FileUpload.Root>
                            <FileUpload.DropZone
                                className="bg-white text-black"
                                onDropFiles={handleFileDrop}
                                onDropUnacceptedFiles={(files) => {
                                    console.log('Unaccepted files:', files);
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
                            {supportingDocuments.length > 0 && (
                                <FileUpload.List>
                                    {supportingDocuments.map((file, index) => {
                                        const fileType = file.type.split(
                                            '/',
                                        )[0] as
                                            'image' | 'application' | 'text';
                                        const fileExtension = file.name
                                            .split('.')
                                            .pop()
                                            ?.toLowerCase();
                                        let iconType:
                                            'pdf' | 'doc' | 'image' | 'empty' =
                                            'empty';

                                        if (fileExtension === 'pdf')
                                            iconType = 'pdf';
                                        else if (
                                            ['doc', 'docx'].includes(
                                                fileExtension || '',
                                            )
                                        )
                                            iconType = 'doc';
                                        else if (fileType === 'image')
                                            iconType = 'image';

                                        return (
                                            <FileUpload.ListItemProgressBar
                                                key={`${file.name}-${index}`}
                                                name={file.name}
                                                size={file.size}
                                                progress={100}
                                                type={iconType}
                                                // Force light background and make child button icons clearly visible:
                                                className="bg-black text-white [&_button:hover]:bg-gray-100! [&_button:hover]:text-gray-900!"
                                                onDelete={() =>
                                                    handleFileDelete(index)
                                                }
                                                onRetry={() =>
                                                    handleFileRetry(index)
                                                }
                                            />
                                        );
                                    })}
                                </FileUpload.List>
                            )}
                        </FileUpload.Root>
                    </CardContent>

                    <CardFooter className="flex justify-end gap-2 pt-25">
                        <Button
                            variant="outline"
                            type="button"
                            onClick={() => {
                                reset();
                                setSupportingDocuments([]);
                                setValue('')
                            }}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Submitting...' : 'Submit Request'}
                        </Button>
                    </CardFooter>
                </Card>
            </form>
        </>
    );
}

SubmitForm.layout = (page: React.ReactNode) => (
    <PublicLayout>{page}</PublicLayout>
);
