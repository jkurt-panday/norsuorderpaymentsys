import React, { useState, useCallback, useEffect } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { Inbox } from 'lucide-react';
import staff from '@/routes/staff';
import RequestTable, { type ColumnDef, type PaginatedData } from '@/components/RequestTable';
import { Badge } from '@/components/ui/badge';

interface StaffInput {
  id: number;
  status: 'pending' | 'approved' | 'cancelled' | 'unprocessed';
}

interface FormInput {
  id: number;
  reference_number: string;
  full_name: string;
  email: string;
  amount: number;
  membership: {
    member_code: string;
  } | null;
  staffInput: StaffInput | null;
  created_at: string;
}

interface Filters {
  search: string;
  status: string;
  date_from: string;
  date_to: string;
}

interface PageProps {
  formInputs: PaginatedData<FormInput>;
  filters: Filters;
}

// Status badge colors - MATCH THESE with the dropdown colors below
const getStatusColor = (status: string): string => {
  switch (status) {
    case 'approved':
      return 'bg-emerald-500 hover:bg-emerald-600 text-white';
    case 'cancelled':
      return 'bg-red-500 hover:bg-red-600 text-white';
    case 'pending':
      return 'bg-amber-500 hover:bg-amber-600 text-white';
    case 'unprocessed':
      return 'bg-blue-500 hover:bg-blue-600 text-white'; // Blue badge
    default:
      return 'bg-slate-500 hover:bg-slate-600 text-white';
  }
};

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleString('en-US', {
    timeZone: 'Asia/Manila',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
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

const ManageRequests: React.FC = () => {
  const { formInputs, filters } = usePage().props as unknown as PageProps;

  const [search, setSearch] = useState(filters.search || '');
  const [status, setStatus] = useState(filters.status || '');
  const [dateFrom, setDateFrom] = useState(filters.date_from || '');
  const [dateTo, setDateTo] = useState(filters.date_to || '');

  const applyFilters = useCallback(() => {
    router.get(
      staff.requests.index.url(),
      {
        search: search || undefined,
        status: status || undefined,
        date_from: dateFrom || undefined,
        date_to: dateTo || undefined,
      },
      {
        preserveState: true,
        preserveScroll: true,
      }
    );
  }, [search, status, dateFrom, dateTo]);

  useEffect(() => {
    const timer = setTimeout(() => {
      if (search !== filters.search) {
        applyFilters();
      }
    }, 500);

    return () => clearTimeout(timer);
  }, [search, filters.search, applyFilters]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    applyFilters();
  };

  const handleReset = () => {
    setSearch('');
    setStatus('');
    setDateFrom('');
    setDateTo('');
    router.get(staff.requests.index.url(), {}, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const handlePageChange = (url: string) => {
    router.get(url, {}, { preserveState: true, preserveScroll: true });
  };

  const columns: ColumnDef<FormInput>[] = [
    {
      header: 'Reference #',
      width: '160px',
      render: (row) => (
        <Link
          href={staff.requests.show.url(row.id)}
          className="truncate font-semibold text-blue-600 hover:underline"
        >
          {row.reference_number}
        </Link>
      ),
    },
    { header: 'Name', width: '180px', render: (row) => row.full_name },
    { header: 'Email', width: '220px', render: (row) => row.email },
    {
      header: 'Amount',
      width: '110px',
      align: 'right',
      render: (row) => formatCurrency(row.amount),
    },
    { header: 'Membership', width: '130px', render: (row) => row.membership?.member_code ?? 'N/A' },
    {
      header: 'Status',
      width: '130px',
      render: (row) => {
        const currentStatus = row.staffInput?.status ?? 'unprocessed';
        return (
          <Badge variant="default" className={`${getStatusColor(currentStatus)} capitalize`}>
            {currentStatus}
          </Badge>
        );
      },
    },
    {
      header: 'Date Submitted',
      width: '200px',
      render: (row) => formatDate(row.created_at),
      className: 'whitespace-nowrap text-slate-600',
    },
  ];

  // ---- Custom actions: View + (Process or Edit), no delete ----
  const renderActions = (row: FormInput) => (
    <div className="inline-flex overflow-hidden rounded-full shadow-sm">
      <Link
        href={staff.requests.show.url(row.id)}
        title="View"
        className="flex h-8 w-10 items-center justify-center bg-cyan-400 text-white transition-colors hover:bg-cyan-500"
      >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4">
          <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z" />
          <circle cx="12" cy="12" r="3" />
        </svg>
      </Link>
      {!row.staffInput ? (
        <Link
          href={staff.requests.process.url(row.id)}
          title="Process"
          className="flex h-8 w-10 items-center justify-center bg-blue-600 text-white transition-colors hover:bg-blue-700"
        >
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4">
            <path d="M20 6 9 17l-5-5" />
          </svg>
        </Link>
      ) : (
        <Link
          href={staff.requests.edit.url(row.staffInput.id)}
          title="Edit"
          className="flex h-8 w-10 items-center justify-center bg-amber-500 text-white transition-colors hover:bg-amber-600"
        >
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4">
            <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" />
          </svg>
        </Link>
      )}
    </div>
  );

  return (
    <RequestTable<FormInput>
      title="Order of Payment Requests"
      columns={columns}
      resource={formInputs}
      renderActions={renderActions}
      actionsWidth="110px"
      emptyIcon={Inbox}
      emptyMessage="No requests found"
      onPageChange={handlePageChange}
      search={search}
      onSearchChange={setSearch}
      searchPlaceholder="Search by reference, name, or email"
      status={status}
      onStatusChange={setStatus}
      statusOptions={[
        { value: 'pending', label: 'Pending', color: 'orange' },
        { value: 'approved', label: 'Approved', color: 'green' },
        { value: 'cancelled', label: 'Cancelled', color: 'red' },
        { value: 'unprocessed', label: 'Unprocessed', color: 'bg-blue-500' }, // Blue dot
      ]}
      dateFrom={dateFrom}
      onDateFromChange={setDateFrom}
      dateTo={dateTo}
      onDateToChange={setDateTo}
      onFilterSubmit={handleSubmit}
      onFilterReset={handleReset}
    />
  );
};

export default ManageRequests;