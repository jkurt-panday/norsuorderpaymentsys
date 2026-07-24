import React, { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import staff from '@/routes/staff';

interface Membership {
  member_code: string;
  member_desc: string;
}

interface PaymentDetailOption {
  payment_desc: string;
}

interface BankAccount {
  account_name: string;
  bank_name: string;
  account_num: string;
}

interface Uacs {
  object_code: string;
  account_title: string;
}

interface ReferenceDocument {
  id: number;
  original_filename: string;
}

interface StaffInput {
  id: number;
  status: 'pending' | 'approved' | 'cancelled' | 'unprocessed';
  ref_date: string | null;
  bankAccount: BankAccount | null;
  uacs: Uacs | null;
  referenceDocument: ReferenceDocument | null;
  created_at: string;
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
  paymentDetailOption: PaymentDetailOption | null;
  staffInput: StaffInput | null;
  supportingDocuments?: SupportingDocument[];
}

interface PageProps {
  formInput: FormInput;
}

const statusBadgeClass = (status: string) => {
  switch (status) {
    case 'approved':
      return 'bg-emerald-100 text-emerald-800';
    case 'cancelled':
      return 'bg-rose-100 text-rose-800';
    case 'pending':
    case 'unprocessed':
    default:
      return 'bg-amber-100 text-amber-900';
  }
};

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
  const { formInput } = usePage().props as unknown as PageProps;
  const [deleteTargetId, setDeleteTargetId] = useState<number | null>(null);

  const supportingDocuments: SupportingDocument[] =
    formInput.supportingDocuments ?? (formInput as any).supporting_documents ?? [];

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

  return (
    <div className="min-h-screen bg-slate-50 px-4 py-8">
      <div className="mx-auto max-w-6xl">
        <div className="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div>
            <h2 className="text-2xl font-bold text-slate-900">Request Details</h2>
            <p className="text-sm text-slate-500">
              Reference:{' '}
              <span className="font-semibold text-blue-600">{formInput.reference_number}</span>
            </p>
          </div>
          <div className="flex gap-2">
            <Link
              href={staff.requests.index.url()}
              className="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-5 py-2 text-sm font-medium text-slate-700 shadow-sm transition-colors hover:bg-slate-100"
            >
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4">
                <path d="M19 12H5M12 19l-7-7 7-7" />
              </svg>
              Back
            </Link>
          </div>
        </div>

        <div className="grid gap-6 lg:grid-cols-2">
          <div className="space-y-6">
            {/* Personal Information */}
            <section className="rounded-3xl border border-slate-200 bg-white shadow-sm">
              <div className="flex items-center gap-2 border-b border-slate-200 px-6 py-4">
                <h3 className="text-base font-semibold text-slate-900">Personal / Office Information</h3>
              </div>
              <div className="p-6">
                <table className="w-full text-sm">
                  <tbody className="divide-y divide-slate-100">
                    <tr>
                      <th className="w-2/5 py-2 text-left font-medium text-slate-500">First Name / Office</th>
                      <td className="py-2 text-slate-800">{formInput.firstname_or_office}</td>
                    </tr>
                    <tr>
                      <th className="py-2 text-left font-medium text-slate-500">Middle Name / Project</th>
                      <td className="py-2 text-slate-800">{formInput.middlename_or_project ?? 'N/A'}</td>
                    </tr>
                    <tr>
                      <th className="py-2 text-left font-medium text-slate-500">Last Name / Agency</th>
                      <td className="py-2 text-slate-800">{formInput.lastname_or_agency}</td>
                    </tr>
                    <tr>
                      <th className="py-2 text-left font-medium text-slate-500">Office / College</th>
                      <td className="py-2 text-slate-800">{formInput.office_or_college}</td>
                    </tr>
                    <tr>
                      <th className="py-2 text-left font-medium text-slate-500">Position / Designation</th>
                      <td className="py-2 text-slate-800">{formInput.position_or_designation}</td>
                    </tr>
                    <tr>
                      <th className="py-2 text-left font-medium text-slate-500">Email</th>
                      <td className="py-2">
                        <a href={`mailto:${formInput.email}`} className="text-blue-600 hover:underline">
                          {formInput.email}
                        </a>
                      </td>
                    </tr>
                    <tr>
                      <th className="py-2 text-left font-medium text-slate-500">Contact Number</th>
                      <td className="py-2 text-slate-800">{formInput.contact_num}</td>
                    </tr>
                    <tr>
                      <th className="py-2 text-left font-medium text-slate-500">Address</th>
                      <td className="py-2 text-slate-800">{formInput.address}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </section>

            {/* Payment Information */}
            <section className="rounded-3xl border border-slate-200 bg-white shadow-sm">
              <div className="flex items-center gap-2 border-b border-slate-200 px-6 py-4">
                <h3 className="text-base font-semibold text-slate-900">Payment Information</h3>
              </div>
              <div className="p-6">
                <table className="w-full text-sm">
                  <tbody className="divide-y divide-slate-100">
                    <tr>
                      <th className="w-2/5 py-2 text-left font-medium text-slate-500">Request Type</th>
                      <td className="py-2">
                        <span className="inline-flex rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-800">
                          {formInput.request_type}
                        </span>
                      </td>
                    </tr>
                    <tr>
                      <th className="py-2 text-left font-medium text-slate-500">Amount</th>
                      <td className="py-2 font-bold text-blue-600">{formatCurrency(formInput.amount)}</td>
                    </tr>
                    <tr>
                      <th className="py-2 text-left font-medium text-slate-500">Membership</th>
                      <td className="py-2 text-slate-800">
                        {formInput.membership?.member_code ?? 'N/A'} - {formInput.membership?.member_desc ?? ''}
                      </td>
                    </tr>
                    <tr>
                      <th className="py-2 text-left font-medium text-slate-500">Payment Option</th>
                      <td className="py-2 text-slate-800">
                        {formInput.paymentDetailOption?.payment_desc ?? 'N/A'}
                      </td>
                    </tr>
                    <tr>
                      <th className="py-2 text-left font-medium text-slate-500">Date Submitted</th>
                      <td className="py-2 text-slate-800">{formatDateTime(formInput.created_at)}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </section>
          </div>

          <div className="space-y-6">
            {/* Staff Processing Information */}
            <section className="rounded-3xl border border-slate-200 bg-white shadow-sm">
              <div className="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <h3 className="text-base font-semibold text-slate-900">Staff Processing</h3>
                {formInput.staffInput && (
                  <span
                    className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${statusBadgeClass(formInput.staffInput.status)}`}
                  >
                    {formInput.staffInput.status.charAt(0).toUpperCase() + formInput.staffInput.status.slice(1)}
                  </span>
                )}
              </div>
              <div className="p-6">
                {formInput.staffInput ? (
                  <table className="w-full text-sm">
                    <tbody className="divide-y divide-slate-100">
                      <tr>
                        <th className="w-2/5 py-2 text-left font-medium text-slate-500">Fund Cluster</th>
                        <td className="py-2 text-slate-800">
                          {formInput.staffInput.bankAccount?.account_name ?? 'N/A'}
                        </td>
                      </tr>
                      <tr>
                        <th className="py-2 text-left font-medium text-slate-500">Bank</th>
                        <td className="py-2 text-slate-800">
                          {formInput.staffInput.bankAccount?.bank_name ?? 'N/A'}
                        </td>
                      </tr>
                      <tr>
                        <th className="py-2 text-left font-medium text-slate-500">Account Number</th>
                        <td className="py-2 text-slate-800">
                          {formInput.staffInput.bankAccount?.account_num ?? 'N/A'}
                        </td>
                      </tr>
                      <tr>
                        <th className="py-2 text-left font-medium text-slate-500">Reference Date</th>
                        <td className="py-2 text-slate-800">
                          {formInput.staffInput.ref_date ? formatDateOnly(formInput.staffInput.ref_date) : 'N/A'}
                        </td>
                      </tr>
                      <tr>
                        <th className="py-2 text-left font-medium text-slate-500">UACS</th>
                        <td className="py-2 text-slate-800">
                          {formInput.staffInput.uacs?.object_code ?? 'N/A'} - {formInput.staffInput.uacs?.account_title ?? ''}
                        </td>
                      </tr>
                      <tr>
                        <th className="py-2 text-left font-medium text-slate-500">Reference Document</th>
                        <td className="py-2">
                          {formInput.staffInput.referenceDocument ? (
                            <a
                              href={staff.documents.download.url(formInput.staffInput.referenceDocument.id)}
                              target="_blank"
                              rel="noopener noreferrer"
                              className="inline-flex items-center gap-1 text-blue-600 hover:underline"
                            >
                              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                <path d="M14 2v6h6" />
                              </svg>
                              {formInput.staffInput.referenceDocument.original_filename}
                            </a>
                          ) : (
                            'N/A'
                          )}
                        </td>
                      </tr>
                      <tr>
                        <th className="py-2 text-left font-medium text-slate-500">Processed By</th>
                        <td className="py-2 text-slate-800">Staff User</td>
                      </tr>
                      <tr>
                        <th className="py-2 text-left font-medium text-slate-500">Processed Date</th>
                        <td className="py-2 text-slate-800">
                          {formatDateTime(formInput.staffInput.created_at)}
                        </td>
                      </tr>
                    </tbody>
                  </table>
                ) : (
                  <div className="py-6 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="mx-auto mb-2 h-8 w-8 text-amber-400">
                      <circle cx="12" cy="12" r="10" />
                      <path d="M12 6v6l4 2" />
                    </svg>
                    <p className="mb-4 text-sm text-slate-500">This request has not been processed yet.</p>
                    <Link
                      href={staff.requests.process.url(formInput.id)}
                      className="inline-flex items-center gap-2 rounded-full bg-blue-600 px-5 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4">
                        <path d="M20 6 9 17l-5-5" />
                      </svg>
                      Process Now
                    </Link>
                  </div>
                )}
              </div>
            </section>

            {/* Supporting Documents */}
            <section className="rounded-3xl border border-slate-200 bg-white shadow-sm">
              <div className="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <h3 className="text-base font-semibold text-slate-900">Supporting Documents</h3>
                <span className="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                  {supportingDocuments.length} file(s)
                </span>
              </div>
              <div className="p-4">
                {supportingDocuments.length > 0 ? (
                  <div className="divide-y divide-slate-100">
                    {supportingDocuments.map((document) => (
                      <div key={document.id} className="flex items-center justify-between px-2 py-3">
                        <div className="flex items-center gap-2 text-sm">
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
                          <a
                            href={staff.documents.download.url(document.id)}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-blue-600 hover:underline"
                          >
                            {document.original_filename}
                          </a>
                          <span className="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">
                            {document.file_extension?.toUpperCase() ?? 'FILE'}
                          </span>
                        </div>
                        <div className="flex items-center gap-3">
                          <span className="text-xs text-slate-400">{document.formatted_file_size ?? formatFileSize(document.file_size)}</span>
                          <a
                            href={staff.documents.download.url(document.id)}
                            title="Download"
                            className="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-slate-600 transition-colors hover:bg-slate-100"
                          >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4">
                              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                              <path d="M7 10l5 5 5-5" />
                              <path d="M12 15V3" />
                            </svg>
                          </a>
                          <button
                            type="button"
                            title="Delete"
                            onClick={() => confirmDelete(document.id)}
                            className="flex h-8 w-8 items-center justify-center rounded-full border border-rose-200 text-rose-500 transition-colors hover:bg-rose-50"
                          >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4">
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
                  <p className="py-4 text-center text-sm text-slate-500">No supporting documents uploaded.</p>
                )}
              </div>
            </section>
          </div>
        </div>
      </div>

      {/* Delete Document Confirmation Modal */}
      {deleteTargetId !== null && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
          <div className="w-full max-w-sm rounded-3xl bg-white p-6 shadow-xl">
            <h3 className="text-lg font-semibold text-slate-900">Delete Document</h3>
            <p className="mt-2 text-sm text-slate-600">Are you sure you want to delete this document?</p>
            <p className="mt-1 text-xs font-medium text-rose-500">This action cannot be undone.</p>
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