import React, { useEffect } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import { flashToast } from '@/utils/flashToast';
import staff from '@/routes/staff';

interface Membership {
    member_desc: string;
}

interface PaymentDetailOption {
    payment_desc: string;
}

interface StaffInputFormInput {
    id: number;
    reference_number: string;
    full_name: string;
    email: string;
    contact_num: string;
    amount: number;
    request_type: string;
    membership: Membership | null;
    payment_detail_option: PaymentDetailOption | null;
}

interface StaffInputRecord {
    id: number;
    fundcluster_id: number;
    ref_document_id: number | null;
    ref_date: string;
    uacs_id: number;
    status: 'pending' | 'approved' | 'cancelled';
    form_input: StaffInputFormInput;
}

interface BankAccount {
    id: number;
    account_name: string;
    bank_name: string;
    account_num: string;
    fund_cluster: string;
}

interface DocumentItem {
    id: number;
    original_filename: string;
    file_extension: string;
}

interface Uacs {
    id: number;
    object_code: string;
    account_title: string;
}

interface FlashProps {
    success?: string;
    error?: string;
    warning?: string;
}

interface PageProps {
    staffInput: StaffInputRecord;
    bankAccounts: BankAccount[];
    documents: DocumentItem[];
    uacsList: Uacs[];
    flash?: FlashProps;
}

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);
};

