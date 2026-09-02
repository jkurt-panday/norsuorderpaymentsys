import { Link, router, usePage } from '@inertiajs/react';
import { Inbox } from 'lucide-react';
import React, { useState, useCallback, useEffect } from 'react';
import RequestTable, { StatusBadge } from '@/components/RequestTable';
import type { ColumnDef, PaginatedData } from '@/components/RequestTable';
import staff from '@/routes/staff';
import { flashToast } from '@/utils/flashToast';

interface StaffInput {
    id: number;
    status: 'pending' | 'approved' | 'cancelled' | 'unprocessed';
}

interface FormInput {
    id: number;
    reference_number: string;
    firstname_or_office: string;
    middlename_or_project: string | null;
    lastname_or_agency: string;
    email: string;
    amount: number;
    membership: {
        member_code: string;
    } | null;
    // Was `staffInput` — this endpoint now returns Laravel/Eloquent's
    // default snake_case relation key, matching the database directly.
    staff_input: StaffInput | null;
    created_at: string;
}

interface Filters {
    search: string;
    status: string;
    date_from: string;
    date_to: string;
}

interface FlashProps {
    success?: string;
    error?: string;
    warning?: string;
}

interface PageProps {
    formInputs: PaginatedData<FormInput>;
    filters: Filters;
    flash?: FlashProps;
}

const STATUS_TO_COLOR: Record<string, string> = {
    approved: 'green',
    processed: 'blue',
    paid: 'purple',
    cancelled: 'red',
    pending: 'orange',
    unprocessed: 'grey',
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleString('en-US', {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: 'short',
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

// full_name is no longer pre-built by the backend (that was a manual
// camelCase-transform artifact) — it's just the three name columns joined,
// so it's cheap to compute here directly from the raw snake_case fields.
const formatFullName = (row: FormInput) => {
    return [
        row.firstname_or_office,
        row.middlename_or_project,
        row.lastname_or_agency,
    ]
        .filter(Boolean)
        .join(' ');
};

const ManageRequests: React.FC = () => {
    const { formInputs, filters, flash } = usePage()
        .props as unknown as PageProps;

    useEffect(() => {
        if (flash?.success) {
            flashToast('success', flash.success);
        }

        if (flash?.error) {
            flashToast('error', flash.error);
        }

        if (flash?.warning) {
            flashToast('warning', flash.warning);
        }
    }, [flash]);

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
            },
        );
    }, [search, status, dateFrom, dateTo]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilters();
    };

    const handleReset = () => {
        setSearch('');
        setStatus('');
        setDateFrom('');
        setDateTo('');
        router.get(
            staff.requests.index.url(),
            {},
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const handlePageChange = (url: string) => {
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    const columns: ColumnDef<FormInput>[] = [
        {
            header: 'Reference #',
            sortable: 'reference_number',
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
        {
            header: 'Name',
            width: '180px',
            sortable: 'firstname_or_office',
            render: (row) => formatFullName(row),
        },
        {
            header: 'Email',
            width: '220px',
            sortable: 'email',
            render: (row) => row.email,
        },
        {
            header: 'Amount',
            sortable: 'amount',
            width: '110px',
            align: 'right',
            className: 'tabular-nums',
            render: (row) => formatCurrency(row.amount),
        },
        {
            header: 'Membership',
            sortable: 'membership_id',
            width: '130px',
            render: (row) => row.membership?.member_code ?? 'N/A',
        },
        {
            header: 'Status',
            sortable: 'status',
            width: '130px',
            render: (row) => {
                const currentStatus = row.staff_input?.status ?? 'unprocessed';

                return (
                    <StatusBadge
                        label={
                            currentStatus.charAt(0).toUpperCase() +
                            currentStatus.slice(1)
                        }
                        color={STATUS_TO_COLOR[currentStatus]}
                    />
                );
            },
        },
        {
            header: 'Date Submitted',
            sortable: 'created_at',
            width: '200px',
            render: (row) => formatDate(row.created_at),
            className: 'whitespace-nowrap text-slate-600',
        },
    ];

    // Only the View action remains — Process and Edit used to live here as
    // separate buttons, but that functionality now lives inside the show
    // page itself, so there's nothing left for this column to branch on.
    const renderActions = (row: FormInput) => (
        <div className="inline-flex overflow-hidden rounded-md shadow-sm">
            <Link
                href={staff.requests.show.url(row.id)}
                title="View"
                className="flex h-8 w-8 items-center justify-center bg-cyan-400 text-white transition-colors hover:bg-cyan-500"
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
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z" />
                    <circle cx="12" cy="12" r="3" />
                </svg>
            </Link>
        </div>
    );

    return (
        <RequestTable<FormInput>
            title="Order of Payment Requests"
            columns={columns}
            resource={formInputs}
            resourceKey="formInputs"
            pollInterval={15000}
            renderActions={renderActions}
            actionsWidth="60px"
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
                {
                    value: 'unprocessed',
                    label: 'Unprocessed',
                    color: 'bg-blue-500',
                },
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
