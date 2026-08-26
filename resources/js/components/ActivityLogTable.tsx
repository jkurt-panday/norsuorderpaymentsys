import { router, usePoll } from '@inertiajs/react';
import {
    Search,
    Plus,
    Pencil,
    Trash2,
    CheckCircle2,
    Activity,
    ArrowUp,
    ArrowDown,
    ArrowUpDown,
    RefreshCw,
    ChevronLeft,
    ChevronRight,
} from 'lucide-react';
import React, { useEffect, useState } from 'react';
import { DateRangeFilter } from '@/components/data-table/date-range-picker';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

export interface ActivityLogChange {
    field: string;
    old: string | null;
    new: string | null;
}

export interface ActivityLogItem {
    id: number;
    action: string;
    event: 'created' | 'updated' | 'deleted' | 'processed';
    description: string;
    actor_name: string | null;
    actor_role: string | null;
    type: string;
    created_at: string;
    changes?: ActivityLogChange[];
}

export interface ActivityLogPaginator {
    data: ActivityLogItem[];
    links?: { url: string | null; label: string; active: boolean }[];
    meta?: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    current_page?: number;
    last_page?: number;
    per_page?: number;
    total?: number;
    from?: number;
    to?: number;
}

export interface ActivityLogFilters {
    activity_search?: string;
    activity_action?: string;
    sort?: string;
    direction?: string;
    date_from?: string;
    date_to?: string;
}

interface ActivityLogTableProps {
    logs: ActivityLogPaginator;
    filters?: ActivityLogFilters;
}

const EVENT_UI: Record<
    string,
    { label: string; icon: React.ElementType; badgeClass: string }
> = {
    created: {
        label: 'Created',
        icon: Plus,
        badgeClass: '!bg-emerald-100 !text-emerald-800',
    },
    updated: {
        label: 'Updated',
        icon: Pencil,
        badgeClass: '!bg-amber-100 !text-amber-900',
    },
    deleted: {
        label: 'Deleted',
        icon: Trash2,
        badgeClass: '!bg-rose-100 !text-rose-800',
    },
    processed: {
        label: 'Processed',
        icon: CheckCircle2,
        badgeClass: '!bg-blue-100 !text-blue-800',
    },
};

const ACTION_OPTIONS = [
    { value: 'created', label: 'Created' },
    { value: 'updated', label: 'Updated' },
    { value: 'deleted', label: 'Deleted' },
    { value: 'processed', label: 'Processed' },
];

function getEventUi(event: string) {
    return EVENT_UI[event] ?? {
        label: event,
        icon: Activity,
        badgeClass: '!bg-slate-100 !text-slate-700',
    };
}

