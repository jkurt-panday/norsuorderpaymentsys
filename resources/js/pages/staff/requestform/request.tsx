import { Link, router, useForm, usePage } from '@inertiajs/react';
import { Inbox, Mail, Send, Search as SearchIcon, X, CheckCircle2, Loader2, CheckSquare, Square } from 'lucide-react';
import React, { useState, useCallback, useEffect } from 'react';
import RequestTable, { StatusBadge } from '@/components/RequestTable';
import type { ColumnDef, PaginatedData } from '@/components/RequestTable';
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
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';

interface StaffInput {
    id: number;
    status: 'pending' | 'approved' | 'cancelled' | 'unprocessed' | 'processed' | 'paid';
    emailed_at: string | null;
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
    processed: 'light-green',
    paid: 'dark-green',
    cancelled: 'red',
    pending: 'orange',
    unprocessed: 'grey',
};

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

/** Returns a human-readable "X days ago" / "X hours ago" / "X minutes ago" string. */
const formatTimeAgo = (isoDate: string | null | undefined): string => {
    if (!isoDate) return '';
    const then = new Date(isoDate).getTime();
    const now = Date.now();
    const diffMs = now - then;
    if (diffMs < 0) return 'just now';

    const minutes = Math.floor(diffMs / 60000);
    const hours = Math.floor(diffMs / 3600000);
    const days = Math.floor(diffMs / 86400000);

    if (days > 0) return `${days} day${days === 1 ? '' : 's'} ago`;
    if (hours > 0) return `${hours} hour${hours === 1 ? '' : 's'} ago`;
    if (minutes > 0) return `${minutes} minute${minutes === 1 ? '' : 's'} ago`;
    return 'just now';
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

    // ---- Bulk Email Modal state ----
    const [isEmailModalOpen, setIsEmailModalOpen] = useState(false);
    const [emailTarget, setEmailTarget] = useState<'specific' | 'all_paid' | 'all_processed' | 'all_pending' | 'all_cancelled'>('specific');
    const [emailSearch, setEmailSearch] = useState('');
    const [emailSubject, setEmailSubject] = useState('');
    const [emailNote, setEmailNote] = useState('');
    const [isSendingEmail, setIsSendingEmail] = useState(false);
    // Selected IDs for the "specific person" checkbox list
    const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
    // ALL form inputs (not paginated) fetched from the backend
    const [allRecipients, setAllRecipients] = useState<FormInput[]>([]);
    const [isLoadingRecipients, setIsLoadingRecipients] = useState(false);

    // Fetch every form input (across all pages) when the modal opens.
    // Uses a direct fetch (NOT Inertia router) so it doesn't touch the
    // page's filter/search state — that keeps the parent table's
    // Filter / Reset / status controls fully functional.
    useEffect(() => {
        if (!isEmailModalOpen) return;

        let cancelled = false;
        setIsLoadingRecipients(true);

        fetch(staff.requests.emailRecipients.url(), {
            headers: { Accept: 'application/json' },
        })
            .then(async (res) => {
                if (!res.ok) {
                    throw new Error(`HTTP ${res.status}`);
                }
                return res.json();
            })
            .then((json) => {
                if (cancelled) return;
                setAllRecipients(json.data ?? []);
                setIsLoadingRecipients(false);
            })
            .catch((err) => {
                if (cancelled) return;
                flashToast('error', 'Failed to load recipients.');
                setIsLoadingRecipients(false);
            });

        return () => {
            cancelled = true;
        };
    }, [isEmailModalOpen]);

    // Build the list of all form inputs from the paginated resource (current page)
    const allFormInputs: FormInput[] = formInputs.data ?? [];

    // Filtered list for the "specific person" search — uses ALL recipients, not just current page
    const filteredSpecific = emailSearch.trim() === ''
        ? allRecipients
        : allRecipients.filter((row) => {
            const q = emailSearch.toLowerCase();
            const fullName = formatFullName(row).toLowerCase();
            return fullName.includes(q) || row.reference_number.toLowerCase().includes(q) || row.email.toLowerCase().includes(q);
        });

    // Eligible recipients (must have staff_input + email)
    const eligibleRecipients = allRecipients.filter(
        (row) => row.staff_input && row.email
    );

    // Toggle a single ID in the checkbox set
    const toggleSelectId = (id: number) => {
        setSelectedIds((prev) => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    };

    // Compute the list of IDs that will be emailed based on the selected target
    const computeTargetIds = (): number[] => {
        if (emailTarget === 'all_paid') {
            return eligibleRecipients
                .filter((r) => r.staff_input!.status === 'paid')
                .map((r) => r.id);
        }
        if (emailTarget === 'all_processed') {
            return eligibleRecipients
                .filter((r) => r.staff_input!.status === 'processed')
                .map((r) => r.id);
        }
        if (emailTarget === 'all_pending') {
            return eligibleRecipients
                .filter((r) => r.staff_input!.status === 'pending')
                .map((r) => r.id);
        }
        if (emailTarget === 'all_cancelled') {
            return eligibleRecipients
                .filter((r) => r.staff_input!.status === 'cancelled')
                .map((r) => r.id);
        }
        // 'specific' — use the checkbox set
        return Array.from(selectedIds);
    };

    const handleOpenEmailModal = () => {
        setEmailSubject('');
        setEmailNote('');
        setEmailSearch('');
        setEmailTarget('specific');
        setSelectedIds(new Set());
        setAllRecipients([]);
        setIsEmailModalOpen(true);
    };

    const handleSendBulkEmail = () => {
        const ids = computeTargetIds();
        if (ids.length === 0) {
            flashToast('error', 'No eligible recipients selected.');
            return;
        }

        setIsSendingEmail(true);
        router.post(
            staff.requests.bulkEmailOp.url(),
            {
                form_input_ids: ids,
                subject: emailSubject || undefined,
                recipient_name: undefined,
                note: emailNote || undefined,
            },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['formInputs'],
                onSuccess: () => {
                    flashToast('success', `Email sent to ${ids.length} recipient(s).`);
                    setIsEmailModalOpen(false);
                    setSelectedIds(new Set());
                },
                onError: () => {
                    flashToast('error', 'Failed to send emails. Please try again.');
                },
                onFinish: () => {
                    setIsSendingEmail(false);
                },
            },
        );
    };

    const applyFilters = useCallback(() => {
        const params = new URLSearchParams();

        if (search) {
            params.set('search', search);
        }

        if (status) {
            params.set('status', status);
        }

        if (dateFrom) {
            params.set('date_from', dateFrom);
        }

        if (dateTo) {
            params.set('date_to', dateTo);
        }

        const qs = params.toString();
        const url = qs ? `${staff.requests.index.url()}?${qs}` : staff.requests.index.url();

        router.get(url, {}, {
            preserveState: true,
            preserveScroll: true,
            only: ['formInputs'],
        });
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
                only: ['formInputs'],
            },
        );
    };

    const handlePageChange = (url: string) => {
        router.get(url, {}, {
            preserveState: true,
            preserveScroll: true,
            only: ['formInputs'],
        });
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
        <div>
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
                    { value: 'processed', label: 'Processed', color: 'light-green' },
                    { value: 'paid', label: 'Paid', color: 'dark-green' },
                    { value: 'cancelled', label: 'Cancelled', color: 'red' },
                    {
                        value: 'unprocessed',
                        label: 'Unprocessed',
                        color: 'grey',
                    },
                ]}
                dateFrom={dateFrom}
                onDateFromChange={setDateFrom}
                dateTo={dateTo}
                onDateToChange={setDateTo}
                onFilterSubmit={handleSubmit}
                onFilterReset={handleReset}
                customToolbar={
                    <Button
                        type="button"
                        onClick={handleOpenEmailModal}
                        className="h-10 gap-2 rounded-lg bg-emerald-600 px-4 text-white hover:bg-emerald-700"
                    >
                        <Mail className="h-4 w-4" />
                        <span>Send Email</span>
                    </Button>
                }
            />

            {/* ---- Bulk Email Modal ---- */}
            <Dialog open={isEmailModalOpen} onOpenChange={setIsEmailModalOpen}>
                <DialogContent className="sm:max-w-lg max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Mail className="h-5 w-5 text-emerald-600" />
                            Send Order of Payment Emails
                        </DialogTitle>
                        <DialogDescription>
                            Choose which recipients should receive their Order of Payment receipts.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 pb-2">
                        {/* Recipient selection dropdown */}
                        <div className="space-y-1.5">
                            <label className="text-sm font-medium text-slate-700">
                                Whom do you want to send email to?
                            </label>
                            <Select
                                value={emailTarget}
                                onValueChange={(v) => {
                                    if (v === 'specific' || v === 'all_paid' || v === 'all_processed' || v === 'all_pending' || v === 'all_cancelled') {
                                        setEmailTarget(v);
                                    }
                                }}
                            >
                                <SelectTrigger className="h-10 w-full rounded-lg border-slate-200 bg-white shadow-sm">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="specific">Specific person</SelectItem>
                                    <SelectItem value="all_paid">All paid</SelectItem>
                                    <SelectItem value="all_processed">All processed</SelectItem>
                                    <SelectItem value="all_pending">All pending</SelectItem>
                                    <SelectItem value="all_cancelled">All cancelled</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Specific person search + checkbox list — only shown when 'specific' is chosen */}
                        {emailTarget === 'specific' && (
                            <div className="space-y-1.5">
                                <label className="text-sm font-medium text-slate-700">
                                    Search recipient
                                </label>
                                <div className="relative">
                                    <SearchIcon className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                    <Input
                                        placeholder="Search by name, reference, or email"
                                        value={emailSearch}
                                        onChange={(e) => setEmailSearch(e.target.value)}
                                        className="h-10 rounded-lg pl-9"
                                    />
                                </div>
                                <div className="max-h-72 overflow-y-auto rounded-lg border border-slate-200">
                                    {isLoadingRecipients ? (
                                        <div className="flex items-center justify-center p-6">
                                            <Loader2 className="h-5 w-5 animate-spin text-slate-400" />
                                        </div>
                                    ) : filteredSpecific.length === 0 ? (
                                        <div className="p-4 text-center text-sm text-slate-400">
                                            No matching recipients
                                        </div>
                                    ) : (
                                        filteredSpecific.map((row) => {
                                            const eligible = row.staff_input && row.email;
                                            const isSelected = selectedIds.has(row.id);
                                            const timeAgo = formatTimeAgo(row.staff_input?.emailed_at);
                                            return (
                                                <div
                                                    key={row.id}
                                                    className={`flex items-start gap-2 border-b border-slate-100 px-3 py-2 text-sm last:border-0 ${
                                                        eligible
                                                            ? 'cursor-pointer bg-white hover:bg-slate-50'
                                                            : 'bg-slate-50 opacity-60'
                                                    }`}
                                                    onClick={() => {
                                                        if (eligible) {
                                                            toggleSelectId(row.id);
                                                        }
                                                    }}
                                                >
                                                    <div className="mt-0.5 shrink-0">
                                                        {eligible ? (
                                                            isSelected ? (
                                                                <CheckSquare className="h-4 w-4 text-emerald-600" />
                                                            ) : (
                                                                <Square className="h-4 w-4 text-slate-300" />
                                                            )
                                                        ) : (
                                                            <Square className="h-4 w-4 text-slate-200" />
                                                        )}
                                                    </div>
                                                    <div className="min-w-0 flex-1">
                                                        <div className="flex min-w-0 items-center gap-2">
                                                            <div className="truncate font-medium text-slate-800">
                                                                {formatFullName(row)}
                                                            </div>
                                                            <span
                                                                className={`inline shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ${
                                                                    row.staff_input
                                                                        ? statusBadgeClass(row.staff_input.status)
                                                                        : 'bg-slate-100 text-slate-600'
                                                                }`}
                                                            >
                                                                {row.staff_input?.status ?? 'Unprocessed'}
                                                            </span>
                                                        </div>
                                                        <div className="truncate text-xs text-slate-500">
                                                            {row.reference_number} · {row.email}
                                                        </div>
                                                        {timeAgo ? (
                                                            <div className="mt-0.5 inline-flex items-center gap-1 text-xs text-emerald-600">
                                                                <Mail className="h-3 w-3" />
                                                                Sent email {timeAgo}
                                                            </div>
                                                        ) : eligible ? (
                                                            <div className="mt-0.5 inline-flex items-center gap-1 text-xs text-slate-400">
                                                                <Mail className="h-3 w-3" />
                                                                Not sent an email
                                                            </div>
                                                        ) : null}
                                                    </div>
                                                </div>
                                            );
                                        })
                                    )}
                                </div>
                                <div className="text-xs text-slate-500">
                                    <span className="font-semibold text-slate-700">{selectedIds.size}</span>{' '}
                                    selected
                                </div>
                            </div>
                        )}

                        {/* Show recipient count for bulk targets */}
                        {emailTarget !== 'specific' && (
                            <div className="rounded-lg bg-slate-50 p-3 text-sm text-slate-600">
                                <span className="font-semibold text-slate-800">
                                    {computeTargetIds().length}
                                </span>{' '}
                                recipient(s) will be emailed
                            </div>
                        )}

                        {/* Subject + Note */}
                        <div className="space-y-1.5">
                            <label className="text-sm font-medium text-slate-700">
                                Subject (optional)
                            </label>
                            <Input
                                placeholder="Leave blank for default subject"
                                value={emailSubject}
                                onChange={(e) => setEmailSubject(e.target.value)}
                                className="h-10 rounded-lg"
                            />
                        </div>
                        <div className="space-y-1.5">
                            <label className="text-sm font-medium text-slate-700">
                                Note (optional)
                            </label>
                            <Textarea
                                placeholder="Optional message to include"
                                value={emailNote}
                                onChange={(e) => setEmailNote(e.target.value)}
                                rows={3}
                                className="rounded-lg"
                            />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setIsEmailModalOpen(false)}
                            disabled={isSendingEmail}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            onClick={handleSendBulkEmail}
                            disabled={isSendingEmail || computeTargetIds().length === 0}
                            className="gap-2 bg-emerald-600 hover:bg-emerald-700"
                        >
                            {isSendingEmail ? (
                                <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                                <Send className="h-4 w-4" />
                            )}
                            <span>
                                {isSendingEmail ? 'Sending...' : `Send (${computeTargetIds().length})`}
                            </span>
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
};

export default ManageRequests;
