import { Link, router, useForm, usePage } from '@inertiajs/react';
import { Inbox, Mail, Send, Search as SearchIcon, X, CheckCircle2, Loader2, CheckSquare, Square, ChevronDown, UserX } from 'lucide-react';
import React, { useState, useCallback, useEffect, useRef, useMemo } from 'react';
import RequestTable, { StatusBadge } from '@/components/RequestTable';
import type { ColumnDef, PaginatedData } from '@/components/RequestTable';
import staff from '@/routes/staff';
import { flashToast } from '@/utils/flashToast';

// Direct URL for email job status (bypasses Wayfinder parser issue)
const EMAIL_JOB_STATUS_URL = '/staff/requests/email-job-status';
const BULK_EMAIL_OP_URL = '/staff/requests/bulk-email-op';
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

    // Sync local filter state whenever the server returns updated filters
    // (e.g. after a navigation, reset, or Inertia partial reload).
    useEffect(() => {
        setSearch(filters.search || '');
        setStatus(filters.status || '');
        setDateFrom(filters.date_from || '');
        setDateTo(filters.date_to || '');
    }, [filters.search, filters.status, filters.date_from, filters.date_to]);

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

    // ---- Email Progress Tracking state ----
    interface EmailProgress {
        form_input_id: number;
        status: 'sent' | 'pending';
        emailed_at: string | null;
    }
    const [emailFormInputIds, setEmailFormInputIds] = useState<number[]>([]);
    const [emailProgress, setEmailProgress] = useState<EmailProgress[]>([]);
    const [isPollingProgress, setIsPollingProgress] = useState(false);
    const isPollingRef = useRef(false); // Ref for immediate polling state
    const [showProgressModal, setShowProgressModal] = useState(false);
    const [pollingStartTime, setPollingStartTime] = useState<number | null>(null);
    const [pollTimeoutId, setPollTimeoutId] = useState<ReturnType<typeof setTimeout> | null>(null);
    const [showSelectedPopover, setShowSelectedPopover] = useState(false);
    const [selectedPopoverSearch, setSelectedPopoverSearch] = useState('');
    const popoverRef = useRef<HTMLDivElement>(null);
    const MAX_POLLING_DURATION = 10 * 60 * 1000; // 10 minutes

    // Close popover when clicking outside
    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (popoverRef.current && !popoverRef.current.contains(event.target as Node)) {
                setShowSelectedPopover(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    // Cleanup polling timeout on unmount
    useEffect(() => {
        return () => {
            if (pollTimeoutId) clearTimeout(pollTimeoutId);
        };
    }, [pollTimeoutId]);

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
    const filteredSpecific = useMemo(() => 
        emailSearch.trim() === ''
            ? allRecipients
            : allRecipients.filter((row) => {
                const q = emailSearch.toLowerCase();
                const fullName = formatFullName(row).toLowerCase();
                return fullName.includes(q) || row.reference_number.toLowerCase().includes(q) || row.email.toLowerCase().includes(q);
            }), 
        [allRecipients, emailSearch]
    );

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

    const bulkRecipients = useMemo(() => 
        emailTarget === 'specific'
            ? []
            : eligibleRecipients.filter((row) => {
                const recipientStatus = row.staff_input?.status;

                return (
                    (emailTarget === 'all_paid' && recipientStatus === 'paid') ||
                    (emailTarget === 'all_processed' && recipientStatus === 'processed') ||
                    (emailTarget === 'all_pending' && recipientStatus === 'pending') ||
                    (emailTarget === 'all_cancelled' && recipientStatus === 'cancelled')
                );
            }), 
        [emailTarget, eligibleRecipients]
    );

    // Filter bulk recipients by search query
    const filteredBulkRecipients = useMemo(() => 
        emailSearch.trim() === ''
            ? bulkRecipients
            : bulkRecipients.filter((row) => {
                const q = emailSearch.toLowerCase();
                const fullName = formatFullName(row).toLowerCase();
                return fullName.includes(q) || row.reference_number.toLowerCase().includes(q) || row.email.toLowerCase().includes(q);
            }), 
        [bulkRecipients, emailSearch]
    );

    // Auto-select all bulk recipients when bulk mode changes
    const prevBulkTargetRef = useRef<string | null>(null);
    useEffect(() => {
        const isBulkMode = emailTarget !== 'specific';
        const wasBulkMode = prevBulkTargetRef.current !== null && prevBulkTargetRef.current !== 'specific';
        
        // Only run when emailTarget actually changes, not when bulkRecipients recalculates
        if (prevBulkTargetRef.current !== emailTarget) {
            if (isBulkMode) {
                if (wasBulkMode && prevBulkTargetRef.current !== emailTarget) {
                    // Switching between bulk modes - clear selections first
                    setSelectedIds(new Set(bulkRecipients.map(r => r.id)));
                } else if (!wasBulkMode) {
                    // Coming from specific mode - select all
                    setSelectedIds(new Set(bulkRecipients.map(r => r.id)));
                }
            } else {
                // Switching from bulk mode to specific - clear all selections
                setSelectedIds(new Set());
            }
        }
        prevBulkTargetRef.current = emailTarget;
    }, [emailTarget]); // Only depend on emailTarget, not bulkRecipients

    const computeTargetIds = (): number[] => {
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

    const handleSendBulkEmail = async () => {
        const ids = computeTargetIds();
        if (ids.length === 0) {
            flashToast('error', 'No eligible recipients selected.');
            return;
        }

        setIsSendingEmail(true);

        try {
            const res = await fetch(BULK_EMAIL_OP_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                },
                body: JSON.stringify({
                    form_input_ids: ids,
                    subject: emailSubject || undefined,
                    recipient_name: undefined,
                    note: emailNote || undefined,
                }),
            });

            if (!res.ok) {
                throw new Error(`HTTP ${res.status}`);
            }

            const data = await res.json();

            if (data.success && data.form_input_ids?.length > 0) {
                setEmailFormInputIds(data.form_input_ids);
                setEmailProgress(data.form_input_ids.map((id: number) => ({
                    form_input_id: id,
                    status: 'pending' as const,
                    emailed_at: null,
                })));
                setIsEmailModalOpen(false);
                setSelectedIds(new Set());
                setShowProgressModal(true);
                startProgressPolling(data.form_input_ids);
                flashToast('success', data.message ?? `Email jobs queued for ${ids.length} recipient(s).`);
            } else {
                flashToast('error', data.message ?? 'Failed to queue emails.');
            }
        } catch (err) {
            flashToast('error', 'Failed to send emails. Please try again.');
        } finally {
            setIsSendingEmail(false);
        }
    };

    const startProgressPolling = (ids: number[]) => {
        isPollingRef.current = true;
        setIsPollingProgress(true);
        setPollingStartTime(Date.now());
        // Use the passed IDs immediately, don't wait for state
        pollProgressWithIds(ids);
    };

    const pollProgressWithIds = async (ids: number[]) => {
        if (!isPollingRef.current || ids.length === 0) {
            return;
        }

        // Check for timeout (10 minutes)
        if (pollingStartTime && Date.now() - pollingStartTime > MAX_POLLING_DURATION) {
            isPollingRef.current = false;
            setIsPollingProgress(false);
            const pendingCount = emailProgress.filter(p => p.status === 'pending').length;
            flashToast('warning', `Polling timed out. ${pendingCount} email(s) still pending. You can retry them.`);
            return;
        }

        try {
            const res = await fetch(EMAIL_JOB_STATUS_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                },
                body: JSON.stringify({ form_input_ids: ids }),
            });

            if (!res.ok) {
                throw new Error(`HTTP ${res.status}`);
            }

            const data = await res.json();

            if (data.jobs) {
                // Force new array reference to trigger React re-render
                setEmailProgress(prev => {
                    const newProgress = data.jobs.map((job: EmailProgress) => ({
                        form_input_id: job.form_input_id,
                        status: job.status,
                        emailed_at: job.emailed_at,
                    }));
                    // Only update if actually changed
                    if (JSON.stringify(newProgress) !== JSON.stringify(prev)) {
                        return newProgress;
                    }
                    return prev;
                });
            }

            if (data.completed) {
                isPollingRef.current = false;
                setIsPollingProgress(false);
                if (pollTimeoutId) clearTimeout(pollTimeoutId);
                flashToast('success', `All emails processed: ${data.summary.sent} sent, ${data.summary.failed} failed.`);
            } else {
                // Poll every 5 seconds (passive auto-refresh)
                const timeoutId = setTimeout(() => pollProgressWithIds(ids), 5000);
                setPollTimeoutId(timeoutId);
            }
        } catch (err) {
            // Retry after 10 seconds on error
            const timeoutId = setTimeout(() => pollProgressWithIds(ids), 10000);
            setPollTimeoutId(timeoutId);
        }
    };

    const stopProgressPolling = () => {
        isPollingRef.current = false;
        setIsPollingProgress(false);
        if (pollTimeoutId) clearTimeout(pollTimeoutId);
    };

    const closeProgressModal = () => {
        setShowProgressModal(false);
        // Don't stop polling - let it continue in background
    };

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
                replace: true,
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
                replace: true,
            },
        );
    };

    const handlePageChange = (url: string) => {
        router.get(
            url,
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
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
                <DialogContent className="sm:max-w-2xl max-h-[90vh] overflow-y-auto">
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
                            <div className="rounded-lg bg-slate-50 p-3 text-sm text-slate-600">
                                <div className="flex items-center gap-3 mb-2 flex-wrap">
                                    <div className="flex items-center gap-2">
                                        <span className="font-semibold text-slate-800">
                                            {filteredSpecific.length}
                                        </span>{''}
                                        <span>recipient(s)</span>
                                    </div>
                                    <div className="relative flex-1 max-w-md">
                                        <SearchIcon className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                        <Input
                                            placeholder="Search by name, reference, or email"
                                            value={emailSearch}
                                            onChange={(e) => setEmailSearch(e.target.value)}
                                            className="h-9 pl-9 text-sm rounded-lg"
                                        />
                                    </div>
                                    {selectedIds.size > 0 && (
                                        <div className="relative">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => setShowSelectedPopover(!showSelectedPopover)}
                                                className="h-9 px-3 gap-1.5 shrink-0"
                                            >
                                                <UserX className="h-4 w-4" />
                                                <span className="hidden sm:inline">{selectedIds.size} selected</span>
                                                <ChevronDown className="h-3.5 w-3.5" />
                                            </Button>
                                            {showSelectedPopover && (
                                                <div
                                                    ref={popoverRef}
                                                    className="absolute right-0 top-full mt-1.5 w-80 bg-white border border-slate-200 rounded-lg shadow-lg py-1 z-10"
                                                    onClick={(e) => e.stopPropagation()}
                                                >
                                                    <div className="px-3 py-2 border-b border-slate-100 flex items-center justify-between">
                                                        <span className="text-sm font-medium text-slate-800">Selected Recipients</span>
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => setSelectedIds(new Set())}
                                                            className="text-xs text-rose-600 hover:text-rose-700"
                                                        >
                                                            Clear All
                                                        </Button>
                                                    </div>
                                                    <div className="px-3 py-2 border-b border-slate-100">
                                                        <Input
                                                            placeholder="Search selected..."
                                                            value={selectedPopoverSearch}
                                                            onChange={(e) => setSelectedPopoverSearch(e.target.value)}
                                                            className="h-9 text-sm"
                                                            autoFocus
                                                        />
                                                    </div>
                                                    <div className="max-h-48 overflow-y-auto">
                                                        {Array.from(selectedIds)
                                                            .map((id) => allRecipients.find(r => r.id === id))
                                                            .filter((row): row is typeof row & { id: number } => row !== undefined)
                                                            .filter((row) => {
                                                                if (!selectedPopoverSearch.trim()) return true;
                                                                const q = selectedPopoverSearch.toLowerCase();
                                                                const fullName = formatFullName(row).toLowerCase();
                                                                return fullName.includes(q) || row.reference_number.toLowerCase().includes(q) || row.email.toLowerCase().includes(q);
                                                            })
                                                            .map((row) => (
                                                                <div
                                                                    key={row.id}
                                                                    className="flex items-center gap-2 px-3 py-1.5 hover:bg-slate-50 cursor-pointer"
                                                                    onClick={() => toggleSelectId(row.id)}
                                                                >
                                                                    <UserX className="h-3.5 w-3.5 text-rose-500 shrink-0" />
                                                                    <div className="min-w-0 flex-1">
                                                                        <div className="text-sm font-medium text-slate-800 truncate">
                                                                            {formatFullName(row)}
                                                                        </div>
                                                                        <div className="text-xs text-slate-500 truncate">
                                                                            {row.reference_number} · {row.email}
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            ))}
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </div>
                                <div className="max-h-72 overflow-y-auto rounded-md border border-slate-200 bg-white">
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
                            </div>
                        )}

                        {emailTarget !== 'specific' && (
                            <div className="rounded-lg bg-slate-50 p-3 text-sm text-slate-600">
                                <div className="flex items-center justify-between gap-2 mb-2">
                                    <div className="flex items-center gap-2">
                                        <span className="font-semibold text-slate-800">
                                            {bulkRecipients.length}
                                        </span>{' '}
                                        <span>recipient(s)</span>
                                        <Input
                                            placeholder="Search recipients..."
                                            value={emailSearch}
                                            onChange={(e) => setEmailSearch(e.target.value)}
                                            className="h-9 w-64 text-sm rounded-lg"
                                        />
                                    </div>
                                    
                                    <div className="flex items-center gap-1.5">
                                        {selectedIds.size > 0 && (
                                            <div className="relative">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => setShowSelectedPopover(!showSelectedPopover)}
                                                    className="h-9 px-3 gap-1.5"
                                                >
                                                    <UserX className="h-4 w-4" />
                                                    <span>{selectedIds.size} selected</span>
                                                    <ChevronDown className="h-3.5 w-3.5" />
                                                </Button>
                                                {showSelectedPopover && (
                                                    <div
                                                        ref={popoverRef}
                                                        className="absolute right-0 top-full mt-1.5 w-80 bg-white border border-slate-200 rounded-lg shadow-lg py-1 z-10"
                                                        onClick={(e) => e.stopPropagation()}
                                                    >
                                                        <div className="px-3 py-2 border-b border-slate-100 flex items-center justify-between">
                                                            <span className="text-sm font-medium text-slate-800">Selected Recipients</span>
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() => setSelectedIds(new Set())}
                                                                className="text-xs text-rose-600 hover:text-rose-700"
                                                            >
                                                                Clear All
                                                            </Button>
                                                        </div>
                                                        <div className="px-3 py-2 border-b border-slate-100">
                                                            <Input
                                                                placeholder="Search selected..."
                                                                value={selectedPopoverSearch}
                                                                onChange={(e) => setSelectedPopoverSearch(e.target.value)}
                                                                className="h-9 text-sm"
                                                                autoFocus
                                                            />
                                                        </div>
                                                        <div className="max-h-48 overflow-y-auto">
                                                            {Array.from(selectedIds)
                                                                .map((id) => allRecipients.find(r => r.id === id))
                                                                .filter((row): row is typeof row & { id: number } => row !== undefined)
                                                                .filter((row) => {
                                                                    if (!selectedPopoverSearch.trim()) return true;
                                                                    const q = selectedPopoverSearch.toLowerCase();
                                                                    const fullName = formatFullName(row).toLowerCase();
                                                                    return fullName.includes(q) || row.reference_number.toLowerCase().includes(q) || row.email.toLowerCase().includes(q);
                                                                })
                                                                .map((row) => (
                                                                    <div
                                                                        key={row.id}
                                                                        className="flex items-center gap-2 px-3 py-1.5 hover:bg-slate-50 cursor-pointer"
                                                                        onClick={() => toggleSelectId(row.id)}
                                                                    >
                                                                        <UserX className="h-3.5 w-3.5 text-rose-500 shrink-0" />
                                                                        <div className="min-w-0 flex-1">
                                                                            <div className="text-sm font-medium text-slate-800 truncate">
                                                                                {formatFullName(row)}
                                                                            </div>
                                                                            <div className="text-xs text-slate-500 truncate">
                                                                                {row.reference_number} · {row.email}
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                ))}
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                        {selectedIds.size === filteredBulkRecipients.length ? (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => setSelectedIds(new Set())}
                                                className="h-9 px-3"
                                            >
                                                Unselect All
                                            </Button>
                                        ) : (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => setSelectedIds(new Set(filteredBulkRecipients.map(r => r.id)))}
                                                className="h-9 px-3"
                                            >
                                                Select All
                                            </Button>
                                        )}
                                    </div>
                                </div>
                                <div className="mt-2 max-h-72 overflow-y-auto rounded-md border border-slate-200 bg-white">
                                    {filteredBulkRecipients.length === 0 ? (
                                        <div className="p-4 text-center text-slate-400">
                                            No matching recipients found
                                        </div>
                                    ) : (
                                        filteredBulkRecipients.map((row) => {
                                            const timeAgo = formatTimeAgo(row.staff_input?.emailed_at);
                                            const isSelected = selectedIds.has(row.id);

                                            return (
                                                <div
                                                    key={row.id}
                                                    className="flex items-start gap-2 border-b border-slate-100 px-3 py-2 text-sm last:border-0 cursor-pointer hover:bg-slate-50"
                                                    onClick={() => toggleSelectId(row.id)}
                                                >
                                                    <div className="mt-0.5 shrink-0">
                                                        {isSelected ? (
                                                            <CheckSquare className="h-4 w-4 text-emerald-600" />
                                                        ) : (
                                                            <Square className="h-4 w-4 text-slate-300" />
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
                                                        ) : (
                                                            <div className="mt-0.5 inline-flex items-center gap-1 text-xs text-slate-400">
                                                                <Mail className="h-3 w-3" />
                                                                Not sent an email
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                            );
                                        })
                                    )}
                                </div>
                                <div className="mt-2 text-xs text-slate-500">
                                    <span className="font-semibold text-slate-700">{selectedIds.size}</span>{' '}
                                    selected
                                </div>
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

            {/* ---- Email Progress Modal ---- */}
            <Dialog open={showProgressModal} onOpenChange={setShowProgressModal}>
                <DialogContent className="sm:max-w-2xl max-h-[80vh] overflow-hidden">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Send className="h-5 w-5 text-emerald-600" />
                            Email Sending Progress
                        </DialogTitle>
                        <DialogDescription>
                            Emails are being sent in the background. You can close this window and continue working.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 pb-2">
                        {/* Progress Summary */}
                        <div className="flex items-center gap-4 p-4 bg-slate-50 rounded-lg">
                            <div className="flex-1 text-center">
                                <div className="text-2xl font-bold text-emerald-600">
                                    {emailProgress.filter(p => p.status === 'sent').length}
                                </div>
                                <div className="text-xs text-slate-500">Sent</div>
                            </div>
                            <div className="w-px h-10 bg-slate-200" />
                            <div className="flex-1 text-center">
                                <div className="text-2xl font-bold text-slate-600">
                                    {emailProgress.filter(p => p.status === 'pending').length}
                                </div>
                                <div className="text-xs text-slate-500">Pending</div>
                            </div>
                            <div className="w-px h-10 bg-slate-200" />
                            <div className="flex-1 text-center">
                                <div className="text-2xl font-bold text-blue-600">
                                    {emailProgress.length}
                                </div>
                                <div className="text-xs text-slate-500">Total</div>
                            </div>
                        </div>

                        {/* Progress Bar */}
                        <div className="h-3 bg-slate-200 rounded-full overflow-hidden">
                            <div
                                className="h-full bg-emerald-600 transition-all duration-300"
                                style={{
                                    width: `${emailProgress.length > 0
                                        ? (emailProgress.filter(p => p.status === 'sent').length / emailProgress.length) * 100
                                        : 0}%`,
                                }}
                            />
                        </div>
                        <p className="text-sm text-slate-500 text-center flex items-center justify-center gap-2">
                            {isPollingProgress 
                                ? (pollingStartTime && Date.now() - pollingStartTime > MAX_POLLING_DURATION * 0.8
                                    ? 'Almost timed out...'
                                    : <span>Processing... <Loader2 className="h-4 w-4 animate-spin text-emerald-600" /> <span className="text-xs text-slate-400">(auto-refresh every 5s)</span></span>) 
                                : 'Completed'}
                        </p>

                        {/* Job List - Scrollable */}
                        <div className="max-h-96 overflow-y-auto rounded-lg border border-slate-200">
                            {emailProgress.length === 0 ? (
                                <div className="p-4 text-center text-sm text-slate-400">
                                    No jobs to display
                                </div>
                            ) : (
                                <div className="divide-y divide-slate-100">
                                    {emailProgress.map((progress, index) => {
                                        const recipient = allRecipients.find(r => r.id === progress.form_input_id);
                                        const statusColors = {
                                            sent: 'bg-emerald-100 text-emerald-700',
                                            pending: 'bg-amber-100 text-amber-700',
                                        };
                                        const statusIcons = {
                                            sent: <CheckCircle2 className="h-3.5 w-3.5" />,
                                            pending: <Loader2 className="h-3.5 w-3.5 animate-spin" />,
                                        };

                                        return (
                                            <div
                                                key={`${progress.form_input_id}-${index}`}
                                                className="flex items-center gap-3 px-4 py-3 hover:bg-slate-50"
                                            >
                                                <div className={`flex-shrink-0 w-6 h-6 rounded-full flex items-center justify-center ${statusColors[progress.status]}`}>
                                                    {statusIcons[progress.status]}
                                                </div>
                                                <div className="flex-1 min-w-0">
                                                    <div className="flex items-center gap-2 truncate">
                                                        <span className="font-medium text-slate-800 truncate">
                                                            {recipient ? formatFullName(recipient) : `ID: ${progress.form_input_id}`}
                                                        </span>
                                                        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${statusColors[progress.status]}`}>
                                                            {progress.status.charAt(0).toUpperCase() + progress.status.slice(1)}
                                                        </span>
                                                    </div>
                                                    <div className="text-xs text-slate-500 truncate">
                                                        {recipient ? `${recipient.reference_number} · ${recipient.email}` : `ID: ${progress.form_input_id}`}
                                                    </div>
                                                    {progress.emailed_at && (
                                                        <div className="text-xs text-emerald-600">
                                                            Sent: {formatDate(progress.emailed_at)}
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={closeProgressModal}
                        >
                            <X className="h-4 w-4 mr-1.5" />
                            Close (continue in background)
                        </Button>
                        {!isPollingProgress && (
                            <Button
                                type="button"
                                onClick={() => {
                                    setShowProgressModal(false);
                                    setEmailFormInputIds([]);
                                    setEmailProgress([]);
                                }}
                                className="gap-2 bg-emerald-600 hover:bg-emerald-700"
                            >
                                <CheckCircle2 className="h-4 w-4" />
                                Done
                            </Button>
                        )}
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
};

export default ManageRequests;