function formatDateTime(value?: string | null) {
    if (!value) {
        return '-';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString('en-US', {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function roleTextColorClass(role?: string | null) {
    switch ((role ?? '').toLowerCase()) {
        case 'admin':
            return 'text-emerald-700 font-bold';
        case 'staff':
            return 'text-blue-700 font-bold';
        case 'customer':
        default:
            return 'text-slate-600 font-medium';
    }
}

function toDate(value: string | undefined): Date | undefined {
    if (!value) {
        return undefined;
    }

    const [y, m, d] = value.split('-').map(Number);

    if (!y || !m || !d) {
        return undefined;
    }

    return new Date(y, m - 1, d);
}

function toDateString(date: Date | undefined): string {
    if (!date) {
        return '';
    }

    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');

    return `${y}-${m}-${d}`;
}

const COLUMNS: { label: string; width: string; sortable?: string }[] = [
    { label: 'Event', width: 'w-[130px]', sortable: 'action' },
    { label: 'Description', width: 'min-w-[260px]' },
    { label: 'Actor', width: 'w-[220px]', sortable: 'actor_name' },
    { label: 'Type', width: 'w-[150px]', sortable: 'subject_type' },
    { label: 'Date', width: 'w-[180px]', sortable: 'created_at' },
];

export function ActivityLogTable({ logs, filters }: ActivityLogTableProps) {
    const rows: ActivityLogItem[] = logs?.data ?? [];

    const [search, setSearch] = useState(filters?.activity_search ?? '');
    const [action, setAction] = useState(filters?.activity_action ?? '');
    const [dateFrom, setDateFrom] = useState(filters?.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters?.date_to ?? '');
    const [sortKey, setSortKey] = useState(filters?.sort ?? '');
    const [sortDir, setSortDir] = useState(filters?.direction ?? '');

    const prevSortProp = React.useRef(filters?.sort);
    const prevDirProp = React.useRef(filters?.direction);

    if (prevSortProp.current !== filters?.sort) {
        prevSortProp.current = filters?.sort;
        setSortKey(filters?.sort ?? '');
    }

    if (prevDirProp.current !== filters?.direction) {
        prevDirProp.current = filters?.direction;
        setSortDir(filters?.direction ?? '');
    }

    const [isNavigating, setIsNavigating] = useState(false);
    const [isResetting, setIsResetting] = useState(false);

    // Keep the feed live: silently refetch only the activity_log data every
    // 5 seconds. This is a background Inertia visit (no full page reload) and
    // preserves the current search/sort/page URL, so filters stay intact.
    const { start, stop } = usePoll(
        5000,
        { only: ['activityLogs'] },
        { autoStart: false },
    );

    useEffect(() => {
        start();

        return () => stop();
    }, [start, stop]);

    const currentPage = logs?.meta?.current_page ?? logs?.current_page ?? 1;
    const lastPage = logs?.meta?.last_page ?? logs?.last_page ?? 1;
    const links = logs?.links ?? [];

    const effectiveSortKey = sortKey;
    const effectiveSortDir = sortDir;

    // Background navigation that only refreshes this table's data (renders the
    // loading bar, never a full website reload).
    const navigateWithParams = (overrides: Record<string, string | undefined>) => {
        const url = new URL(window.location.href);

        for (const [key, value] of Object.entries(overrides)) {
            if (value) {
                url.searchParams.set(key, value);
            } else {
                url.searchParams.delete(key);
            }
        }

        url.searchParams.delete('page');

        stop();

        router.get(
            url.pathname + url.search,
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['activityLogs'],
                showProgress: false,
                onStart: () => setIsNavigating(true),
                onFinish: () => {
                    setIsNavigating(false);
                    start();
                },
            },
        );
    };

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        navigateWithParams({
            activity_search: search.trim() || undefined,
            activity_action: action === 'all' ? undefined : action || undefined,
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
            sort: sortKey || undefined,
            direction: sortDir || undefined,
        });
    };

    const handleActionChange = (value: string | null) => {
        const selected = value ?? 'all';
        setAction(selected);
        navigateWithParams({
            activity_search: search.trim() || undefined,
            activity_action: selected === 'all' ? undefined : selected,
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
            sort: sortKey || undefined,
            direction: sortDir || undefined,
        });
    };

    const handleDateRangeApply = ({ from, to }: { from: Date | undefined; to: Date | undefined }) => {
        const fromStr = toDateString(from);
        const toStr = toDateString(to);
        setDateFrom(fromStr);
        setDateTo(toStr);
        navigateWithParams({
            date_from: fromStr || undefined,
            date_to: toStr || undefined,
            activity_search: search.trim() || undefined,
            activity_action: action === 'all' ? undefined : action || undefined,
            sort: sortKey || undefined,
            direction: sortDir || undefined,
        });
    };

    // Clickable column header: cycles through inactive -> asc -> desc -> inactive
    const handleSortClick = (columnKey: string) => {
        const isActive = effectiveSortKey === columnKey;
        const currentDir = effectiveSortDir;

        if (!isActive) {
            const nextDir = 'asc';
            setSortKey(columnKey);
            setSortDir(nextDir);
            navigateWithParams({
                sort: columnKey,
                direction: nextDir,
                activity_search: search.trim() || undefined,
                activity_action: action === 'all' ? undefined : action || undefined,
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
            });
        } else if (currentDir === 'asc') {
            setSortKey(columnKey);
            setSortDir('desc');
            navigateWithParams({
                sort: columnKey,
                direction: 'desc',
                activity_search: search.trim() || undefined,
                activity_action: action === 'all' ? undefined : action || undefined,
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
            });
        } else {
            setSortKey('');
            setSortDir('');
            navigateWithParams({
                sort: undefined,
                direction: undefined,
                activity_search: search.trim() || undefined,
                activity_action: action === 'all' ? undefined : action || undefined,
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
            });
        }
    };

    const handleReset = () => {
        setSearch('');
        setAction('');
        setDateFrom('');
        setDateTo('');
        setSortKey('');
        setSortDir('');

        stop();

        router.get(
            window.location.pathname,
            {},
            {
                preserveScroll: true,
                replace: true,
                only: ['activityLogs'],
                showProgress: false,
                onStart: () => setIsResetting(true),
                onFinish: () => {
                    setIsResetting(false);
                    start();
                },
            },
        );
    };

    const [pageJumpOpen, setPageJumpOpen] = useState(false);
    const [pageJumpInput, setPageJumpInput] = useState('');

    const navigateToPage = (page: number) => {
        const url = new URL(window.location.href);

        if (page > 1) {
            url.searchParams.set('page', String(page));
        } else {
            url.searchParams.delete('page');
        }

        stop();

        router.get(
            url.pathname + url.search,
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['activityLogs'],
                showProgress: false,
                onStart: () => setIsNavigating(true),
                onFinish: () => {
                    setIsNavigating(false);
                    start();
                },
            },
        );
    };

    const isBusy = isNavigating || isResetting;

    return (
        <Card className="border border-slate-200 bg-white shadow-sm">
            <div className="relative h-0.5 w-full overflow-hidden bg-transparent">
                {isBusy && (
                    <div className="absolute inset-0 animate-[table-loading-bar_1s_ease-in-out_infinite] bg-blue-500" />
                )}
            </div>
            <style>{`
                @keyframes table-loading-bar {
                    0% { transform: translateX(-100%); }
                    100% { transform: translateX(100%); }
                }
            `}</style>

            <CardHeader className="border-b border-slate-200 py-3.5 px-6">
                <form
                    onSubmit={handleSearchSubmit}
                    className="flex flex-wrap items-center gap-2 w-full"
                >
                    <div className="relative flex-1 min-w-[200px] sm:max-w-xs">
                        <Search className="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-slate-400" />
                        <Input
                            type="search"
                            placeholder="Search action, name, role, description..."
                            className="h-9 pl-8 text-xs"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </div>

                    {/* Action Filter Select */}
                    <Select value={action || 'all'} onValueChange={handleActionChange}>
                        <SelectTrigger className="h-9 w-full text-xs sm:w-36">
                            <SelectValue placeholder="All actions" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All actions</SelectItem>
                            {ACTION_OPTIONS.map((opt) => (
                                <SelectItem key={opt.value} value={opt.value}>
                                    {opt.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {/* Date Range */}
                    <DateRangeFilter
                        from={toDate(dateFrom)}
                        to={toDate(dateTo)}
                        onFromChange={(date) => setDateFrom(toDateString(date))}
                        onToChange={(date) => setDateTo(toDateString(date))}
                        onApply={handleDateRangeApply}
                    />

                    <Button type="submit" size="sm" className="h-9 bg-blue-600 text-xs text-white hover:bg-blue-700">
                        <Search className="mr-1.5 h-3.5 w-3.5" />
                        Search
                    </Button>

                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="h-9 text-xs"
                        onClick={handleReset}
                        disabled={isResetting}
                        title="Reset search, filter and sorting"
                    >
                        <RefreshCw
                            className={`mr-1.5 h-3.5 w-3.5 ${isResetting ? 'animate-spin' : ''}`}
                        />
                        Reset
                    </Button>
                </form>
            </CardHeader>

            <CardContent className="p-0">
                <Table
                    className={`transition-opacity duration-150 ${
                        isBusy ? 'opacity-60' : 'opacity-100'
                    }`}
                >
                    <TableHeader>
                        <TableRow className="bg-slate-50 hover:bg-slate-50">
                            {COLUMNS.map((col) => {
                                const sortKeyName = col.sortable;
                                const isActive = sortKeyName ? effectiveSortKey === sortKeyName : false;

                                return (
                                    <TableHead
                                        key={col.label}
                                        className={`${col.width} ${
                                            col.label === 'Event' ? 'pl-6' : ''
                                        } ${
                                            col.label === 'Date' ? 'pr-6' : ''
                                        } ${
                                            sortKeyName
                                                ? 'cursor-pointer select-none transition-colors'
                                                : ''
                                        } ${
                                            isActive
                                                ? 'bg-blue-50/80 font-bold text-blue-600'
                                                : 'hover:text-slate-900'
                                        }`}
                                        onClick={
                                            sortKeyName
                                                ? () => handleSortClick(sortKeyName)
                                                : undefined
                                        }
                                    >
                                        <span className="inline-flex items-center gap-1.5">
                                            {col.label}
                                            {sortKeyName && (
                                                isActive ? (
                                                    effectiveSortDir === 'asc' ? (
                                                        <ArrowUp className="h-4 w-4 stroke-[2.5] text-blue-600" />
                                                    ) : (
                                                        <ArrowDown className="h-4 w-4 stroke-[2.5] text-blue-600" />
                                                    )
                                                ) : (
                                                    <ArrowUpDown className="h-3.5 w-3.5 text-slate-400" />
                                                )
                                            )}
                                        </span>
                                    </TableHead>
                                );
                            })}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.length === 0 ? (
                            <TableRow>
                                <TableCell
                                    colSpan={5}
                                    className="py-10 text-center text-sm text-slate-500"
                                >
                                    No activity found.
                                </TableCell>
                            </TableRow>
                        ) : (
                            rows.map((log) => {
                                const ui = getEventUi(log.event);
                                const Icon = ui.icon;
                                const actorName = log.actor_name ?? 'System';
                                const actorRole = log.actor_role ?? '—';

                                return (
                                    <TableRow key={log.id}>
                                        <TableCell className="pl-6">
                                            <Badge
                                                variant="secondary"
                                                className={`gap-1 ${ui.badgeClass}`}
                                            >
                                                <Icon className="h-3 w-3" />
                                                {ui.label}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-sm text-slate-700">
                                            <div>{log.description}</div>
                                            {log.changes &&
                                                log.changes.length > 0 && (
                                                    <ul className="mt-1 space-y-0.5 text-xs text-slate-500">
                                                        {log.changes.map(
                                                            (c, i) => (
                                                                <li
                                                                    key={i}
                                                                    className="flex flex-wrap items-center gap-1"
                                                                >
                                                                    <span className="font-medium text-slate-600">
                                                                        {c.field}:
                                                                    </span>
                                                                    {c.old ? (
                                                                        <>
                                                                            <span className="line-through decoration-slate-300">
                                                                                {c.old}
                                                                            </span>
                                                                            <span className="text-slate-400">
                                                                                →
                                                                            </span>
                                                                        </>
                                                                    ) : null}
                                                                    <span className="text-slate-700">
                                                                        {c.new ?? '—'}
                                                                    </span>
                                                                </li>
                                                            ),
                                                        )}
                                                    </ul>
                                                )}
                                        </TableCell>
                                        <TableCell className="text-sm text-slate-800">
                                            <span className="font-semibold text-slate-900">
                                                ({actorName})
                                            </span>
                                            {actorRole && actorRole !== '—' && (
                                                <span
                                                    className={`ml-1.5 ${roleTextColorClass(actorRole)}`}
                                                >
                                                    - {actorRole}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-sm text-slate-600">
                                            {log.type}
                                        </TableCell>
                                        <TableCell className="pr-6 text-sm text-slate-500">
                                            {formatDateTime(log.created_at)}
                                        </TableCell>
                                    </TableRow>
                                );
                            })
                        )}
                    </TableBody>
                </Table>
            </CardContent>

            {rows.length > 0 && (
                <div className="flex items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 sm:px-5">
                    <p className="shrink-0 text-xs text-slate-500 sm:text-sm">
                        Showing {logs.from ?? 0}-{logs.to ?? 0} of{' '}
                        {logs.total} results
                    </p>

                    {lastPage > 3 ? (
                        (() => {
                            const prevLink = links.find((l) =>
                                l.label
                                    .replace(/&laquo;|&raquo;/g, '')
                                    .trim()
                                    .toLowerCase()
                                    .includes('previous'),
                            );
                            const nextLink = links.find((l) =>
                                l.label
                                    .replace(/&laquo;|&raquo;/g, '')
                                    .trim()
                                    .toLowerCase()
                                    .includes('next'),
                            );

                            return (
                                <div className="flex shrink-0 items-center gap-1.5">
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="outline"
                                        disabled={!prevLink?.url}
                                        onClick={() =>
                                            navigateToPage(currentPage - 1)
                                        }
                                        aria-label="Previous page"
                                        className="h-8 w-8 shrink-0 rounded-md text-sm"
                                    >
                                        <ChevronLeft className="h-4 w-4" />
                                    </Button>

                                    <Popover
                                        open={pageJumpOpen}
                                        onOpenChange={(open) => {
                                            setPageJumpOpen(open);

                                            if (open) {
                                                setPageJumpInput(
                                                    String(currentPage),
                                                );
                                            }
                                        }}
                                    >
                                        <PopoverTrigger
                                            render={
                                                <button
                                                    type="button"
                                                    className="inline-flex h-8 items-center justify-center gap-1 rounded-md border border-slate-200 px-3 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50"
                                                >
                                                    Page {currentPage} of {lastPage}
                                                </button>
                                            }
                                        />
                                        <PopoverContent
                                            align="center"
                                            className="w-48 space-y-2 p-2"
                                        >
                                            <form
                                                onSubmit={(e) => {
                                                    e.preventDefault();
                                                    const parsed = Number(pageJumpInput);

                                                    if (
                                                        !Number.isNaN(parsed) &&
                                                        parsed >= 1 &&
                                                        parsed <= lastPage
                                                    ) {
                                                        navigateToPage(parsed);
                                                        setPageJumpOpen(false);
                                                    }
                                                }}
                                                className="flex items-center gap-1.5"
                                            >
                                                <input
                                                    type="number"
                                                    min={1}
                                                    max={lastPage}
                                                    value={pageJumpInput}
                                                    onChange={(e) =>
                                                        setPageJumpInput(e.target.value)
                                                    }
                                                    placeholder={`1–${lastPage}`}
                                                    className="h-8 w-full min-w-0 rounded-md border border-slate-200 px-2 text-sm text-slate-700 outline-none focus:ring-2 focus:ring-blue-200"
                                                    autoFocus
                                                />
                                                <button
                                                    type="submit"
                                                    className="h-8 shrink-0 rounded-md bg-blue-900 px-2.5 text-xs font-semibold text-white transition-colors hover:bg-blue-950"
                                                >
                                                    Go
                                                </button>
                                            </form>

                                            <div className="max-h-56 space-y-0.5 overflow-y-auto border-t border-slate-100 pt-1.5">
                                                {Array.from(
                                                    { length: lastPage },
                                                    (_, i) => i + 1,
                                                ).map((page) => (
                                                    <button
                                                        key={page}
                                                        type="button"
                                                        onClick={() => {
                                                            navigateToPage(page);
                                                            setPageJumpOpen(false);
                                                        }}
                                                        className={`flex w-full items-center rounded-md px-2 py-1.5 text-left text-sm transition-colors ${
                                                            page === currentPage
                                                                ? 'bg-blue-50 font-medium text-blue-900'
                                                                : 'text-slate-600 hover:bg-slate-50'
                                                        }`}
                                                    >
                                                        Page {page}
                                                    </button>
                                                ))}
                                            </div>
                                        </PopoverContent>
                                    </Popover>

                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="outline"
                                        disabled={!nextLink?.url}
                                        onClick={() =>
                                            navigateToPage(currentPage + 1)
                                        }
                                        aria-label="Next page"
                                        className="h-8 w-8 shrink-0 rounded-md text-sm"
                                    >
                                        <ChevronRight className="h-4 w-4" />
                                    </Button>
                                </div>
                            );
                        })()
                    ) : (
                        <div className="flex flex-nowrap items-center gap-1">
                            {links.map((link, i) => {
                                const rawLabel = link.label
                                    .replace(/&laquo;|&raquo;/g, '')
                                    .trim();
                                const isPrev =
                                    rawLabel.toLowerCase() === 'previous';
                                const isNext =
                                    rawLabel.toLowerCase() === 'next';

                                return (
                                    <Button
                                        key={i}
                                        type="button"
                                        size="icon"
                                        variant={
                                            link.active
                                                ? 'default'
                                                : 'outline'
                                        }
                                        disabled={!link.url}
                                        onClick={() =>
                                            link.url &&
                                            navigateToPage(
                                                Number(link.label) || 1,
                                            )
                                        }
                                        aria-label={
                                            isPrev
                                                ? 'Previous page'
                                                : isNext
                                                  ? 'Next page'
                                                  : `Page ${link.label}`
                                        }
                                        className="h-8 w-8 shrink-0 rounded-md text-sm"
                                    >
                                        {isPrev ? (
                                            <ChevronLeft className="h-4 w-4" />
                                        ) : isNext ? (
                                            <ChevronRight className="h-4 w-4" />
                                        ) : (
                                            link.label
                                        )}
                                    </Button>
                                );
                            })}
                        </div>
                    )}
                </div>
            )}
        </Card>
    );
}
