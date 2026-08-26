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
    Calendar,
} from 'lucide-react';
import React, { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
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

const SORT_OPTIONS = [
    { value: 'created_at:desc', label: 'Newest First' },
    { value: 'created_at:asc', label: 'Oldest First' },
    { value: 'action:asc', label: 'Event (A - Z)' },
    { value: 'action:desc', label: 'Event (Z - A)' },
    { value: 'actor_name:asc', label: 'Actor (A - Z)' },
    { value: 'actor_name:desc', label: 'Actor (Z - A)' },
    { value: 'subject_type:asc', label: 'Type (A - Z)' },
    { value: 'subject_type:desc', label: 'Type (Z - A)' },
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
    usePoll(5000, { only: ['activityLogs'] });

    const currentPage = logs?.meta?.current_page ?? logs?.current_page ?? 1;
    const lastPage = logs?.meta?.last_page ?? logs?.last_page ?? 1;
    const total = logs?.meta?.total ?? logs?.total ?? rows.length;
    const links = logs?.links ?? [];

    const effectiveSortKey = sortKey || 'created_at';
    const effectiveSortDir = sortDir || (sortKey ? 'asc' : 'desc');

    const currentSortSelectValue = `${effectiveSortKey}:${effectiveSortDir}`;

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
                onFinish: () => setIsNavigating(false),
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

    const handleSortSelectChange = (value: string | null) => {
        if (!value) {
            return;
        }

        const [sKey, sDir] = value.split(':');
        setSortKey(sKey);
        setSortDir(sDir);
        navigateWithParams({
            sort: sKey,
            direction: sDir,
            activity_search: search.trim() || undefined,
            activity_action: action === 'all' ? undefined : action || undefined,
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
        });
    };

    const handleDateFromChange = (val: string) => {
        setDateFrom(val);
        navigateWithParams({
            date_from: val || undefined,
            date_to: dateTo || undefined,
            activity_search: search.trim() || undefined,
            activity_action: action === 'all' ? undefined : action || undefined,
            sort: sortKey || undefined,
            direction: sortDir || undefined,
        });
    };

    const handleDateToChange = (val: string) => {
        setDateTo(val);
        navigateWithParams({
            date_from: dateFrom || undefined,
            date_to: val || undefined,
            activity_search: search.trim() || undefined,
            activity_action: action === 'all' ? undefined : action || undefined,
            sort: sortKey || undefined,
            direction: sortDir || undefined,
        });
    };

    // Clickable column header: cleanly toggles between desc (newest/Z-A) and asc (oldest/A-Z)
    const handleSortClick = (columnKey: string) => {
        const isActive = effectiveSortKey === columnKey;

        let nextDir: 'asc' | 'desc' = 'desc';

        if (!isActive) {
            nextDir = columnKey === 'created_at' ? 'desc' : 'asc';
        } else {
            nextDir = effectiveSortDir === 'desc' ? 'asc' : 'desc';
        }

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
    };

    const handleReset = () => {
        setSearch('');
        setAction('');
        setDateFrom('');
        setDateTo('');
        setSortKey('');
        setSortDir('');

        router.get(
            window.location.pathname,
            {},
            {
                preserveScroll: true,
                replace: true,
                only: ['activityLogs'],
                showProgress: false,
                onStart: () => setIsResetting(true),
                onFinish: () => setIsResetting(false),
            },
        );
    };

    const goToPage = (url: string) => {
        router.get(
            url,
            {},
            {
                preserveState: true,
                preserveScroll: true,
                only: ['activityLogs'],
                showProgress: false,
                onStart: () => setIsNavigating(true),
                onFinish: () => setIsNavigating(false),
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
                            placeholder="Search action, name, role..."
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

                    {/* Sort Dropdown */}
                    <Select value={currentSortSelectValue} onValueChange={handleSortSelectChange}>
                        <SelectTrigger className="h-9 w-full text-xs sm:w-40">
                            <SelectValue placeholder="Sort by" />
                        </SelectTrigger>
                        <SelectContent>
                            {SORT_OPTIONS.map((opt) => (
                                <SelectItem key={opt.value} value={opt.value}>
                                    {opt.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {/* Date From */}
                    <div className="flex items-center gap-1.5">
                        <Calendar className="hidden h-3.5 w-3.5 text-slate-400 sm:inline-block" />
                        <Input
                            type="date"
                            title="Date From"
                            className="h-9 w-full text-xs sm:w-36"
                            value={dateFrom}
                            onChange={(e) => handleDateFromChange(e.target.value)}
                        />
                    </div>

                    {/* Date To */}
                    <div className="flex items-center gap-1.5">
                        <span className="hidden text-xs text-slate-400 sm:inline-block">to</span>
                        <Input
                            type="date"
                            title="Date To"
                            className="h-9 w-full text-xs sm:w-36"
                            value={dateTo}
                            onChange={(e) => handleDateToChange(e.target.value)}
                        />
                    </div>

                    <Button type="submit" size="sm" className="h-9 text-xs">
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

            {lastPage > 1 && (
                <div className="flex flex-col items-center justify-between gap-3 border-t border-slate-200 px-6 py-4 sm:flex-row">
                    <p className="text-xs text-slate-500">
                        Page{' '}
                        <span className="font-semibold text-slate-700">
                            {currentPage}
                        </span>{' '}
                        of{' '}
                        <span className="font-semibold text-slate-700">
                            {lastPage}
                        </span>{' '}
                        &middot; {total.toLocaleString()} total entries
                    </p>

                    <Pagination className="mx-0 w-auto justify-end">
                        <PaginationContent className="gap-1">
                            {links.map((link, index) => {
                                const isPrev = index === 0;
                                const isNext = index === links.length - 1;
                                const isEllipsis = link.label === '...';

                                if (isPrev) {
                                    return (
                                        <PaginationItem key={index}>
                                            <PaginationPrevious
                                                href={link.url ?? '#'}
                                                onClick={(e) => {
                                                    e.preventDefault();

                                                    if (link.url) {
                                                        goToPage(link.url);
                                                    }
                                                }}
                                                className={
                                                    !link.url
                                                        ? 'pointer-events-none opacity-50'
                                                        : 'cursor-pointer'
                                                }
                                            />
                                        </PaginationItem>
                                    );
                                }

                                if (isNext) {
                                    return (
                                        <PaginationItem key={index}>
                                            <PaginationNext
                                                href={link.url ?? '#'}
                                                onClick={(e) => {
                                                    e.preventDefault();

                                                    if (link.url) {
                                                        goToPage(link.url);
                                                    }
                                                }}
                                                className={
                                                    !link.url
                                                        ? 'pointer-events-none opacity-50'
                                                        : 'cursor-pointer'
                                                }
                                            />
                                        </PaginationItem>
                                    );
                                }

                                if (isEllipsis) {
                                    return (
                                        <PaginationItem key={index}>
                                            <PaginationEllipsis />
                                        </PaginationItem>
                                    );
                                }

                                return (
                                    <PaginationItem key={index}>
                                        <PaginationLink
                                            href={link.url ?? '#'}
                                            isActive={link.active}
                                            onClick={(e) => {
                                                e.preventDefault();

                                                if (link.url) {
                                                    goToPage(link.url);
                                                }
                                            }}
                                            className={
                                                link.active
                                                    ? 'bg-slate-900 text-white hover:bg-slate-800'
                                                    : 'cursor-pointer text-slate-700'
                                            }
                                        >
                                            {link.label}
                                        </PaginationLink>
                                    </PaginationItem>
                                );
                            })}
                        </PaginationContent>
                    </Pagination>
                </div>
            )}
        </Card>
    );
}