export default function EditRequest() {
    const { staffInput, bankAccounts, documents, uacsList, flash } = usePage()
        .props as unknown as PageProps;
    const formInput = staffInput.form_input;

    useEffect(() => {
        if (flash?.success) flashToast('success', flash.success);
        if (flash?.error) flashToast('error', flash.error);
        if (flash?.warning) flashToast('warning', flash.warning);
    }, [flash]);

    const { data, setData, put, processing, errors } = useForm({
        form_input_id: String(formInput.id),
        fundcluster_id: String(staffInput.fundcluster_id ?? ''),
        ref_document_id: staffInput.ref_document_id
            ? String(staffInput.ref_document_id)
            : '',
        ref_date: staffInput.ref_date ? staffInput.ref_date.slice(0, 10) : '',
        uacs_id: String(staffInput.uacs_id ?? ''),
        status: staffInput.status,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(staff.requests.update.url(staffInput.id), {
            preserveScroll: true,
            onError: () => {
                toast.error('Please check the form for errors.');
            },
        });
    };

    return (
        <div className="min-h-screen bg-slate-50 px-4 py-8">
            <div className="mx-auto max-w-6xl">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h2 className="text-2xl font-bold text-slate-900">
                            Edit Processed Request
                        </h2>
                        <p className="text-sm text-slate-500">
                            Reference:{' '}
                            <span className="font-semibold text-blue-600">
                                {formInput.reference_number}
                            </span>
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={() => window.history.back()}
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
                    </button>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="lg:col-span-2">
                        <section className="rounded-3xl border border-slate-200 bg-white shadow-sm">
                            <div className="border-b border-slate-200 px-6 py-4">
                                <h3 className="text-base font-semibold text-slate-900">
                                    Staff Processing Form
                                </h3>
                            </div>
                            <div className="p-6">
                                <form onSubmit={handleSubmit}>
                                    <input
                                        type="hidden"
                                        name="form_input_id"
                                        value={data.form_input_id}
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
                                                errors.fundcluster_id
                                                    ? 'border-rose-400'
                                                    : 'border-slate-200'
                                            }`}
                                            value={data.fundcluster_id}
                                            onChange={(e) =>
                                                setData(
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
                                        {errors.fundcluster_id && (
                                            <p className="mt-1 text-xs text-rose-500">
                                                {errors.fundcluster_id}
                                            </p>
                                        )}
                                    </div>

                                    <div className="mb-4">
                                        <label className="mb-1 block text-sm font-medium text-slate-700">
                                            Reference Document
                                        </label>
                                        <select
                                            className={`w-full rounded-xl border px-4 py-2 text-sm text-slate-700 outline-none ${
                                                errors.ref_document_id
                                                    ? 'border-rose-400'
                                                    : 'border-slate-200'
                                            }`}
                                            value={data.ref_document_id}
                                            onChange={(e) =>
                                                setData(
                                                    'ref_document_id',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            <option value="">
                                                Select Reference Document
                                                (Optional)
                                            </option>
                                            {documents.map((document) => (
                                                <option
                                                    key={document.id}
                                                    value={document.id}
                                                >
                                                    {document.original_filename}
                                                </option>
                                            ))}
                                        </select>
                                        {errors.ref_document_id && (
                                            <p className="mt-1 text-xs text-rose-500">
                                                {errors.ref_document_id}
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
                                                errors.ref_date
                                                    ? 'border-rose-400'
                                                    : 'border-slate-200'
                                            }`}
                                            value={data.ref_date}
                                            onChange={(e) =>
                                                setData(
                                                    'ref_date',
                                                    e.target.value,
                                                )
                                            }
                                            required
                                        />
                                        {errors.ref_date && (
                                            <p className="mt-1 text-xs text-rose-500">
                                                {errors.ref_date}
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
                                                errors.uacs_id
                                                    ? 'border-rose-400'
                                                    : 'border-slate-200'
                                            }`}
                                            value={data.uacs_id}
                                            onChange={(e) =>
                                                setData(
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
                                        {errors.uacs_id && (
                                            <p className="mt-1 text-xs text-rose-500">
                                                {errors.uacs_id}
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
                                                errors.status
                                                    ? 'border-rose-400'
                                                    : 'border-slate-200'
                                            }`}
                                            value={data.status}
                                            onChange={(e) =>
                                                setData(
                                                    'status',
                                                    e.target
                                                        .value as typeof data.status,
                                                )
                                            }
                                            required
                                        >
                                            <option value="pending">
                                                Pending
                                            </option>
                                            <option value="approved">
                                                Approved
                                            </option>
                                            <option value="cancelled">
                                                Cancelled
                                            </option>
                                        </select>
                                        {errors.status && (
                                            <p className="mt-1 text-xs text-rose-500">
                                                {errors.status}
                                            </p>
                                        )}
                                    </div>

                                    <div className="flex justify-end gap-2">
                                        <button
                                            type="submit"
                                            disabled={processing}
                                            className="inline-flex items-center gap-2 rounded-full bg-amber-500 px-6 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-amber-600 disabled:opacity-60"
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
                                                <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" />
                                            </svg>
                                            {processing
                                                ? 'Saving...'
                                                : 'Save Changes'}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </section>
                    </div>

                    <div>
                        <section className="rounded-3xl border border-slate-200 bg-white shadow-sm">
                            <div className="border-b border-slate-200 px-6 py-4">
                                <h3 className="text-base font-semibold text-slate-900">
                                    Request Information
                                </h3>
                            </div>
                            <div className="space-y-3 p-6 text-sm">
                                <div>
                                    <p className="font-medium text-slate-500">
                                        Name:
                                    </p>
                                    <p className="text-slate-800">
                                        {formInput.full_name}
                                    </p>
                                </div>
                                <div>
                                    <p className="font-medium text-slate-500">
                                        Email:
                                    </p>
                                    <p className="text-slate-800">
                                        {formInput.email}
                                    </p>
                                </div>
                                <div>
                                    <p className="font-medium text-slate-500">
                                        Contact:
                                    </p>
                                    <p className="text-slate-800">
                                        {formInput.contact_num}
                                    </p>
                                </div>
                                <div>
                                    <p className="font-medium text-slate-500">
                                        Amount:
                                    </p>
                                    <p className="font-bold text-blue-600">
                                        {formatCurrency(formInput.amount)}
                                    </p>
                                </div>
                                <div>
                                    <p className="font-medium text-slate-500">
                                        Request Type:
                                    </p>
                                    <p className="text-slate-800">
                                        {formInput.request_type}
                                    </p>
                                </div>
                                <div>
                                    <p className="font-medium text-slate-500">
                                        Membership:
                                    </p>
                                    <p className="text-slate-800">
                                        {formInput.membership?.member_desc ??
                                            'N/A'}
                                    </p>
                                </div>
                                <div>
                                    <p className="font-medium text-slate-500">
                                        Payment Option:
                                    </p>
                                    <p className="text-slate-800">
                                        {formInput.payment_detail_option
                                            ?.payment_desc ?? 'N/A'}
                                    </p>
                                </div>
                                <div>
                                    <p className="font-medium text-slate-500">
                                        Documents:
                                    </p>
                                    <div className="mt-1 space-y-1">
                                        {documents.map((document) => (
                                            <div
                                                key={document.id}
                                                className="flex items-center gap-1 text-sm"
                                            >
                                                <svg
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    strokeWidth="2"
                                                    strokeLinecap="round"
                                                    strokeLinejoin="round"
                                                    className="h-4 w-4 text-slate-400"
                                                >
                                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                                    <path d="M14 2v6h6" />
                                                </svg>
                                                <a
                                                    href={staff.documents.download.url(
                                                        document.id,
                                                    )}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="text-blue-600 hover:underline"
                                                >
                                                    {document.original_filename}
                                                </a>
                                                <span className="inline-flex rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-semibold text-sky-800">
                                                    {document.file_extension}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </div>
    );
}
