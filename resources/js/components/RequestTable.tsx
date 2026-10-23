import { router, usePoll } from '@inertiajs/react';
import {
    Search,
    Filter,
    ChevronLeft,
    ChevronRight,
    ArrowUpDown,
    ArrowUp,
    ArrowDown,
    ListFilter,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import * as React from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { cn } from '@/lib/utils';
import { CardTitle } from '@/components/ui/card';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export function StatusBadge({
    label,
    color,
}: {
    label: string;
    color?: keyof typeof STATUS_COLORS | string;
}) {
    const dotColor = resolveStatusColor(color);

    return (
        <span className="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 dark:bg-slate-800/60 dark:text-slate-300 dark:border dark:border-slate-700/40">
            <span
                className={cn('h-1.5 w-1.5 shrink-0 rounded-full', dotColor)}
            />
            {label}
        </span>
    );
}
/** Shape of a Laravel paginator's JSON */
export interface PaginatedData<T> {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export interface ColumnDef<T> {
    header: string;
    width?: string;
    render: (row: T) => React.ReactNode;
    className?: string;
    align?: 'left' | 'right' | 'center';
    hideOnMobile?: boolean;
    sortable?: string;
}

export const STATUS_COLORS = {
    green: 'bg-emerald-500',
    'light-green': 'bg-green-300',
    'dark-green': 'bg-green-600',
    blue: 'bg-blue-500',
    purple: 'bg-violet-500',
    red: 'bg-red-500',
    orange: 'bg-amber-500',
    grey: 'bg-slate-400',
} as const;

export interface StatusOption {
    value: string;
    label: string;
    color?: keyof typeof STATUS_COLORS | string;
}

const resolveStatusColor = (color?: string) =>
    (color && STATUS_COLORS[color as keyof typeof STATUS_COLORS]) ||
    color ||
    STATUS_COLORS.grey;

interface RequestTableProps<T> {
    title?: string;
    columns: ColumnDef<T>[];
    resource: PaginatedData<T>;
    renderActions: (row: T) => React.ReactNode;
    actionsWidth?: string;
    emptyIcon?: LucideIcon;
    emptyMessage?: string;
    onPageChange?: (url: string) => void;
    search: string;
    onSearchChange: (value: string) => void;
    searchPlaceholder?: string;
    status: string;
    onStatusChange: (value: string) => void;
    statusOptions: StatusOption[];
    statusPlaceholder?: string;
    dateFrom: string;
    onDateFromChange: (value: string) => void;
    dateTo: string;
    onDateToChange: (value: string) => void;
    onFilterSubmit: (e: React.SyntheticEvent) => void;
    onFilterReset: () => void;
    pollInterval?: number;
    resourceKey?: string;
    highlightDuration?: number;
    /** Optional React node rendered beside the total in the table header. */
    customToolbar?: React.ReactNode;
}

const alignClass: Record<NonNullable<ColumnDef<unknown>['align']>, string> = {
    left: 'text-left',
    right: 'text-right',
    center: 'text-center',
};

function rowSignature(row: unknown): string {
    try {
        return JSON.stringify(row);
    } catch {
        return '';
    }
}

export default function RequestTable<T extends { id: number | string }>({
    title,
    columns,
    resource,
    renderActions,
    actionsWidth = '110px',
    emptyIcon: EmptyIcon,
    emptyMessage = 'No records found',
    onPageChange,
    search,
    onSearchChange,
    searchPlaceholder = 'Search...',
    status,
    onStatusChange,
    statusOptions,
    statusPlaceholder = 'All Status',
    dateFrom,
    onDateFromChange,
    dateTo,
    onDateToChange,
    onFilterSubmit,
    onFilterReset,
    pollInterval,
    resourceKey,
    highlightDuration = 2000,
    customToolbar,
}: RequestTableProps<T>) {
    const rows = resource.data;
    const showPagination = rows.length > 0;

    const selectedStatus = statusOptions.find((opt) => opt.value === status);

    // ---- Table-scoped loading indicator ----------------------------------
    // Drives a subtle loading bar INSIDE the table card (see the <style>
    // block + bar div below), instead of relying on Inertia's default
    // global top-progress-bar — that global bar is what made sort clicks
    // feel like "the whole website is reloading". Every navigation helper
    // below (sort, page-jump) sets this and passes showProgress: false so
    // the global bar never fires at all.
    const [isNavigating, setIsNavigating] = React.useState(false);

    const prevSignatures = React.useRef<Map<string | number, string> | null>(
        null,
    );
    const [highlightedIds, setHighlightedIds] = React.useState<
        Set<string | number>
    >(new Set());
    const highlightTimers = React.useRef<
        Map<string | number, ReturnType<typeof setTimeout>>
    >(new Map());

    React.useEffect(() => {
        const nextSignatures = new Map<string | number, string>();
        const changed: (string | number)[] = [];

        for (const row of rows) {
            const sig = rowSignature(row);
            nextSignatures.set(row.id, sig);

            if (prevSignatures.current) {
                const prevSig = prevSignatures.current.get(row.id);

                if (prevSig === undefined || prevSig !== sig) {
                    changed.push(row.id);
                }
            }
        }

        if (prevSignatures.current && changed.length > 0) {
            setHighlightedIds((prev) => {
                const next = new Set(prev);
                changed.forEach((id) => next.add(id));

                return next;
            });

            changed.forEach((id) => {
                const existing = highlightTimers.current.get(id);

                if (existing) {
                    clearTimeout(existing);
                }

                const timer = setTimeout(() => {
                    setHighlightedIds((prev) => {
                        const next = new Set(prev);
                        next.delete(id);

                        return next;
                    });
                    highlightTimers.current.delete(id);
                }, highlightDuration);

                highlightTimers.current.set(id, timer);
            });
        }

        prevSignatures.current = nextSignatures;

        const timers = highlightTimers.current;

        return () => {
            timers.forEach((t) => clearTimeout(t));
            timers.clear();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [rows]);

    const { start, stop } = usePoll(
        pollInterval ?? 300000,
        {
            only: resourceKey ? [resourceKey] : undefined,
        },
        { autoStart: false },
    );

    React.useEffect(() => {
        if (!pollInterval) {
            return;
        }

        if (process.env.NODE_ENV !== 'production' && !resourceKey) {
            console.warn(
                "[RequestTable] pollInterval is set without resourceKey — polling will reload the entire page payload instead of just this table's data.",
            );
        }

        start();

        return () => stop();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [pollInterval, resourceKey]);

    // ---- Page-jump dropdown -----------------------------------------------
    // Now scoped via `only`/`preserveState` (same as sort below) so it only
    // re-fetches this table's data instead of the whole page, and shows the
    // local loading bar instead of Inertia's global one.
    const [pageJumpOpen, setPageJumpOpen] = React.useState(false);
    const [pageJumpInput, setPageJumpInput] = React.useState('');

    const navigateToPage = (page: number) => {
        const url = new URL(window.location.href);

        if (page > 1) {
            url.searchParams.set('page', String(page));
        } else {
            url.searchParams.delete('page');
        }

        stop(); // pause polling so a stale in-flight poll can't overwrite this navigation

        router.get(
            url.pathname + url.search,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                only: resourceKey ? [resourceKey] : undefined,
                showProgress: false,
                onStart: () => setIsNavigating(true),
                onFinish: () => {
                    setIsNavigating(false);
                    start(); // resume polling now that we're settled on the new page
                },
            },
        );
    };

    // ---- Sortable column headers -------------------------------------
    const urlParams =
        typeof window !== 'undefined'
            ? new URLSearchParams(window.location.search)
            : new URLSearchParams();
    const currentSort = urlParams.get('sort') ?? '';
    const currentDirection =
        (urlParams.get('direction') as 'asc' | 'desc') || 'asc';

    // Scoped partial reload — only `resourceKey`'s data is re-fetched, the
    // URL still updates (so refresh/back-button keep working), and
    // showProgress: false + the local isNavigating bar replace Inertia's
    // global top-progress-bar so sorting no longer looks like a full
    // page reload.
    const navigateWithSort = (
        overrides: Record<string, string | undefined>,
    ) => {
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
                only: resourceKey ? [resourceKey] : undefined,
                showProgress: false,
                onStart: () => setIsNavigating(true),
                onFinish: () => {
                    setIsNavigating(false);
                    start();
                },
            },
        );
    };

    const handleSortClick = (sortKey: string) => {
        const isActiveColumn = currentSort === sortKey;

        if (!isActiveColumn) {
            navigateWithSort({ sort: sortKey, direction: 'asc' });
        } else if (currentDirection === 'asc') {
            navigateWithSort({ sort: sortKey, direction: 'desc' });
        } else {
            navigateWithSort({ sort: undefined, direction: undefined });
        }
    };

    return (
        <div className="mx-auto min-h-screen w-full max-w-7xl min-w-0 space-y-4 bg-page-background p-3 sm:p-6">
            {(title || resource.total !== undefined || customToolbar) && (
                <div className="flex flex-wrap items-center justify-between gap-2">
                    {title && (
                        <CardTitle className="bg-[#3399FF] font-['Poppins'] bg-clip-text text-3xl font-black tracking-tight text-transparent sm:text-4xl">
                            {title}
                        </CardTitle>
                    )}
                    {customToolbar && (
                        <div className="flex items-center">{customToolbar}</div>
                    )}
                </div>
            )}

            {/* ---- Filter bar: search + status + date range ---- */}
            <Card className="min-w-0 overflow-x-auto border-border shadow-sm">
                <CardContent className="p-5 sm:p-5">
                    <form onSubmit={onFilterSubmit} className="space-y-4">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-12">
                            <div className="flex flex-col gap-1.5 lg:col-span-4">
                                <label
                                    htmlFor="rt-search"
                                    className="text-xs font-medium text-muted-foreground"
                                >
                                    Search
                                </label>
                                <div className="relative">
                                    <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        id="rt-search"
                                        placeholder={searchPlaceholder}
                                        value={search}
                                        onChange={(e) =>
                                            onSearchChange(e.target.value)
                                        }
                                        className="h-10 rounded-lg pl-9"
                                    />
                                </div>
                            </div>

                            <div className="flex flex-col gap-1.5 lg:col-span-2">
                                <label className="text-xs font-medium text-muted-foreground">
                                    Status
                                </label>
                                <Select
                                    value={status || 'all'}
                                    onValueChange={(v) =>
                                        onStatusChange(
                                            v === null || v === 'all' ? '' : v,
                                        )
                                    }
                                >
                                    <SelectTrigger className="h-10 w-full rounded-lg border-input bg-background shadow-sm transition-colors hover:bg-muted focus:ring-2 focus:ring-ring/20">
                                        <SelectValue
                                            placeholder={statusPlaceholder}
                                        >
                                            {status && selectedStatus ? (
                                                <span className="flex items-center gap-2">
                                                    <span
                                                        className={cn(
                                                            'h-2 w-2 shrink-0 rounded-full',
                                                            resolveStatusColor(
                                                                selectedStatus.color,
                                                            ),
                                                        )}
                                                    />
                                                    {selectedStatus.label}
                                                </span>
                                            ) : (
                                                <span className="flex items-center gap-2 text-muted-foreground">
                                                    <ListFilter className="h-3.5 w-3.5" />
                                                    {statusPlaceholder}
                                                </span>
                                            )}
                                        </SelectValue>
                                    </SelectTrigger>
                                    <SelectContent
                                        sideOffset={6}
                                        className="min-w-(--anchor-width) rounded-lg border-border shadow-lg"
                                    >
                                        <SelectItem
                                            value="all"
                                            className="cursor-pointer rounded-md"
                                        >
                                            <span className="flex items-center gap-2 text-foreground">
                                                <ListFilter className="h-3.5 w-3.5" />
                                                {statusPlaceholder}
                                            </span>
                                        </SelectItem>
                                        <div className="my-1 h-px bg-border" />
                                        <div className="px-2 py-1.5 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                            Filter by status
                                        </div>
                                        {statusOptions.map((opt) => (
                                            <SelectItem
                                                key={opt.value}
                                                value={opt.value}
                                                className="cursor-pointer rounded-md"
                                            >
                                                <span className="flex items-center gap-2">
                                                    <span
                                                        className={cn(
                                                            'h-2 w-2 shrink-0 rounded-full',
                                                            resolveStatusColor(
                                                                opt.color,
                                                            ),
                                                        )}
                                                    />
                                                    {opt.label}
                                                </span>
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="flex flex-col gap-1.5 lg:col-span-4">
                                <label className="text-xs font-medium text-muted-foreground">
                                    Date Range
                                </label>
                                <div className="flex h-10 items-center overflow-hidden rounded-lg border border-input bg-transparent focus-within:ring-2 focus-within:ring-ring/50">
                                    <input
                                        id="rt-date-from"
                                        type="date"
                                        value={dateFrom}
                                        onChange={(e) =>
                                            onDateFromChange(e.target.value)
                                        }
                                        aria-label="Date from"
                                        className="h-full min-w-0 flex-1 border-0 bg-transparent px-3 text-sm text-foreground [color-scheme:light] dark:[color-scheme:dark] outline-none"
                                    />
                                    <span className="shrink-0 px-1 text-xs font-medium text-muted-foreground">
                                        to
                                    </span>
                                    <input
                                        id="rt-date-to"
                                        type="date"
                                        value={dateTo}
                                        onChange={(e) =>
                                            onDateToChange(e.target.value)
                                        }
                                        aria-label="Date to"
                                        className="h-full min-w-0 flex-1 border-0 bg-transparent px-3 text-sm text-foreground [color-scheme:light] dark:[color-scheme:dark] outline-none"
                                    />
                                </div>
                            </div>

                            <div className="flex items-end gap-2 lg:col-span-2">
                                <Button
                                    type="submit"
                                    className="h-10 flex-1 gap-2 rounded-lg bg-primary text-primary-foreground hover:bg-primary/90 lg:flex-none"
                                >
                                    <Filter className="h-4 w-4" />
                                    <span>Filter</span>
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="h-10 rounded-lg"
                                    onClick={onFilterReset}
                                >
                                    Reset
                                </Button>
                            </div>
                        </div>
                    </form>
                </CardContent>
            </Card>

            {/* ---- Table ---- */}
            <Card className="min-w-0 gap-0 overflow-hidden border-border p-0 py-0 shadow-sm">
                {/* Local, table-scoped loading bar — replaces Inertia's global
                    top-progress-bar for sort/page-jump navigations. */}
                <div className="relative h-0.5 w-full overflow-hidden bg-transparent">
                    {isNavigating && (
                        <div className="absolute inset-0 animate-[table-loading-bar_1s_ease-in-out_infinite] bg-primary" />
                    )}
                </div>
                <style>{`
                    @keyframes table-loading-bar {
                        0% { transform: translateX(-100%); }
                        100% { transform: translateX(100%); }
                    }
                `}</style>
                <CardContent className="min-w-0 overflow-x-auto p-0">
                    <Table
                        className={`transition-opacity duration-150 ${
                            isNavigating ? 'opacity-60' : 'opacity-100'
                        }`}
                    >
                        <TableHeader>
                            <TableRow className="border-b border-border hover:bg-transparent">
                                {columns.map((col, i) => {
                                    const isActiveSort =
                                        col.sortable &&
                                        currentSort === col.sortable;

                                    const headerLabel = (
                                        <span className="inline-flex items-center gap-1.5">
                                            {col.header}
                                            {col.sortable &&
                                                (isActiveSort ? (
                                                    currentDirection ===
                                                    'asc' ? (
                                                        <ArrowUp className="h-3 w-3 text-primary" />
                                                    ) : (
                                                        <ArrowDown className="h-3 w-3 text-primary" />
                                                    )
                                                ) : (
                                                    <ArrowUpDown className="h-3 w-3 text-muted-foreground/60" />
                                                ))}
                                        </span>
                                    );

                                    return (
                                        <TableHead
                                            key={i}
                                            style={
                                                col.width
                                                    ? { width: col.width }
                                                    : undefined
                                            }
                                            onClick={
                                                col.sortable
                                                    ? () =>
                                                          handleSortClick(
                                                              col.sortable!,
                                                          )
                                                    : undefined
                                            }
                                            className={cn(
                                                'h-11 bg-muted/50 text-xs font-semibold tracking-wide whitespace-nowrap text-muted-foreground uppercase',
                                                i === 0 && 'pl-4 sm:pl-6',
                                                col.align &&
                                                    alignClass[col.align],
                                                col.hideOnMobile &&
                                                    'hidden md:table-cell',
                                                col.sortable &&
                                                    'cursor-pointer select-none hover:text-foreground',
                                                col.className,
                                            )}
                                        >
                                            {headerLabel}
                                        </TableHead>
                                    );
                                })}
                                <TableHead
                                    style={{ width: actionsWidth }}
                                    className="h-11 bg-muted/50 pr-4 text-right text-xs font-semibold tracking-wide text-muted-foreground uppercase sm:pr-6"
                                >
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            {rows.length === 0 ? (
                                <TableRow className="hover:bg-transparent">
                                    <TableCell
                                        colSpan={columns.length + 1}
                                        className="h-56"
                                    >
                                        <div className="flex flex-col items-center justify-center gap-2 text-muted-foreground">
                                            {EmptyIcon && (
                                                <EmptyIcon
                                                    className="h-8 w-8"
                                                    strokeWidth={1.5}
                                                />
                                            )}
                                            <p className="text-sm font-medium text-muted-foreground">
                                                {emptyMessage}
                                            </p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ) : (
                                rows.map((row, i) => {
                                    const isHighlighted = highlightedIds.has(
                                        row.id,
                                    );

                                    return (
                                        <TableRow
                                            key={row.id}
                                            className={cn(
                                                'border-b border-border transition-colors duration-700 last:border-0 hover:bg-muted/50',
                                                isHighlighted
                                                    ? 'animate-[row-flash_2s_ease-out] bg-primary/10'
                                                    : i % 2 === 1 &&
                                                          'bg-muted/20',
                                            )}
                                        >
                                            {columns.map((col, j) => (
                                                <TableCell
                                                    key={j}
                                                    style={
                                                        col.width
                                                            ? {
                                                                  width: col.width,
                                                              }
                                                            : undefined
                                                    }
                                                    className={cn(
                                                        'py-3 text-sm text-foreground',
                                                        j === 0 &&
                                                            'pl-4 font-medium text-foreground sm:pl-6',
                                                        col.align &&
                                                            alignClass[
                                                                col.align
                                                            ],
                                                        col.hideOnMobile &&
                                                            'hidden md:table-cell',
                                                        col.className,
                                                    )}
                                                >
                                                    {col.render(row)}
                                                </TableCell>
                                            ))}
                                            <TableCell
                                                style={{ width: actionsWidth }}
                                                className="py-3 pr-4 text-right sm:pr-6"
                                            >
                                                {renderActions(row)}
                                            </TableCell>
                                        </TableRow>
                                    );
                                })
                            )}
                        </TableBody>
                    </Table>
                </CardContent>

                {showPagination && (
                    <div className="flex items-center justify-between gap-3 border-t border-border px-4 py-3 sm:px-5">
                        <p className="shrink-0 text-xs text-muted-foreground sm:text-sm">
                            Showing {resource.from ?? 0}-{resource.to ?? 0} of{' '}
                            {resource.total} results
                        </p>

                        {resource.last_page > 5 ? (
                            (() => {
                                const prevLink = resource.links.find((l) =>
                                    l.label
                                        .replace(/&laquo;|&raquo;/g, '')
                                        .trim()
                                        .toLowerCase()
                                        .includes('previous'),
                                );
                                const nextLink = resource.links.find((l) =>
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
                                                navigateToPage(
                                                    resource.current_page - 1,
                                                )
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
                                                        String(
                                                            resource.current_page,
                                                        ),
                                                    );
                                                }
                                            }}
                                        >
                                            <PopoverTrigger>
                                                <span
                                                    role="button"
                                                    tabIndex={0}
                                                    className="inline-flex h-8 cursor-pointer items-center justify-center gap-1 rounded-md border border-input bg-background px-3 text-sm font-medium text-foreground transition-colors hover:bg-muted"
                                                >
                                                    Page {resource.current_page}{' '}
                                                    of {resource.last_page}
                                                </span>
                                            </PopoverTrigger>
                                            <PopoverContent
                                                align="center"
                                                className="w-48 space-y-2 p-2 border-border bg-popover"
                                            >
                                                <form
                                                    onSubmit={(e) => {
                                                        e.preventDefault();
                                                        const parsed =
                                                            Number(
                                                                pageJumpInput,
                                                            );

                                                        if (
                                                            !Number.isNaN(
                                                                parsed,
                                                            ) &&
                                                            parsed >= 1 &&
                                                            parsed <=
                                                                resource.last_page
                                                        ) {
                                                            navigateToPage(
                                                                parsed,
                                                            );
                                                            setPageJumpOpen(
                                                                false,
                                                            );
                                                        }
                                                    }}
                                                    className="flex items-center gap-1.5"
                                                >
                                                    <input
                                                        type="number"
                                                        min={1}
                                                        max={resource.last_page}
                                                        value={pageJumpInput}
                                                        onChange={(e) =>
                                                            setPageJumpInput(
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder={`1–${resource.last_page}`}
                                                        className="h-8 w-full min-w-0 rounded-md border border-input bg-background px-2 text-sm text-foreground outline-none focus:ring-2 focus:ring-ring/20"
                                                        autoFocus
                                                    />
                                                    <button
                                                        type="submit"
                                                        className="h-8 shrink-0 rounded-md bg-primary px-2.5 text-xs font-semibold text-primary-foreground transition-colors hover:bg-primary/90"
                                                    >
                                                        Go
                                                    </button>
                                                </form>

                                                <div className="max-h-56 space-y-0.5 overflow-y-auto border-t border-border pt-1.5">
                                                    {Array.from(
                                                        {
                                                            length: resource.last_page,
                                                        },
                                                        (_, i) => i + 1,
                                                    ).map((page) => (
                                                        <button
                                                            key={page}
                                                            type="button"
                                                            onClick={() => {
                                                                navigateToPage(
                                                                  page,
                                                                );
                                                                setPageJumpOpen(
                                                                  false,
                                                                );
                                                            }}
                                                            className={`flex w-full items-center rounded-md px-2 py-1.5 text-left text-sm transition-colors ${
                                                                page ===
                                                                resource.current_page
                                                                    ? 'bg-primary/15 font-medium text-primary'
                                                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground'
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
                                                navigateToPage(
                                                    resource.current_page + 1,
                                                )
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
                                {resource.links.map((link, i) => {
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
                                                onPageChange?.(link.url)
                                            }
                                            aria-label={
                                                isPrev
                                                    ? 'Previous page'
                                                    : isNext
                                                      ? 'Next page'
                                                      : rawLabel
                                            }
                                            className={cn(
                                                'h-8 w-8 shrink-0 rounded-md text-sm',
                                                link.active &&
                                                    'bg-primary text-primary-foreground hover:bg-primary/90',
                                            )}
                                        >
                                            {isPrev ? (
                                                <ChevronLeft className="h-4 w-4" />
                                            ) : isNext ? (
                                                <ChevronRight className="h-4 w-4" />
                                            ) : (
                                                rawLabel
                                            )}
                                        </Button>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                )}
            </Card>
        </div>
    );
}
