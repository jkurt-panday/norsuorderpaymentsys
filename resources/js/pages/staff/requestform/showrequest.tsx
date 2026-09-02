import { Link, router, useForm, usePage } from '@inertiajs/react';
import React, { useEffect, useState } from 'react';
import staff from '@/routes/staff';
import { flashToast } from '@/utils/flashToast';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

interface Membership {
    member_code: string;
    member_desc: string;
}

interface PaymentDetailOption {
    id: number;
    payment_desc: string;
}

interface BankAccount {
    id: number;
    account_name: string;
    bank_name: string;
    account_num: string;
    fund_cluster: string;
}

interface Uacs {
    id: number;
    object_code: string;
    account_title: string;
}

interface ReferenceDocument {
    id: number;
    original_filename: string;
}

interface StaffInput {
    id: number;
    status: 'pending' | 'processed' | 'paid' | 'cancelled' | 'unprocessed';
    ref_date: string | null;
    bank_account: BankAccount | null;
    uacs: Uacs | null;
    reference_document: ReferenceDocument | null;
    created_at: string;
    purpose: string | null;
    or_no: string | null;
    or_date: string | null;
}

interface SupportingDocument {
    id: number;
    original_filename: string;
    file_extension: string;
    file_size?: number;
    formatted_file_size?: string;
    file_url?: string;
}

interface FormInput {
    id: number;
    reference_number: string;
    firstname_or_office: string;
    middlename_or_project: string | null;
    lastname_or_agency: string;
    office_or_college: string;
    position_or_designation: string;
    email: string;
    contact_num: string;
    address: string;
    request_type: string;
    amount: number;
    created_at: string;
    membership: Membership | null;
    payment_detail_option: PaymentDetailOption | null;
    staff_input: StaffInput | null;
    supportingDocuments?: SupportingDocument[];
}

interface FlashProps {
    success?: string;
    error?: string;
    warning?: string;
}

interface AuthUser {
    role: string;
}

interface PageProps {
    auth: { user: AuthUser | null };
    formInput: FormInput;
    bankAccounts: BankAccount[];
    uacsList: Uacs[];
    paymentOptions: PaymentDetailOption[];
    flash?: FlashProps;
}

const statusBadgeClass = (status: string) => {
    switch (status) {
        case 'processed':
            return 'bg-green-50 text-green-700';
        case 'paid':
            return 'bg-green-100 text-green-800';
        case 'cancelled':
            return 'bg-rose-100 text-rose-800';
        case 'pending':
        case 'unprocessed':
        default:
            return 'bg-amber-100 text-amber-900';
    }
};

const PlaceholderField = ({
    label,
    value = 'Not yet set',
}: {
    label: string;
    value?: string;
}) => (
    <div>
        <label className="mb-1 block text-xs font-medium tracking-wide text-slate-500 uppercase">
            {label}
        </label>
        <input
            type="text"
            value={value}
            disabled
            className="flex-1 border-0 bg-transparent p-0 text-sm text-slate-400 italic outline-none disabled:opacity-100"
        />
    </div>
);

const ReadOnlyRow = ({
    label,
    value,
    valueClass = 'text-slate-900',
    stacked = false,
    scrollable = false,
    truncate = false,
    maxLength = 60,
}: {
    label: string;
    value: string | number | null;
    valueClass?: string;
    stacked?: boolean;
    scrollable?: boolean;
    truncate?: boolean;
    maxLength?: number;
}) => {
    const stringValue = value == null ? '' : String(value);
    const shouldTruncate = truncate && stringValue.length > maxLength;
    const displayValue = shouldTruncate
        ? `${stringValue.slice(0, maxLength).trimEnd()}…`
        : stringValue;

    const valueContent = scrollable ? (
        <div
            className={`max-h-30 min-w-0 flex-1 overflow-y-auto rounded-md border border-slate-100 bg-slate-50/60 px-3 py-2 text-sm ${valueClass} break-words whitespace-pre-wrap`}
        >
            {value}
        </div>
    ) : (
        <p
            className={`min-w-0 flex-1 text-sm ${valueClass} ${
                shouldTruncate
                    ? 'truncate'
                    : 'break-words whitespace-pre-wrap'
            }`}
            title={shouldTruncate ? stringValue : undefined}
        >
            {displayValue}
        </p>
    );

    return stacked ? (
        <div className="border-b border-slate-100 py-0 last:border-0">
            <label className="mb-1 block text-sm font-medium text-slate-600">
                {label}
            </label>
            {valueContent}
        </div>
    ) : (
        <div className="flex min-w-0 items-start gap-6 border-b border-slate-100 py-0 last:border-0">
            <label className="w-40 shrink-0 text-sm font-medium text-slate-600">
                {label}
            </label>
            {valueContent}
        </div>
    );
};

const FormField = ({
    label,
    required,
    error,
    children,
}: {
    label: string;
    required?: boolean;
    error?: string;
    children: React.ReactNode;
}) => (
    <div className="mb-4">
        <label className="mb-1 block text-sm font-medium text-slate-700">
            {label} {required && <span className="text-rose-500">*</span>}
        </label>
        {children}
        {error && <p className="mt-1 text-xs text-rose-500">{error}</p>}
    </div>
);

const formatDateTime = (value: string, withSeconds = true) => {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString('en-US', {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: withSeconds ? '2-digit' : undefined,
    });
};

const formatDateOnly = (value: string) => {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);
};

