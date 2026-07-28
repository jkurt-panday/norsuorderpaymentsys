import React from 'react';
import { usePage } from '@inertiajs/react';
import RequestForm, { type SectionDef } from '@/components/RequestForm';
import { Badge } from '@/components/ui/badge';
import staff from '@/routes/staff';

// ============ TYPE DEFINITIONS ============
interface BankAccountInfo {
  id: number;
  account_name: string;
  bank_name: string;
  account_num: string;
}

interface Uacs {
  id: number;
  object_code: string;
  account_title: string;
}

interface SupportingDocument {
  id: number;
  original_filename: string;
}

interface StaffInput {
  id: number;
  form_input_id: number;
  fundcluster_id: number | null;
  ref_document_id: number | null;
  ref_date: string;
  uacs_id: number | null;
  status: 'pending' | 'approved' | 'cancelled';
  created_at: string;
  updated_at: string;
  formInput: {
    reference_number: string;
  };
}

interface PageProps {
  staffInput: StaffInput;
  bankAccounts: BankAccountInfo[];
  uacsList: Uacs[];
  documents: SupportingDocument[];
}

// ============ HELPERS ============
const getStatusColor = (status: string): string => {
  switch (status) {
    case 'approved':
      return 'bg-emerald-500 hover:bg-emerald-600 text-white';
    case 'cancelled':
      return 'bg-red-500 hover:bg-red-600 text-white';
    case 'pending':
      return 'bg-amber-500 hover:bg-amber-600 text-white';
    default:
      return 'bg-slate-500 hover:bg-slate-600 text-white';
  }
};

const formatDateTime = (value: string) =>
  new Date(value).toLocaleString('en-US', {
    timeZone: 'Asia/Manila',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });

// ============ PAGE ============
const EditRequest: React.FC = () => {
  const { staffInput, bankAccounts, uacsList, documents } = usePage().props as unknown as PageProps;

  const sections: SectionDef[] = [
    {
      title: 'Update Staff Processing',
      description: `Reference: ${staffInput.formInput.reference_number}`,
      fields: [
        {
          name: 'fundcluster_id',
          label: 'Bank Account',
          required: true,
          type: 'select',
          colSpan: 'full',
          options: bankAccounts.map((account) => ({
            value: String(account.id),
            label: `${account.account_name} - ${account.bank_name} (${account.account_num})`,
          })),
        },
        {
          name: 'ref_document_id',
          label: 'Reference Document',
          type: 'select',
          placeholder: 'Select reference document (optional)',
          colSpan: 'full',
          options: documents.map((document) => ({
            value: String(document.id),
            label: document.original_filename,
          })),
        },
        {
          name: 'ref_date',
          label: 'Reference Date',
          required: true,
          type: 'date',
        },
        {
          name: 'uacs_id',
          label: 'UACS',
          required: true,
          type: 'select',
          options: uacsList.map((uacs) => ({
            value: String(uacs.id),
            label: `${uacs.object_code} - ${uacs.account_title}`,
          })),
        },
        {
          name: 'status',
          label: 'Status',
          required: true,
          type: 'select',
          colSpan: 'full',
          options: [
            { value: 'pending', label: 'Pending' },
            { value: 'approved', label: 'Approved' },
            { value: 'cancelled', label: 'Cancelled' },
          ],
        },
      ],
    },
  ];

  const initialData: Record<string, string> = {
    // Hidden field — not rendered as an input, but still submitted with the form
    form_input_id: String(staffInput.form_input_id),
    fundcluster_id: staffInput.fundcluster_id ? String(staffInput.fundcluster_id) : '',
    ref_document_id: staffInput.ref_document_id ? String(staffInput.ref_document_id) : '',
    ref_date: staffInput.ref_date ? staffInput.ref_date.slice(0, 10) : '',
    uacs_id: staffInput.uacs_id ? String(staffInput.uacs_id) : '',
    status: staffInput.status,
  };

  const sidebar = (
    <div>
      <h3 className="text-base font-semibold text-slate-900">Current Status</h3>
      <div className="mt-4 flex flex-col items-center gap-2 py-2 text-center">
        <Badge className={`${getStatusColor(staffInput.status)} px-4 py-1.5 text-sm capitalize`}>
          {staffInput.status}
        </Badge>
        <p className="text-xs text-slate-500">
          Last updated: {formatDateTime(staffInput.updated_at)}
        </p>
      </div>
      <div className="mt-4 space-y-1.5 border-t border-slate-200 pt-4 text-xs text-slate-500">
        <p>
          <span className="font-medium text-slate-700">Created:</span>{' '}
          {formatDateTime(staffInput.created_at)}
        </p>
        <p>
          <span className="font-medium text-slate-700">Form Input ID:</span> #{staffInput.form_input_id}
        </p>
      </div>
    </div>
  );

  return (
    <RequestForm
      title={`Edit Processing - ${staffInput.formInput.reference_number}`}
      backHref={staff.requests.show.url(staffInput.form_input_id)}
      sections={sections}
      initialData={initialData}
      submitUrl={staff.requests.update.url(staffInput.id)}
      method="put"
      submitLabel="Update Processing"
      processingLabel="Updating..."
      sidebar={sidebar}
    />
  );
};

export default EditRequest;