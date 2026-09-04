import { Head, Link, router } from '@inertiajs/react';
import { Inbox } from 'lucide-react';
import React, { useState, useCallback } from 'react';
import RequestTable, { StatusBadge } from '@/components/RequestTable';
import type { ColumnDef, PaginatedData } from '@/components/RequestTable';
import cashier from '@/routes/cashier';

interface FormInput {
    reference_number: string;
    firstname_or_office: string;
    middlename_or_project: string | null;
    lastname_or_agency: string;
    email: string;
    amount: string | number;
}

interface PaymentRequest {
    id: number;
    status: 'processed' | 'paid';
    or_no: string | null;
    or_date: string | null;
    created_at: string;
    form_input: FormInput;
}

interface Filters {
    search: string;
    status: string;
    date_from: string;
    date_to: string;
}

interface Props {
    requests: PaginatedData<PaymentRequest>;
    filters: Filters;
}

const money = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
});

const STATUS_TO_COLOR: Record<string, string> = {
    processed: 'orange',
    paid: 'dark-green',
};

function fullName(formInput: FormInput) {
    return [
        formInput.firstname_or_office,
        formInput.middlename_or_project,
        formInput.lastname_or_agency,
    ]
        .filter(Boolean)
        .join(' ');
}

const CashierRequestsIndex: React.FC<Props> = ({ requests, filters }) => {
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');
    const [dateFrom, setDateFrom] = useState(filters.date_from || '');
    const [dateTo, setDateTo] = useState(filters.date_to || '');

    const applyFilters = useCallback(() => {
        router.get(
            cashier.requests.index.url(),
            {
                search: search || undefined,
                status: status || undefined,
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, [search, status, dateFrom, dateTo]);

    const handleSubmit = (event: React.FormEvent) => {
        event.preventDefault();
        applyFilters();
    };

    const handleReset = () => {
        setSearch('');
        setStatus('');
        setDateFrom('');
        setDateTo('');
        router.get(
            cashier.requests.index.url(),
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

    const columns: ColumnDef<PaymentRequest>[] = [
        {
            header: 'Reference #',
            sortable: 'reference_number',
            width: '170px',
            render: (row) => (
                <Link
                    href={cashier.requests.show.url(row.id)}
                    className="block truncate font-semibold text-blue-600 hover:underline"
                >
                    {row.form_input.reference_number}
                </Link>
            ),
        },
        {
            header: 'Payer',
            sortable: 'firstname_or_office',
            width: '220px',
            render: (row) => (
                <div className="min-w-0">
                    <div className="text-slate-900">
                        {fullName(row.form_input)}
                    </div>
                    <div className="text-xs text-slate-500">
                        {row.form_input.email}
                    </div>
                </div>
            ),
        },
        {
            header: 'Amount',
            sortable: 'amount',
            width: '110px',
            align: 'right',
            className: 'tabular-nums',
            render: (row) => money.format(Number(row.form_input.amount)),
        },
        {
            header: 'Status',
            sortable: 'status',
            width: '120px',
            render: (row) => (
                <StatusBadge
                    label={row.status === 'paid' ? 'Paid' : 'Ready'}
                    color={STATUS_TO_COLOR[row.status]}
                />
            ),
        },
        {
            header: 'OR number',
            sortable: 'or_no',
            width: '150px',
            render: (row) => row.or_no ?? '—',
        },
        {
            header: 'Date recorded',
            sortable: 'created_at',
            width: '180px',
            className: 'whitespace-nowrap text-slate-600',
            render: (row) =>
                new Date(row.created_at ?? row.or_date ?? '').toLocaleString(
                    'en-US',
                    {
                        timeZone: 'Asia/Manila',
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                    },
                ),
        },
    ];

    const renderActions = (row: PaymentRequest) => (
        <div className="inline-flex overflow-hidden rounded-md shadow-sm">
            <Link
                href={cashier.requests.show.url(row.id)}
                title={row.status === 'paid' ? 'View' : 'Record payment'}
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
        <>
            <Head title="Cashier Requests" />

            <RequestTable<PaymentRequest>
                title="Payment Requests"
                columns={columns}
                resource={requests}
                resourceKey="requests"
                pollInterval={15000}
                renderActions={renderActions}
                actionsWidth="56px"
                emptyIcon={Inbox}
                emptyMessage="No matching payment requests."
                onPageChange={handlePageChange}
                search={search}
                onSearchChange={setSearch}
                searchPlaceholder="Reference, payer, email, or OR number"
                status={status}
                onStatusChange={setStatus}
                statusOptions={[
                    {
                        value: 'processed',
                        label: 'Ready for payment',
                        color: 'orange',
                    },
                    { value: 'paid', label: 'Paid', color: 'dark-green' },
                ]}
                statusPlaceholder="All Status"
                dateFrom={dateFrom}
                onDateFromChange={setDateFrom}
                dateTo={dateTo}
                onDateToChange={setDateTo}
                onFilterSubmit={handleSubmit}
                onFilterReset={handleReset}
            />
        </>
    );
};

export default CashierRequestsIndex;