const formatFileSize = (bytes?: number) => {
    if (!bytes || bytes <= 0) {
        return 'N/A';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let index = 0;

    while (size >= 1024 && index < units.length - 1) {
        size /= 1024;
        index += 1;
    }

    return `${size.toFixed(2)} ${units[index]}`;
};

export default function ShowRequest() {
    const { auth, formInput, bankAccounts, uacsList, paymentOptions, flash } =
        usePage().props as unknown as PageProps;
    const isCashier = auth?.user?.role === 'cashier';
    const [deleteTargetId, setDeleteTargetId] = useState<number | null>(null);

    const [isEmailPreviewOpen, setIsEmailPreviewOpen] = useState(false);
    const [emailSubject, setEmailSubject] = useState('');
    const [emailRecipientName, setEmailRecipientName] = useState('');
    const [emailNote, setEmailNote] = useState('');
    const [isSendingEmail, setIsSendingEmail] = useState(false);

    const buildDefaultEmailSubject = () =>
        `Order of Payment - ${formInput.reference_number}`;

    const buildDefaultRecipientName = () =>
        `${formInput.firstname_or_office} ${formInput.lastname_or_agency}`;

    const openEmailPreview = () => {
        setEmailSubject(buildDefaultEmailSubject());
        setEmailRecipientName(buildDefaultRecipientName());
        setEmailNote('');
        setIsEmailPreviewOpen(true);
    };

    const handleConfirmSendEmail = () => {
        setIsSendingEmail(true);
        router.post(
            staff.requests.emailOp.url(formInput.id),
            {
                subject: emailSubject,
                recipient_name: emailRecipientName,
                note: emailNote,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    flashToast(
                        'success',
                        'Order of Payment emailed successfully.',
                    );
                    setIsEmailPreviewOpen(false);
                },
                onError: () => {
                    flashToast(
                        'error',
                        'Failed to send the email. Please try again.',
                    );
                },
                onFinish: () => {
                    setIsSendingEmail(false);
                },
            },
        );
    };

    // Mirrors the Blade template exactly, so what's shown here matches what
    // actually gets sent. Escaping matches the backend's e()+nl2br() — this
    // is a display preview only; the real email is always built server-side.
    const escapeHtml = (value: string) =>
        value
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');

    const buildEmailPreviewHtml = (recipientName: string, note: string) => {
        const noteHtml = note.trim()
            ? `<p>${escapeHtml(note).replace(/\n/g, '<br>')}</p>`
            : '';

        return `<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1e293b; margin:0; padding:16px;">
    <p>Dear ${escapeHtml(recipientName || '—')},</p>
    <p>
        Please find attached your Order of Payment
        (Reference No. <strong>${formInput.reference_number}</strong>)
        for the amount of <strong>${formatCurrency(formInput.amount)}</strong>.
    </p>
    ${noteHtml}
    <p>Two copies are attached:</p>
    <ul>
        <li>A5 portrait — for individual filing</li>
        <li>Legal landscape — 3-up copies (Payor's / Cash Unit's / Accounting Unit's)</li>
    </ul>
    <p>Please present this at the Cash / Treasury Unit to complete payment.</p>
    <p>Regards,<br>NORSU Accounting Office</p>
</body>
</html>`;
    };

    // Toggles the inline "Process Now" form on/off in place of the
    // empty-state block. Automatically closes itself once formInput.staff_input
    // exists (i.e. after a successful submit + redirect-back-here).
    const [isProcessing, setIsProcessing] = useState(false);

    // Toggles the inline edit form for FormInput's own fields (name, amount,
    // payment option). Always available regardless of processing status —
    // these fields belong to the original submission, not to staff
    // processing, so editing them shouldn't be gated behind that.
    const [isEditingDetails, setIsEditingDetails] = useState(false);
    const [isAddingPaymentOption, setIsAddingPaymentOption] = useState(false);

    // Toggles the inline edit form for the processed staff_input values.
    // This keeps the staff processing card on the same page instead of
    // redirecting to the separate edit screen.
    const [isEditingStaffInput, setIsEditingStaffInput] = useState(false);

    // Toggles the inline edit form for OR fields (cashier only).
    const [isEditingOr, setIsEditingOr] = useState(false);

    useEffect(() => {
        if (formInput.staff_input) {
            setIsProcessing(false);
        }
    }, [formInput.staff_input]);

    // Basic flash handling — swap in your toast lib here if you have one
    // (e.g. sonner's toast.success / toast.error) instead of console.log.
    useEffect(() => {
        flashToast('success', flash?.success);
        flashToast('error', flash?.error);
        flashToast('warning', flash?.warning);
    }, [flash]);

    const supportingDocuments: SupportingDocument[] =
        formInput.supportingDocuments ??
        (formInput as any).supporting_documents ??
        [];

    const confirmDelete = (documentId: number) => {
        setDeleteTargetId(documentId);
    };

    const handleDelete = () => {
        if (deleteTargetId === null) {
            return;
        }

        router.delete(staff.documents.destroy.url(deleteTargetId), {
            onFinish: () => setDeleteTargetId(null),
            preserveScroll: true,
        });
    };

    // ---- Inline "Edit Details" form (name / amount / payment option) -----
    const {
        data: detailsData,
        setData: setDetailsData,
        put: putDetails,
        processing: isSubmittingDetails,
        errors: detailsErrors,
        reset: resetDetailsForm,
    } = useForm({
        firstname_or_office: formInput.firstname_or_office,
        middlename_or_project: formInput.middlename_or_project ?? '',
        lastname_or_agency: formInput.lastname_or_agency,
        amount: String(formInput.amount),
        payment_detail_option_id: formInput.payment_detail_option
            ? String(formInput.payment_detail_option.id)
            : '',
        new_payment_option: '',
    });

    const handleDetailsSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        putDetails(staff.requests.updateDetails.url(formInput.id), {
            preserveScroll: true,
            onSuccess: () => setIsEditingDetails(false),
        });
    };

    const cancelDetailsEdit = () => {
        resetDetailsForm();
        setIsAddingPaymentOption(false);
        setIsEditingDetails(false);
    };

    const {
        data: staffInputData,
        setData: setStaffInputData,
        put: putStaffInput,
        processing: isSubmittingStaffInput,
        errors: staffInputErrors,
        reset: resetStaffInputForm,
    } = useForm({
        fundcluster_id: formInput.staff_input?.bank_account
            ? String(formInput.staff_input.bank_account.id)
            : '',
        ref_document_id: formInput.staff_input?.reference_document
            ? String(formInput.staff_input.reference_document.id)
            : '',
        ref_date: formInput.staff_input?.ref_date
            ? formInput.staff_input.ref_date.split('T')[0]
            : '',
        uacs_id: formInput.staff_input?.uacs
            ? String(formInput.staff_input.uacs.id)
            : '',
        status: formInput.staff_input?.status ?? 'processed',
        purpose: formInput.staff_input?.purpose ?? '',
        or_no: formInput.staff_input?.or_no ?? '',
        or_date: formInput.staff_input?.or_date
            ? formInput.staff_input.or_date.split('T')[0]
            : '',
    });

    const handleStaffInputSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!formInput.staff_input) {
            return;
        }

        router.put(staff.requests.update.url(formInput.staff_input.id), staffInputData, {
            preserveScroll: true,
            onSuccess: () => {
                resetStaffInputForm();
                setIsEditingStaffInput(false);
            },
            onError: (errors) => {
                console.error('Staff input update errors:', errors);
            },
        });
    };

    const cancelStaffInputEdit = () => {
        resetStaffInputForm();
        setIsEditingStaffInput(false);
    };

    // ---- Inline "Edit OR" form (cashier only) -------------------------------
    const {
        data: orData,
        setData: setOrData,
        put: putOr,
        processing: isSubmittingOr,
        errors: orErrors,
        reset: resetOrForm,
    } = useForm({
        or_no: formInput.staff_input?.or_no ?? '',
        or_date: formInput.staff_input?.or_date
            ? formInput.staff_input.or_date.split('T')[0]
            : '',
    });

    const handleOrSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!formInput.staff_input) {
            return;
        }

        putOr(staff.requests.updateOr.url(formInput.staff_input.id), {
            preserveScroll: true,
            onSuccess: () => {
                resetOrForm();
                setIsEditingOr(false);
            },
        });
    };

    const cancelOrEdit = () => {
        resetOrForm();
        setIsEditingOr(false);
    };

    // ---- Inline "Process Now" form ----------------------------------------
    const {
        data: processData,
        setData: setProcessData,
        post: postProcess,
        processing: isSubmittingProcess,
        errors: processErrors,
        reset: resetProcessForm,
    } = useForm({
        form_input_id: formInput.id,
        fundcluster_id: '',
        ref_document_id: '',
        ref_date: new Date().toISOString().slice(0, 10),
        uacs_id: '',
        status: 'processed',
        purpose: '',
        or_no: '',
        or_date: '',
    });
    const [isOpDropdownOpen, setIsOpDropdownOpen] = useState(false);
    const handleProcessSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        postProcess(staff.requests.store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                resetProcessForm();
                setIsProcessing(false);
            },
        });
    };

    return (
        <div className="min-h-screen bg-slate-50 px-4 py-8">
            <div className="mx-auto sm:max-w-6xl">
                <div className="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 className="text-2xl font-bold text-slate-900">
                            Request Details
                        </h2>
                        <p className="text-sm text-slate-500">
                            Reference:{' '}
                            <span className="font-semibold text-blue-600">
                                {formInput.reference_number}
                            </span>
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Link
                            href={staff.requests.index.url()}
                            className="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-5 py-2 text-sm font-medium text-slate-700 shadow-sm transition-colors hover:bg-slate-100"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="2"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                className="h-4 w-4"
                            >
                                <path d="M19 12H5M12 19l-7-7 7-7" />
                            </svg>
                            Back
                        </Link>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <div className="space-y-6">
                        {/* Personal Information */}
                        <section className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div className="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                                <h3 className="text-base font-semibold text-slate-900">
                                    Personal / Office Information
                                </h3>
                                {!isEditingDetails && (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setDetailsData({
                                                firstname_or_office:
                                                    formInput.firstname_or_office,
                                                middlename_or_project:
                                                    formInput.middlename_or_project ??
                                                    '',
                                                lastname_or_agency:
                                                    formInput.lastname_or_agency,
                                                amount: String(
                                                    formInput.amount,
                                                ),
                                                payment_detail_option_id:
                                                    formInput.payment_detail_option
                                                        ? String(
                                                              formInput
                                                                  .payment_detail_option
                                                                  .id,
                                                          )
                                                        : '',
                                                new_payment_option: '',
                                            });
                                            setIsEditingDetails(true);
                                        }}
                                        className="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-100"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            strokeWidth="2"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            className="h-3.5 w-3.5"
                                        >
                                            <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" />
                                        </svg>
                                        Edit
                                    </button>
                                )}
                            </div>
                            <div className="p-6">
                                {isEditingDetails ? (
                                    <div className="space-y-4">
                                        <div>
                                            <label className="mb-1 block text-sm font-medium text-slate-700">
                                                First Name / Office{' '}
                                                <span className="text-rose-500">
                                                    *
                                                </span>
                                            </label>
                                            <input
                                                type="text"
                                                className={`w-full rounded-xl border px-4 py-2 text-sm text-slate-700 outline-none ${
                                                    detailsErrors.firstname_or_office
                                                        ? 'border-rose-400'
                                                        : 'border-slate-200'
                                                }`}
                                                value={
                                                    detailsData.firstname_or_office
                                                }
                                                onChange={(e) =>
                                                    setDetailsData(
                                                        'firstname_or_office',
                                                        e.target.value,
                                                    )
                                                }
                                                required
                                            />
                                            {detailsErrors.firstname_or_office && (
                                                <p className="mt-1 text-xs text-rose-500">
                                                    {
                                                        detailsErrors.firstname_or_office
                                                    }
                                                </p>
                                            )}
                                        </div>

                                        <div>
                                            <label className="mb-1 block text-sm font-medium text-slate-700">
                                                Middle Name / Project
                                            </label>
                                            <input
                                                type="text"
                                                className={`w-full rounded-xl border px-4 py-2 text-sm text-slate-700 outline-none ${
                                                    detailsErrors.middlename_or_project
                                                        ? 'border-rose-400'
                                                        : 'border-slate-200'
                                                }`}
                                                value={
                                                    detailsData.middlename_or_project
                                                }
                                                onChange={(e) =>
                                                    setDetailsData(
                                                        'middlename_or_project',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                            {detailsErrors.middlename_or_project && (
                                                <p className="mt-1 text-xs text-rose-500">
                                                    {
                                                        detailsErrors.middlename_or_project
                                                    }
                                                </p>
                                            )}
                                        </div>

                                        <div>
                                            <label className="mb-1 block text-sm font-medium text-slate-700">
                                                Last Name / Agency{' '}
                                                <span className="text-rose-500">
                                                    *
                                                </span>
                                            </label>
                                            <input
                                                type="text"
                                                className={`w-full rounded-xl border px-4 py-2 text-sm text-slate-700 outline-none ${
                                                    detailsErrors.lastname_or_agency
                                                        ? 'border-rose-400'
                                                        : 'border-slate-200'
                                                }`}
                                                value={
                                                    detailsData.lastname_or_agency
                                                }
                                                onChange={(e) =>
                                                    setDetailsData(
                                                        'lastname_or_agency',
                                                        e.target.value,
                                                    )
                                                }
                                                required
                                            />
                                            {detailsErrors.lastname_or_agency && (
                                                <p className="mt-1 text-xs text-rose-500">
                                                    {
                                                        detailsErrors.lastname_or_agency
                                                    }
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                ) : (
                                    <div className="space-y-3">
                                        <div className="flex min-w-0 items-start gap-6 border-b border-slate-100 py-3 last:border-0">
                                            <ReadOnlyRow
                                                label="First Name  / Office"
                                                value={
                                                    formInput.firstname_or_office
                                                }
                                                valueClass="text-black-400"
                                            />
                                        </div>
                                        <div className="flex min-w-0 items-start gap-6 border-b border-slate-100 py-3 last:border-0">
                                            <ReadOnlyRow
                                                label="Middlename / Project"
                                                value={
                                                    formInput.middlename_or_project
                                                }
                                                valueClass="text-black-400"
                                            />
                                        </div>
                                        <div className="flex min-w-0 items-start gap-6 border-b border-slate-100 py-3 last:border-0">
                                            <ReadOnlyRow
                                                label="Last Name / Agency"
                                                value={
                                                    formInput.lastname_or_agency
                                                }
                                                valueClass="text-black-400"
                                            />
                                        </div>
                                        <div className="flex min-w-0 items-start gap-6 border-b border-slate-100 py-3 last:border-0">
                                            <ReadOnlyRow
                                                label="Office / College"
                                                value={
                                                    formInput.office_or_college
                                                }
                                                valueClass="text-black-400"
                                            />
                                        </div>
                                        <div className="flex min-w-0 items-start gap-6 border-b border-slate-100 py-3 last:border-0">
                                            <ReadOnlyRow
                                                label="Position / Desgination"
                                                value={
                                                    formInput.position_or_designation
                                                }
                                                valueClass="text-black-400"
                                            />
                                        </div>
                                        <div className="flex min-w-0 items-start gap-6 border-b border-slate-100 py-3 last:border-0">
                                            <ReadOnlyRow
                                                label="Email"
                                                value={formInput.email}
                                                valueClass="text-blue-400"
                                            />
                                        </div>
                                        <div className="flex min-w-0 items-start gap-6 border-b border-slate-100 py-3 last:border-0">
                                            <ReadOnlyRow
                                                label="Contact Number"
                                                value={formInput.contact_num}
                                                valueClass="text-blue-400"
                                            />
                                        </div>
                                        <div className="flex min-w-0 items-start gap-6 border-b border-slate-100 py-3 last:border-0">
                                            <ReadOnlyRow
                                                label="Address"
                                                value={formInput.address}
                                                valueClass="text-black-400"
                                            />
                                        </div>
                                    </div>
                                )}
                            </div>
                        </section>

                        {/* Payment Information */}
                        <section className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                            <div className="flex items-center gap-2 border-b border-slate-200 px-6 py-4">
                                <h3 className="text-base font-semibold text-slate-900">
                                    Payment Information
                                </h3>
                            </div>
                            <div className="p-6">
                                {isEditingDetails ? (
                                    <form onSubmit={handleDetailsSubmit}>
                                        <div className="mb-4">
                                            <label className="mb-1 block text-sm font-medium text-slate-700">
                                                Amount{' '}
                                                <span className="text-rose-500">
                                                    *
                                                </span>
                                            </label>
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                className={`w-full rounded-xl border px-4 py-2 text-sm text-slate-700 outline-none ${
                                                    detailsErrors.amount
                                                        ? 'border-rose-400'
                                                        : 'border-slate-200'
                                                }`}
                                                value={detailsData.amount}
                                                onChange={(e) =>
                                                    setDetailsData(
                                                        'amount',
                                                        e.target.value,
                                                    )
                                                }
                                                required
                                            />
                                            {detailsErrors.amount && (
                                                <p className="mt-1 text-xs text-rose-500">
                                                    {detailsErrors.amount}
                                                </p>
                                            )}
                                        </div>

                                        <div className="mb-6">
                                            <label className="mb-1 block text-sm font-medium text-slate-700">
                                                Payment Option{' '}
                                                <span className="text-rose-500">
                                                    *
                                                </span>
                                            </label>
                                            {!isAddingPaymentOption ? (
                                                <div className="flex gap-2">
                                                    <select
                                                        className={`w-full rounded-xl border px-4 py-2 text-sm text-slate-700 outline-none ${
                                                            detailsErrors.payment_detail_option_id
                                                                ? 'border-rose-400'
                                                                : 'border-slate-200'
                                                        }`}
                                                        value={
                                                            detailsData.payment_detail_option_id
                                                        }
                                                        onChange={(e) =>
                                                            setDetailsData(
                                                                'payment_detail_option_id',
                                                                e.target.value,
                                                            )
                                                        }
                                                        required
                                                    >
                                                        <option value="">
                                                            Select Payment
                                                            Option
                                                        </option>
                                                        {paymentOptions.map(
                                                            (option) => (
                                                                <option
                                                                    key={
                                                                        option.id
                                                                    }
                                                                    value={
                                                                        option.id
                                                                    }
                                                                >
                                                                    {
                                                                        option.payment_desc
                                                                    }
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            setIsAddingPaymentOption(
                                                                true,
                                                            )
                                                        }
                                                        className="inline-flex cursor-pointer items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-slate-700"
                                                    >
                                                        Add
                                                    </button>
                                                </div>
                                            ) : (
                                                <div className="flex gap-2">
                                                    <input
                                                        type="text"
                                                        value={
                                                            detailsData.new_payment_option
                                                        }
                                                        onChange={(e) =>
                                                            setDetailsData(
                                                                'new_payment_option',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="Enter a new payment option"
                                                        className="w-full rounded-xl border border-slate-200 px-4 py-2 text-sm text-slate-700 outline-none"
                                                        autoFocus
                                                    />
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            setDetailsData(
                                                                'new_payment_option',
                                                                '',
                                                            );
                                                            setIsAddingPaymentOption(
                                                                false,
                                                            );
                                                        }}
                                                        className="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-slate-700"
                                                    >
                                                        Close
                                                    </button>
                                                </div>
                                            )}
                                            {detailsErrors.payment_detail_option_id && (
                                                <p className="mt-1 text-xs text-rose-500">
                                                    {
                                                        detailsErrors.payment_detail_option_id
                                                    }
                                                </p>
                                            )}
                                            {detailsErrors.new_payment_option && (
                                                <p className="mt-1 text-xs text-rose-500">
                                                    {
                                                        detailsErrors.new_payment_option
                                                    }
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex justify-end gap-2">
                                            <button
                                                type="button"
                                                onClick={cancelDetailsEdit}
                                                className="cursor-pointer rounded-full border border-slate-200 px-5 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100"
                                            >
                                                Cancel
                                            </button>
                                            <button
                                                type="submit"
                                                disabled={isSubmittingDetails}
                                                className="inline-flex cursor-pointer items-center gap-2 rounded-full bg-blue-600 px-6 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700 disabled:opacity-60"
                                            >
                                                <svg
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    strokeWidth="2.5"
                                                    strokeLinecap="round"
                                                    strokeLinejoin="round"
                                                    className="h-4 w-4"
                                                >
                                                    <path d="M20 6 9 17l-5-5" />
                                                </svg>
                                                {isSubmittingDetails
                                                    ? 'Saving...'
                                                    : 'Save Changes'}
                                            </button>
                                        </div>
                                    </form>
                                ) : (
                                    <div className="space-y-3">
                                        <div className="flex min-w-0 items-start gap-6 border-b border-slate-100 py-3 last:border-0">
                                            <label className="w-40 shrink-0 text-sm font-medium text-slate-600">
                                                Request Type
                                            </label>
                                            <input
                                                type="text"
                                                value={formInput.request_type}
                                                disabled
                                                className="flex-1 border-0 bg-transparent p-0 text-sm text-slate-900 outline-none disabled:opacity-100"
                                            />
                                        </div>
                                        <div className="flex min-w-0 items-start gap-6 border-b border-slate-100 py-3 last:border-0">
                                            <label className="w-40 shrink-0 text-sm font-medium text-slate-600">
                                                Amount
                                            </label>
                                            <input
                                                type="text"
                                                value={formatCurrency(
                                                    formInput.amount,
                                                )}
                                                disabled
                                                className="flex-1 border-0 bg-transparent p-0 text-sm text-blue-400 outline-none disabled:opacity-100"
                                            />
                                        </div>
                                        <div className="flex min-w-0 items-start gap-6 border-b border-slate-100 py-3 last:border-0">
                                            <ReadOnlyRow
                                                label="Membership"
                                                value={
                                                    formInput.membership
                                                        ? `${formInput.membership.member_code} - ${formInput.membership.member_desc}`
                                                        : 'N/A'
                                                }
                                                valueClass="text-black-400"
                                            />
                                        </div>
                                        <div className="flex min-w-0 items-start gap-6 border-b border-slate-100 py-3 last:border-0">
                                            <ReadOnlyRow
                                                label="Payment Option"
                                                value={
                                                    formInput
                                                        .payment_detail_option
                                                        ?.payment_desc ?? 'N/A'
                                                }
                                                valueClass="text-black-400"
                                            />
                                        </div>
                                        <div className="flex min-w-0 items-start gap-6 border-b border-slate-100 py-3 last:border-0">
                                            <ReadOnlyRow
                                                label="Submission Date"
                                                value={formatDateTime(
                                                    formInput.created_at,
                                                )}
                                                valueClass="text-black-400"
                                            />
                                        </div>
                                    </div>
                                )}
                            </div>
                        </section>
                    </div>

                    <div className="space-y-6">
                        {/* Staff Processing Information */}
                        <section className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                            <div className="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                                <h3 className="text-base font-semibold text-slate-900">
                                    Staff Processing
                                </h3>
                                <div className="flex items-center gap-2">
                                    {formInput.staff_input && (
                                        <span
                                            className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${statusBadgeClass(formInput.staff_input.status)}`}
                                        >
                                            {formInput.staff_input.status
                                                .charAt(0)
                                                .toUpperCase() +
                                                formInput.staff_input.status.slice(
                                                    1,
                                                )}
                                        </span>
                                    )}
                                    {/* Inline edit is used here so the staff processing
                                                fields stay on the same page and behave like the
                                                left-side request details card. */}
                                    {formInput.staff_input &&
                                        !isEditingStaffInput &&
                                        !isCashier && (
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    setStaffInputData({
                                                        fundcluster_id:
                                                            formInput
                                                                .staff_input
                                                                ?.bank_account
                                                                ? String(
                                                                      formInput
                                                                          .staff_input
                                                                          .bank_account
                                                                          .id,
                                                                  )
                                                                : '',
                                                        ref_document_id:
                                                            formInput
                                                                .staff_input
                                                                ?.reference_document
                                                                ? String(
                                                                      formInput
                                                                          .staff_input
                                                                          .reference_document
                                                                          .id,
                                                                  )
                                                                : '',
                                                        ref_date:
                                                            formInput
                                                                .staff_input
                                                                ?.ref_date
                                                                ? formInput
                                                                    .staff_input
                                                                    .ref_date
                                                                    .split('T')[0]
                                                                : '',
                                                        uacs_id: formInput
                                                            .staff_input?.uacs
                                                            ? String(
                                                                  formInput
                                                                      .staff_input
                                                                      .uacs.id,
                                                              )
                                                            : '',
                                                        status:
                                                            formInput
                                                                .staff_input
                                                                ?.status ??
                                                            'processed',
                                                        purpose:
                                                            formInput
                                                                .staff_input
                                                                ?.purpose ?? '',
                                                        or_no:
                                                            formInput
                                                                .staff_input
                                                                ?.or_no ?? '',
                                                        or_date:
                                                            formInput
                                                                .staff_input
                                                                ?.or_date
                                                                ? formInput
                                                                    .staff_input
                                                                    .or_date
                                                                    .split('T')[0]
                                                                : '',
                                                    });
                                                    setIsEditingStaffInput(
                                                        true,
                                                    );
                                                }}
                                                className="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-100"
                                            >
                                                <svg
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    strokeWidth="2"
                                                    strokeLinecap="round"
                                                    strokeLinejoin="round"
                                                    className="h-3.5 w-3.5"
                                                >
                                                    <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" />
                                                </svg>
                                                Edit
                                            </button>
                                        )}
                                    {!formInput.staff_input &&
                                        !isProcessing &&
                                        !isCashier && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setIsProcessing(true)
                                                }
                                                className="inline-flex cursor-pointer items-center gap-1.5 rounded-full bg-blue-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-blue-700"
                                            >
                                                <svg
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    strokeWidth="2.5"
                                                    strokeLinecap="round"
                                                    strokeLinejoin="round"
                                                    className="h-3.5 w-3.5"
                                                >
                                                    <path d="M20 6 9 17l-5-5" />
                                                </svg>
                                                Process Now
                                            </button>
                                        )}
                                </div>
                            </div>
                            <div className="p-6">
                                {formInput.staff_input ? (
                                    isEditingStaffInput ? (
                                        <form onSubmit={handleStaffInputSubmit}>
                                            <div className="space-y-4">
                                                <FormField
                                                    label="Bank Account"
                                                    required
                                                    error={
                                                        staffInputErrors.fundcluster_id
                                                    }
                                                >
                                                    <select
                                                        className={`w-full rounded-xl border px-4 py-2 text-sm text-slate-700 outline-none ${
                                                            staffInputErrors.fundcluster_id
                                                                ? 'border-rose-400'
                                                                : 'border-slate-200'
                                                        }`}
                                                        value={
                                                            staffInputData.fundcluster_id
                                                        }
                                                        onChange={(e) =>
                                                            setStaffInputData(
                                                                'fundcluster_id',
                                                                e.target.value,
                                                            )
                                                        }
                                                        required
                                                    >
                                                        <option value="">
                                                            Select Bank Account
                                                        </option>
                                                        {bankAccounts.map(
                                                            (account) => (
                                                                <option
                                                                    key={
                                                                        account.id
                                                                    }
                                                                    value={
                                                                        account.id
                                                                    }
                                                                >
                                                                    {
                                                                        account.fund_cluster
                                                                    }{' '}
                                                                    -{' '}
                                                                    {
                                                                        account.account_name
                                                                    }{' '}
                                                                    -{' '}
                                                                    {
                                                                        account.bank_name
                                                                    }{' '}
                                                                    (
                                                                    {
                                                                        account.account_num
                                                                    }
                                                                    )
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>
                                                </FormField>

                                                <FormField
                                                    label="Reference Document"
                                                    error={
                                                        staffInputErrors.ref_document_id
                                                    }
                                                >
                                                    <select
                                                        className={`w-full rounded-xl border px-4 py-2 text-sm text-slate-700 outline-none ${
                                                            staffInputErrors.ref_document_id
                                                                ? 'border-rose-400'
                                                                : 'border-slate-200'
                                                        }`}
                                                        value={
                                                            staffInputData.ref_document_id
                                                        }
                                                        onChange={(e) =>
                                                            setStaffInputData(
                                                                'ref_document_id',
                                                                e.target.value,
                                                            )
                                                        }
                                                    >
                                                        <option value="">
                                                            Select Reference
                                                            Document (Optional)
                                                        </option>
                                                        {supportingDocuments.map(
                                                            (document) => (
                                                                <option
                                                                    key={
                                                                        document.id
                                                                    }
                                                                    value={
                                                                        document.id
                                                                    }
                                                                >
                                                                    {
                                                                        document.original_filename
                                                                    }
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>
                                                </FormField>
                                                <FormField
                                                    label="Reference Date"
                                                    required
                                                    error={
                                                        staffInputErrors.ref_date
                                                    }
                                                >
                                                    <input
                                                        type="date"
                                                        className={`w-full rounded-xl border px-4 py-2 text-sm text-slate-700 outline-none ${
                                                            staffInputErrors.ref_date
                                                                ? 'border-rose-400'
                                                                : 'border-slate-200'
                                                        }`}
                                                        value={
                                                            staffInputData.ref_date
                                                        }
                                                        onChange={(e) =>
                                                            setStaffInputData(
                                                                'ref_date',
                                                                e.target.value,
                                                            )
                                                        }
                                                        required
                                                    />
                                                </FormField>
                                                <FormField
                                                    label="UACS"
                                                    required
                                                    error={
                                                        staffInputErrors.uacs_id
                                                    }
                                                >
                                                    <select
                                                        className={`w-full rounded-xl border px-4 py-2 text-sm text-slate-700 outline-none ${
                                                            staffInputErrors.uacs_id
                                                                ? 'border-rose-400'
                                                                : 'border-slate-200'
                                                        }`}
                                                        value={
                                                            staffInputData.uacs_id
                                                        }
                                                        onChange={(e) =>
                                                            setStaffInputData(
                                                                'uacs_id',
                                                                e.target.value,
                                                            )
                                                        }
                                                        required
                                                    >
                                                        <option value="">
                                                            Select UACS
                                                        </option>
                                                        {uacsList.map(
                                                            (uacs) => (
                                                                <option
                                                                    key={
                                                                        uacs.id
                                                                    }
                                                                    value={
                                                                        uacs.id
                                                                    }
                                                                >
                                                                    {
                                                                        uacs.object_code
                                                                    }{' '}
                                                                    -{' '}
                                                                    {
                                                                        uacs.account_title
                                                                    }
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>
                                                </FormField>
                                                <FormField
                                                    label="Status"
                                                    required
                                                    error={
                                                        staffInputErrors.status
                                                    }
                                                >
                                                    <select
                                                        className={`w-full rounded-xl border px-4 py-2 text-sm text-slate-700 outline-none ${
                                                            staffInputErrors.status
                                                                ? 'border-rose-400'
                                                                : 'border-slate-200'
                                                        }`}
                                                        value={
                                                            staffInputData.status
                                                        }
                                                        onChange={(e) =>
                                                            setStaffInputData(
                                                                'status',
                                                                e.target.value,
                                                            )
                                                        }
                                                        required
                                                    >
                                                        <option value="pending">
                                                            Pending
                                                        </option>
                                                        <option value="processed">
                                                            Processed
                                                        </option>
                                                        <option value="paid">
                                                            Paid
                                                        </option>
                                                        <option value="cancelled">
                                                            Cancelled
                                                        </option>
                                                    </select>
                                                </FormField>
                                                <FormField
                                                    label="Purpose"
                                                    error={
                                                        staffInputErrors.purpose
                                                    }
                                                >
                                                    <textarea
                                                        className={`w-full rounded-xl border px-4 py-2 text-sm text-slate-700 outline-none ${
                                                            staffInputErrors.purpose
                                                                ? 'border-rose-400'
                                                                : 'border-slate-200'
                                                        }`}
                                                        value={
                                                            staffInputData.purpose
                                                        }
                                                        onChange={(e) =>
                                                            setStaffInputData(
                                                                'purpose',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </FormField>

                                                <div className="flex justify-end gap-2 pt-2">
                                                    <button
                                                        type="button"
                                                        onClick={
                                                            cancelStaffInputEdit
                                                        }
                                                        className="cursor-pointer rounded-full border border-slate-200 px-5 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100"
                                                    >
                                                        Cancel
                                                    </button>
                                                    <button
                                                        type="submit"
                                                        disabled={
                                                            isSubmittingStaffInput
                                                        }
                                                        className="inline-flex items-center gap-2 rounded-full bg-emerald-600 px-6 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700 disabled:opacity-60"
                                                    >
                                                        <svg
                                                            xmlns="http://www.w3.org/2000/svg"
                                                            viewBox="0 0 24 24"
                                                            fill="none"
                                                            stroke="currentColor"
                                                            strokeWidth="2.5"
                                                            strokeLinecap="round"
                                                            strokeLinejoin="round"
                                                            className="h-4 w-4"
                                                        >
                                                            <path d="M20 6 9 17l-5-5" />
                                                        </svg>
                                                        {isSubmittingStaffInput
                                                            ? 'Saving...'
                                                            : 'Save Changes'}
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    ) : (
                                        <div className="space-y-3">
                                            <div className="flex min-w-0 items-start gap-6 border-b border-slate-100 py-3 last:border-0">
                                                <ReadOnlyRow
                                                    label="Bank Account"
                                                    value={
                                                        formInput.staff_input
                                                            ?.bank_account
                                                            ?.account_name ??
                                                        'N/A'
                                                    }
                                                    valueClass="text-black-400"
                                                />
                                            </div>
                                            <div className="flex min-w-0 items-start gap-6 border-b border-slate-100 py-3 last:border-0">
                                                <ReadOnlyRow
                                                    label="Bank"
                                                    value={
                                                        formInput.staff_input
                                                            .bank_account
                                                            ?.bank_name ?? 'N/A'
                                                    }
                                                    valueClass="text-black-400"
                                                />
                                            </div>
                                            <div className="flex min-w-0 items-start gap-6 border-b border-slate-100 py-3 last:border-0">
                                                <ReadOnlyRow
                                                    label="Fund Cluster"
                                                    value={
                                                        formInput.staff_input
                                                            .bank_account
                                                            ?.fund_cluster ??
                                                        'N/A'
                                                    }
                                                    valueClass="text-black-400"
                                                />
                                            </div>
                                            <div className="flex min-w-0 items-start gap-6 border-b border-slate-100 py-3 last:border-0">
                                                <ReadOnlyRow
                                                    label="Account Number"
                                                    value={
                                                        formInput.staff_input
                                                            .bank_account
                                                            ?.account_num ??
                                                        'N/A'
                                                    }
                                                    valueClass="text-black-400"
                                                />
                                            </div>
                                            <div className="flex min-w-0 items-start gap-6 border-b border-slate-100 py-3 last:border-0">
                                                <ReadOnlyRow
                                                    label="Reference Date"
                                                    value={
                                                        formInput.staff_input
                                                            .ref_date
                                                            ? formatDateOnly(
                                                                  formInput
                                                                      .staff_input
                                                                      .ref_date,
                                                              )
                                                            : 'N/A'
                                                    }
                                                    valueClass="text-black-400"
                                                />
                                            </div>
                                            <div className="flex min-w-0 items-start gap-6 border-b border-slate-100 py-3 last:border-0">
                                                <ReadOnlyRow
                                                    label="UACS"
                                                    value={
                                                        formInput.staff_input
                                                            .uacs
                                                            ? `${formInput.staff_input.uacs.object_code} - ${formInput.staff_input.uacs.account_title}`
                                                            : 'N/A'
                                                    }
                                                    valueClass="text-black-400"
                                                />
                                            </div>
                                            <div className="flex min-w-0 items-start gap-6 border-b border-slate-100 py-3 last:border-0">
                                                <ReadOnlyRow
                                                    label="Reference Document"
                                                    value={
                                                        formInput.staff_input
                                                            .reference_document
                                                            ?.original_filename ??
                                                        'N/A'
                                                    }
                                                    valueClass="text-black-400"
                                                    truncate
                                                />
                                            </div>
                                            <div className="w-full border-b border-slate-100 py-3 last:border-0">
                                                <ReadOnlyRow
                                                    label="Purpose"
                                                    value={
                                                        formInput.staff_input
                                                            .purpose ?? 'N/A'
                                                    }
                                                    valueClass="text-black-400"
                                                    scrollable
                                                />
                                            </div>
                                            <div className="flex min-w-0 items-start gap-6 border-b border-slate-100 py-3 last:border-0">
                                                <ReadOnlyRow
                                                    label="Processed By"
                                                    value="Staff User"
                                                    valueClass="text-black-400"
                                                />
                                            </div>
                                            <div className="flex min-w-0 items-start gap-6 border-b border-slate-100 py-3 last:border-0">
                                                <ReadOnlyRow
                                                    label="Processed Date"
                                                    value={formatDateTime(
                                                        formInput.staff_input
                                                            .created_at,
                                                    )}
                                                    valueClass="text-black-400"
                                                />
                                            </div>
                                        </div>
                                    )
                                ) : isProcessing ? (
                                    /* ---- Inline Process Form ---- */
                                    <form onSubmit={handleProcessSubmit}>
                                        <input
                                            type="hidden"
                                            name="form_input_id"
                                            value={processData.form_input_id}
                                        />

                                        <div className="mb-4">
                                            <label className="mb-1 block text-sm font-medium text-slate-700">
                                                Bank Account{' '}
                                                <span className="text-rose-500">
                                                    *
                                                </span>
                                            </label>
                                            <select
                                                className={`w-full rounded-xl border px-4 py-2 text-sm text-slate-700 outline-none ${
                                                    processErrors.fundcluster_id
                                                        ? 'border-rose-400'
                                                        : 'border-slate-200'
                                                }`}
                                                value={
                                                    processData.fundcluster_id
                                                }
                                                onChange={(e) =>
                                                    setProcessData(
                                                        'fundcluster_id',
                                                        e.target.value,
                                                    )
                                                }
                                                required
                                            >
                                                <option value="">
                                                    Select Bank Account
                                                </option>
                                                {bankAccounts.map((account) => (
                                                    <option
                                                        key={account.id}
                                                        value={account.id}
                                                    >
                                                        {account.fund_cluster} -{' '}
                                                        {account.account_name} -{' '}
                                                        {account.bank_name} (
                                                        {account.account_num})
                                                    </option>
                                                ))}
                                            </select>
                                            {processErrors.fundcluster_id && (
                                                <p className="mt-1 text-xs text-rose-500">
                                                    {
                                                        processErrors.fundcluster_id
                                                    }
                                                </p>
                                            )}
                                        </div>

                                        <div className="mb-4">
                                            <label className="mb-1 block text-sm font-medium text-slate-700">
                                                Reference Document
                                            </label>
                                            <select
                                                className={`w-full rounded-xl border px-4 py-2 text-sm text-slate-700 outline-none ${
                                                    processErrors.ref_document_id
                                                        ? 'border-rose-400'
                                                        : 'border-slate-200'
                                                }`}
                                                value={
                                                    processData.ref_document_id
                                                }
                                                onChange={(e) =>
                                                    setProcessData(
                                                        'ref_document_id',
                                                        e.target.value,
                                                    )
                                                }
                                            >
                                                <option value="">
                                                    Select Reference Document
                                                    (Optional)
                                                </option>
                                                {supportingDocuments.map(
                                                    (document) => (
                                                        <option
                                                            key={document.id}
                                                            value={document.id}
                                                        >
                                                            {
                                                                document.original_filename
                                                            }
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                            {processErrors.ref_document_id && (
                                                <p className="mt-1 text-xs text-rose-500">
                                                    {
                                                        processErrors.ref_document_id
                                                    }
                                                </p>
                                            )}
                                        </div>

                                        <div className="mb-4">
                                            <label className="mb-1 block text-sm font-medium text-slate-700">
                                                Reference Date{' '}
                                                <span className="text-rose-500">
                                                    *
                                                </span>
                                            </label>
                                            <input
                                                type="date"
                                                className={`w-full rounded-xl border px-4 py-2 text-sm text-slate-700 outline-none ${
                                                    processErrors.ref_date
                                                        ? 'border-rose-400'
                                                        : 'border-slate-200'
                                                }`}
                                                value={processData.ref_date}
                                                onChange={(e) =>
                                                    setProcessData(
                                                        'ref_date',
                                                        e.target.value,
                                                    )
                                                }
                                                required
                                            />
                                            {processErrors.ref_date && (
                                                <p className="mt-1 text-xs text-rose-500">
                                                    {processErrors.ref_date}
                                                </p>
                                            )}
                                        </div>

                                        <div className="mb-4">
                                            <label className="mb-1 block text-sm font-medium text-slate-700">
                                                UACS{' '}
                                                <span className="text-rose-500">
                                                    *
                                                </span>
                                            </label>
                                            <select
                                                className={`w-full rounded-xl border px-4 py-2 text-sm text-slate-700 outline-none ${
                                                    processErrors.uacs_id
                                                        ? 'border-rose-400'
                                                        : 'border-slate-200'
                                                }`}
                                                value={processData.uacs_id}
                                                onChange={(e) =>
                                                    setProcessData(
                                                        'uacs_id',
                                                        e.target.value,
                                                    )
                                                }
                                                required
                                            >
                                                <option value="">
                                                    Select UACS
                                                </option>
                                                {uacsList.map((uacs) => (
                                                    <option
                                                        key={uacs.id}
                                                        value={uacs.id}
                                                    >
                                                        {uacs.object_code} -{' '}
                                                        {uacs.account_title}
                                                    </option>
                                                ))}
                                            </select>
                                            {processErrors.uacs_id && (
                                                <p className="mt-1 text-xs text-rose-500">
                                                    {processErrors.uacs_id}
                                                </p>
                                            )}
                                        </div>

                                        <div className="mb-6">
                                            <label className="mb-1 block text-sm font-medium text-slate-700">
                                                Status{' '}
                                                <span className="text-rose-500">
                                                    *
                                                </span>
                                            </label>
                                            <select
                                                className={`w-full rounded-xl border px-4 py-2 text-sm text-slate-700 outline-none ${
                                                    processErrors.status
                                                        ? 'border-rose-400'
                                                        : 'border-slate-200'
                                                }`}
                                                value={processData.status}
                                                onChange={(e) =>
                                                    setProcessData(
                                                        'status',
                                                        e.target.value,
                                                    )
                                                }
                                                required
                                            >
                                                <option value="">
                                                    Select Status
                                                </option>
                                                <option value="pending">
                                                    Pending
                                                </option>
                                                <option value="processed">
                                                    Processed
                                                </option>
                                                <option value="paid">
                                                    Paid
                                                </option>
                                                <option value="cancelled">
                                                    Cancelled
                                                </option>
                                            </select>
                                            {processErrors.status && (
                                                <p className="mt-1 text-xs text-rose-500">
                                                    {processErrors.status}
                                                </p>
                                            )}
                                        </div>
                                        <div className="mb-6">
                                            <label className="mb-1 block text-sm font-medium text-slate-700">
                                                Purpose
                                            </label>
                                            <textarea
                                                className={`w-full rounded-xl border px-4 py-2 text-sm text-slate-700 outline-none ${
                                                    processErrors.purpose
                                                        ? 'border-rose-400'
                                                        : 'border-slate-200'
                                                }`}
                                                value={processData.purpose}
                                                onChange={(e) =>
                                                    setProcessData(
                                                        'purpose',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                            {processErrors.purpose && (
                                                <p className="mt-1 text-xs text-rose-500">
                                                    {processErrors.purpose}
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex justify-end gap-2">
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setIsProcessing(false)
                                                }
                                                className="rounded-full border border-slate-200 px-5 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100"
                                            >
                                                Cancel
                                            </button>
                                            <button
                                                type="submit"
                                                disabled={isSubmittingProcess}
                                                className="inline-flex items-center gap-2 rounded-full bg-emerald-600 px-6 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700 disabled:opacity-60"
                                            >
                                                <svg
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    strokeWidth="2.5"
                                                    strokeLinecap="round"
                                                    strokeLinejoin="round"
                                                    className="h-4 w-4"
                                                >
                                                    <path d="M20 6 9 17l-5-5" />
                                                </svg>
                                                {isSubmittingProcess
                                                    ? 'Processing...'
                                                    : 'Process Request'}
                                            </button>
                                        </div>
                                    </form>
                                ) : (
                                    <div className="space-y-3">
                                        {[
                                            'Bank Account',
                                            'Bank',
                                            'Reference Date',
                                            'UACS',
                                            'Reference Document',
                                        ].map((label) => (
                                            <PlaceholderField
                                                key={label}
                                                label={label}
                                            />
                                        ))}
                                        <div>
                                            <label className="mb-1 block text-xs font-medium tracking-wide text-slate-500 uppercase">
                                                Status
                                            </label>
                                            <span className="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-900">
                                                Unprocessed
                                            </span>
                                        </div>
                                        <PlaceholderField
                                            label="Purpose"
                                            value="To be filled"
                                        />
                                    </div>
                                )}
                            </div>
                        </section>

                        {/* Official Receipt Information */}
                        {formInput.staff_input && (
                            <section className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                                <div className="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                                    <h3 className="text-base font-semibold text-slate-900">
                                        Official Receipt
                                    </h3>
                                    <div className="flex items-center gap-2">
                                        {isCashier && !isEditingOr && (
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    setOrData({
                                                        or_no: formInput.staff_input?.or_no ?? '',
                                                        or_date: formInput.staff_input?.or_date
                                                            ? formInput.staff_input.or_date.split('T')[0]
                                                            : '',
                                                    });
                                                    setIsEditingOr(true);
                                                }}
                                                className="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-100"
                                            >
                                                <svg
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    strokeWidth="2"
                                                    strokeLinecap="round"
                                                    strokeLinejoin="round"
                                                    className="h-3.5 w-3.5"
                                                >
                                                    <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" />
                                                </svg>
                                                {formInput.staff_input?.or_no ? 'Edit OR' : 'Place OR Number'}
                                            </button>
                                        )}
                                    </div>
                                </div>
                                <div className="p-6">
                                    {isCashier && isEditingOr ? (
                                        <form onSubmit={handleOrSubmit}>
                                            <div className="space-y-4">
                                                <FormField
                                                    label="OR Number"
                                                    required
                                                    error={orErrors.or_no}
                                                >
                                                    <input
                                                        type="text"
                                                        inputMode="numeric"
                                                        pattern="[0-9\-\.\/\s]+"
                                                        title="Only numbers, dashes, slashes, dots and spaces are allowed"
                                                        className={`w-full rounded-xl border px-4 py-2 text-sm text-slate-700 outline-none ${
                                                            orErrors.or_no
                                                                ? 'border-rose-400'
                                                                : 'border-slate-200'
                                                        }`}
                                                        value={orData.or_no}
                                                        onChange={(e) => {
                                                            const filtered = e.target.value.replace(/[^0-9\-\.\/\s]/g, '');
                                                            setOrData('or_no', filtered);
                                                        }}
                                                        required
                                                    />
                                                </FormField>
                                                <FormField
                                                    label="OR Date"
                                                    required
                                                    error={orErrors.or_date}
                                                >
                                                    <input
                                                        type="date"
                                                        className={`w-full rounded-xl border px-4 py-2 text-sm text-slate-700 outline-none ${
                                                            orErrors.or_date
                                                                ? 'border-rose-400'
                                                                : 'border-slate-200'
                                                        }`}
                                                        value={orData.or_date}
                                                        onChange={(e) =>
                                                            setOrData('or_date', e.target.value)
                                                        }
                                                        required
                                                    />
                                                </FormField>
                                                <div className="flex justify-end gap-2 pt-2">
                                                    <button
                                                        type="button"
                                                        onClick={cancelOrEdit}
                                                        className="cursor-pointer rounded-full border border-slate-200 px-5 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100"
                                                    >
                                                        Cancel
                                                    </button>
                                                    <button
                                                        type="submit"
                                                        disabled={isSubmittingOr}
                                                        className="inline-flex items-center gap-2 rounded-full bg-emerald-600 px-6 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700 disabled:opacity-60"
                                                    >
                                                        <svg
                                                            xmlns="http://www.w3.org/2000/svg"
                                                            viewBox="0 0 24 24"
                                                            fill="none"
                                                            stroke="currentColor"
                                                            strokeWidth="2.5"
                                                            strokeLinecap="round"
                                                            strokeLinejoin="round"
                                                            className="h-4 w-4"
                                                        >
                                                            <path d="M20 6 9 17l-5-5" />
                                                        </svg>
                                                        {isSubmittingOr ? 'Saving...' : formInput.staff_input?.or_no ? 'Update OR' : 'Place OR Number'}
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    ) : (
                                        <div className="space-y-3">
                                            <div className={`flex min-w-0 items-start gap-6 border-b border-slate-100 py-3 last:border-0 ${!isCashier ? 'opacity-60' : ''}`}>
                                                <ReadOnlyRow
                                                    label="OR Number"
                                                    value={formInput.staff_input?.or_no ?? 'N/A'}
                                                    valueClass="text-black-400"
                                                />
                                            </div>
                                            <div className={`flex min-w-0 items-start gap-6 border-b border-slate-100 py-3 last:border-0 ${!isCashier ? 'opacity-60' : ''}`}>
                                                <ReadOnlyRow
                                                    label="OR Date"
                                                    value={
                                                        formInput.staff_input?.or_date
                                                            ? formatDateOnly(
                                                                  formInput.staff_input.or_date,
                                                              )
                                                            : 'N/A'
                                                    }
                                                    valueClass="text-black-400"
                                                />
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </section>
                        )}

                        {/* Supporting Documents */}
                        <section className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                            <div className="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                                <h3 className="text-base font-semibold text-slate-900">
                                    Supporting Documents
                                </h3>
                                <span className="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                    {supportingDocuments.length} file(s)
                                </span>
                            </div>
                            <div className="p-4">
                                {supportingDocuments.length > 0 ? (
                                    <div className="divide-y divide-slate-100">
                                        {supportingDocuments.map((document) => (
                                            <div
                                                key={document.id}
                                                className="flex items-center justify-between px-2 py-3"
                                            >
                                                <div className="flex min-w-0 flex-1 items-center gap-2 text-sm">
                                                    <svg
                                                        xmlns="http://www.w3.org/2000/svg"
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        strokeWidth="2"
                                                        strokeLinecap="round"
                                                        strokeLinejoin="round"
                                                        className={`h-4 w-4 ${document.file_extension === 'pdf' ? 'text-rose-500' : 'text-blue-500'}`}
                                                    >
                                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                                        <path d="M14 2v6h6" />
                                                    </svg>
                                                    <TooltipProvider delayDuration={150}>
                                                        <Tooltip>
                                                            <TooltipTrigger asChild>
                                                                <a
                                                                    href={staff.documents.download.url(
                                                                        document.id,
                                                                    )}
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    title={
                                                                        document.original_filename
                                                                    }
                                                                    className="block min-w-0 max-w-[260px] truncate text-blue-600 hover:underline"
                                                                >
                                                                    {
                                                                        document.original_filename
                                                                    }
                                                                </a>
                                                            </TooltipTrigger>
                                                            <TooltipContent
                                                                side="top"
                                                                align="start"
                                                                className="max-w-sm break-all"
                                                            >
                                                                {
                                                                    document.original_filename
                                                                }
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </TooltipProvider>
                                                    <span className="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">
                                                        {document.file_extension?.toUpperCase() ??
                                                            'FILE'}
                                                    </span>
                                                </div>
                                                <div className="flex items-center gap-3">
                                                    <span className="text-xs text-slate-400">
                                                        {document.formatted_file_size ??
                                                            formatFileSize(
                                                                document.file_size,
                                                            )}
                                                    </span>
                                                    <a
                                                        href={staff.documents.download.url(
                                                            document.id,
                                                        )}
                                                        title="Download"
                                                        className="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-slate-600 transition-colors hover:bg-slate-100"
                                                    >
                                                        <svg
                                                            xmlns="http://www.w3.org/2000/svg"
                                                            viewBox="0 0 24 24"
                                                            fill="none"
                                                            stroke="currentColor"
                                                            strokeWidth="2"
                                                            strokeLinecap="round"
                                                            strokeLinejoin="round"
                                                            className="h-4 w-4"
                                                        >
                                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                                            <path d="M7 10l5 5 5-5" />
                                                            <path d="M12 15V3" />
                                                        </svg>
                                                    </a>
                                                    <button
                                                        type="button"
                                                        title="Delete"
                                                        onClick={() =>
                                                            confirmDelete(
                                                                document.id,
                                                            )
                                                        }
                                                        className="flex h-8 w-8 items-center justify-center rounded-full border border-rose-200 text-rose-500 transition-colors hover:bg-rose-50"
                                                    >
                                                        <svg
                                                            xmlns="http://www.w3.org/2000/svg"
                                                            viewBox="0 0 24 24"
                                                            fill="none"
                                                            stroke="currentColor"
                                                            strokeWidth="2"
                                                            strokeLinecap="round"
                                                            strokeLinejoin="round"
                                                            className="h-4 w-4"
                                                        >
                                                            <path d="M3 6h18" />
                                                            <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                                            <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="py-4 text-center text-sm text-slate-500">
                                        No supporting documents uploaded.
                                    </p>
                                )}
                            </div>
                        </section>
                    </div>
                </div>
            </div>
            {formInput.staff_input && (
                <div className="mx-auto mt-6 flex justify-end gap-2 sm:max-w-6xl">
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button
                                type="button"
                                className="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="2"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    className="h-4 w-4"
                                >
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                                View OP
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="2"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    className="h-3.5 w-3.5"
                                >
                                    <path d="m6 9 6 6 6-6" />
                                </svg>
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-44">
                            <DropdownMenuItem asChild>
                                <a
                                    href={`${staff.requests.viewOp.url(formInput.id)}?layout=portrait`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="cursor-pointer"
                                >
                                    Portrait (3 Pages)
                                </a>
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild>
                                <a
                                    href={`${staff.requests.viewOp.url(formInput.id)}?layout=landscape`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="cursor-pointer"
                                >
                                    Landscape (1 Page)
                                </a>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <button
                        type="button"
                        onClick={openEmailPreview}
                        className="inline-flex cursor-pointer items-center gap-1.5 rounded-full bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="2"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            className="h-4 w-4"
                        >
                            <rect width="20" height="16" x="2" y="4" rx="2" />
                            <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />
                        </svg>
                        Send to Email
                    </button>
                </div>
            )}
            {/* Email Preview / Edit Modal */}
            <Dialog
                open={isEmailPreviewOpen}
                onOpenChange={setIsEmailPreviewOpen}
            >
                <DialogContent className="sm:max-w-4xl">
                    <DialogHeader>
                        <DialogTitle>Preview Email</DialogTitle>
                        <DialogDescription>
                            Review before sending to{' '}
                            <span className="font-medium text-slate-700">
                                {formInput.email}
                            </span>
                            . Two PDF copies (portrait + landscape) will be
                            attached automatically.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-6 md:grid-cols-2">
                        <div className="space-y-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">
                                    Subject
                                </label>
                                <Input
                                    value={emailSubject}
                                    onChange={(e) =>
                                        setEmailSubject(e.target.value)
                                    }
                                />
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">
                                    Recipient Name
                                </label>
                                <Input
                                    value={emailRecipientName}
                                    onChange={(e) =>
                                        setEmailRecipientName(e.target.value)
                                    }
                                />
                                <p className="mt-1 text-xs text-slate-400">
                                    How they're greeted — edit if the name needs
                                    correcting.
                                </p>
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">
                                    Add a Note (optional)
                                </label>
                                <Textarea
                                    rows={5}
                                    placeholder="e.g. Please claim this within 5 business days."
                                    value={emailNote}
                                    onChange={(e) =>
                                        setEmailNote(e.target.value)
                                    }
                                />
                                <p className="mt-1 text-xs text-slate-400">
                                    Plain text only — no formatting needed, it's
                                    inserted as its own paragraph.
                                </p>
                            </div>
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">
                                Live Preview
                            </label>
                            <div className="overflow-hidden rounded-xl border border-slate-200">
                                <iframe
                                    title="Email preview"
                                    srcDoc={buildEmailPreviewHtml(
                                        emailRecipientName,
                                        emailNote,
                                    )}
                                    className="h-[28rem] w-full bg-white"
                                />
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <button
                            type="button"
                            onClick={() => setIsEmailPreviewOpen(false)}
                            className="cursor-pointer rounded-full border border-slate-200 px-5 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            onClick={handleConfirmSendEmail}
                            disabled={isSendingEmail}
                            className="inline-flex cursor-pointer items-center gap-2 rounded-full bg-blue-600 px-6 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700 disabled:opacity-60"
                        >
                            {isSendingEmail ? 'Sending...' : 'Confirm & Send'}
                        </button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Document Confirmation Modal */}
            {deleteTargetId !== null && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
                    <div className="w-full max-w-sm rounded-3xl bg-white p-6 shadow-xl">
                        <h3 className="text-lg font-semibold text-slate-900">
                            Delete Document
                        </h3>
                        <p className="mt-2 text-sm text-slate-600">
                            Are you sure you want to delete this document?
                        </p>
                        <p className="mt-1 text-xs font-medium text-rose-500">
                            This action cannot be undone.
                        </p>
                        <div className="mt-6 flex justify-end gap-2">
                            <button
                                type="button"
                                onClick={() => setDeleteTargetId(null)}
                                className="rounded-full border border-slate-200 px-5 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                onClick={handleDelete}
                                className="rounded-full bg-rose-600 px-5 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-rose-700"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
