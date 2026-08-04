import * as React from 'react';
import { router, usePoll } from '@inertiajs/react';
import { Search, Filter, ChevronLeft, ChevronRight } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

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
        <span className="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
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
    /**
     * Hide this column below the `md` breakpoint (< 768px).
     * Use for lower-priority columns (email, dates, secondary IDs) so the
     * table stays scannable on phones without needing horizontal scroll
     * for the columns that actually matter there.
     */
    hideOnMobile?: boolean;
}

/**
 * Preset status dot colors — pass one of these keys as `StatusOption.color`,
 * or fall back to any raw Tailwind bg-* class if you need something custom.
 *   green  -> approved / active / paid
 *   red    -> rejected / inactive / overdue
 *   orange -> pending / in review
 *   grey   -> default / unknown / draft
 */
export const STATUS_COLORS = {
    green: 'bg-emerald-500',
    red: 'bg-red-500',
    orange: 'bg-amber-500',
    grey: 'bg-slate-400',
} as const;

export interface StatusOption {
    value: string;
    label: string;
    /** "green" | "red" | "orange" | "grey", or a raw Tailwind bg-* class */
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

    /** Required — this component assumes bespoke per-row actions (View/Process/Edit, etc.) */
    renderActions: (row: T) => React.ReactNode;
    actionsWidth?: string;

    emptyIcon?: LucideIcon;
    emptyMessage?: string;
    onPageChange?: (url: string) => void;

    // ---- Filter bar (search + status + date range), all controlled from the page ----
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

    onFilterSubmit: (e: React.FormEvent) => void;
    onFilterReset: () => void;

    /**
     * Enables live polling for this table. Pass an interval in ms (e.g. 5000).
     * Omit entirely to leave the table static (no polling, no behavior change).
     * Requires `resourceKey` to be set.
     */
    pollInterval?: number;
    /** The Inertia prop key that `resource` is bound to on this page (e.g. 'requests').
     *  Required when `pollInterval` is set, so polling can reload *only* this prop
     *  instead of the whole page payload. */
    resourceKey?: string;
    /**
     * How long the highlight animation stays on a new/changed row, in ms.
     * Defaults to 2000.
     */
    highlightDuration?: number;
}

const alignClass: Record<NonNullable<ColumnDef<unknown>['align']>, string> = {
    left: 'text-left',
    right: 'text-right',
    center: 'text-center',
};

// Shallow content signature for a row, used to detect "changed" (not just "new").
// Falls back gracefully for any row shape — doesn't need to know the row's fields.
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
}: RequestTableProps<T>) {
    const rows = resource.data;
    const showPagination = rows.length > 0;

    const selectedStatus = statusOptions.find((opt) => opt.value === status);

    // ---- Live update detection -------------------------------------------
    // Tracks id -> content signature from the last render, so we can tell
    // which rows are brand new vs. changed vs. untouched after a poll.
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
                // New row (wasn't present before) or changed content.
                if (prevSig === undefined || prevSig !== sig) {
                    changed.push(row.id);
                }
            }
        }

        // Skip highlighting on the very first render — nothing to diff against yet.
        if (prevSignatures.current && changed.length > 0) {
            setHighlightedIds((prev) => {
                const next = new Set(prev);
                changed.forEach((id) => next.add(id));
                return next;
            });

            changed.forEach((id) => {
                const existing = highlightTimers.current.get(id);
                if (existing) clearTimeout(existing);

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

        return () => {
            highlightTimers.current.forEach((t) => clearTimeout(t));
            highlightTimers.current.clear();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [rows]);

    // ---- Polling -------------------------------------------------------
    // `only` scopes the reload to this resource's prop so polling stays cheap —
    // without it, every tick would re-fetch the entire page's props (including
    // whatever search/status/date filters are active, which is fine, but no
    // need to also refetch unrelated page props).
    const { start, stop } = usePoll(
        pollInterval ?? 5000,
        {
            only: resourceKey ? [resourceKey] : undefined,
            preserveScroll: true,
            preserveState: true,
        },
        { autoStart: false },
    );

    React.useEffect(() => {
        if (!pollInterval) return;

        if (process.env.NODE_ENV !== 'production' && !resourceKey) {
            console.warn(
                "[RequestTable] pollInterval is set without resourceKey — polling will reload the entire page payload instead of just this table's data.",
            );
        }

        start();
        return () => stop();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [pollInterval, resourceKey]);

    // ---- Page-jump dropdown (only part changed per this request) ---------
    // Same fix as ResourceTable: once there are more than 10 pages, the
    // button row gets replaced with Prev / a searchable "Page X of Y"
    // dropdown / Next. Deliberately a plain full reload (no preserveState,
    // no `only`) — that's what fixed the "click page 2, land back on
    // page 1" bug there, so the same approach is used here from the start.
    const [pageJumpOpen, setPageJumpOpen] = React.useState(false);
    const [pageJumpInput, setPageJumpInput] = React.useState('');

    const navigateToPage = (page: number) => {
        const url = new URL(window.location.href);

        if (page > 1) {
            url.searchParams.set('page', String(page));
        } else {
            url.searchParams.delete('page');
        }

        router.get(
            url.pathname + url.search,
            {},
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <div className="mx-auto min-h-screen w-full max-w-7xl min-w-0 space-y-4 bg-slate-50 p-3 sm:p-6">
            {(title || resource.total !== undefined) && (
                <div className="flex flex-wrap items-center justify-between gap-2">
                    {title && (
                        <h2
                            className="max-w-xs truncate text-base font-semibold text-slate-900 sm:max-w-md sm:text-lg"
                            title={title}
                        >
                            {title}
                        </h2>
                    )}
                    {resource.total !== undefined && (
                        <span className="text-xs text-slate-500 sm:text-sm">
                            {resource.total.toLocaleString()} total
                        </span>
                    )}
                </div>
            )}

            {/* ---- Filter bar: search + status + date range ---- */}
            <Card className="min-w-0 overflow-x-auto border-slate-200/70 shadow-sm">
                <CardContent className="p-4 sm:p-5">
                    <form onSubmit={onFilterSubmit} className="space-y-4">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-12">
                            {/* Search — widest field since it's the primary filter */}
                            <div className="flex flex-col gap-1.5 lg:col-span-4">
                                <label
                                    htmlFor="rt-search"
                                    className="text-xs font-medium text-slate-600"
                                >
                                    Search
                                </label>
                                <div className="relative">
                                    <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
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

                            {/* Status */}
                            <div className="flex flex-col gap-1.5 lg:col-span-2">
                                <label className="text-xs font-medium text-slate-600">
                                    Status
                                </label>
                                <Select
                                    value={status || 'all'}
                                    onValueChange={(v) =>
                                        onStatusChange(v === 'all' ? '' : v)
                                    }
                                >
                                    <SelectTrigger className="h-10 w-full rounded-lg">
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
                                                statusPlaceholder
                                            )}
                                        </SelectValue>
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            {statusPlaceholder}
                                        </SelectItem>
                                        {statusOptions.map((opt) => (
                                            <SelectItem
                                                key={opt.value}
                                                value={opt.value}
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

                            {/* Date range — grouped into one connected control with a
                  shared border and a small "to" divider, instead of two
                  identical floating pill inputs. */}
                            <div className="flex flex-col gap-1.5 lg:col-span-4">
                                <label className="text-xs font-medium text-slate-600">
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
                                        className="h-full min-w-0 flex-1 border-0 bg-transparent px-3 text-sm text-slate-700 [color-scheme:light] outline-none"
                                    />
                                    <span className="shrink-0 px-1 text-xs font-medium text-slate-400">
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
                                        className="h-full min-w-0 flex-1 border-0 bg-transparent px-3 text-sm text-slate-700 [color-scheme:light] outline-none"
                                    />
                                </div>
                            </div>

                            {/* Actions */}
                            <div className="flex items-end gap-2 lg:col-span-2">
                                <Button
                                    type="submit"
                                    className="h-10 flex-1 gap-2 rounded-lg bg-blue-900 text-white hover:bg-blue-950 lg:flex-none"
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
            <Card className="min-w-0 overflow-hidden border-slate-200/70 py-0 shadow-sm">
                <CardContent className="min-w-0 overflow-x-auto p-0">
                    <Table>
                        <TableHeader>
                            <TableRow className="border-b border-slate-200 hover:bg-transparent">
                                {columns.map((col, i) => (
                                    <TableHead
                                        key={i}
                                        style={
                                            col.width
                                                ? { width: col.width }
                                                : undefined
                                        }
                                        className={cn(
                                            'h-11 bg-slate-50/80 text-xs font-semibold tracking-wide whitespace-nowrap text-slate-600 uppercase',
                                            i === 0 && 'pl-4 sm:pl-6',
                                            col.align && alignClass[col.align],
                                            col.hideOnMobile &&
                                                'hidden md:table-cell',
                                            col.className,
                                        )}
                                    >
                                        {col.header}
                                    </TableHead>
                                ))}
                                <TableHead
                                    style={{ width: actionsWidth }}
                                    className="h-11 bg-white pr-4 text-right text-xs font-semibold tracking-wide text-slate-500 uppercase sm:pr-6"
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
                                        <div className="flex flex-col items-center justify-center gap-2 text-slate-400">
                                            {EmptyIcon && (
                                                <EmptyIcon
                                                    className="h-8 w-8"
                                                    strokeWidth={1.5}
                                                />
                                            )}
                                            <p className="text-sm font-medium text-slate-500">
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
                                                'border-b border-slate-100 transition-colors duration-700 last:border-0 hover:bg-blue-50/40',
                                                isHighlighted
                                                    ? 'animate-[row-flash_2s_ease-out] bg-blue-50'
                                                    : i % 2 === 1 &&
                                                          'bg-slate-50/30',
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
                                                        'py-3 text-sm text-slate-700',
                                                        j === 0 &&
                                                            'pl-4 font-medium text-slate-900 sm:pl-6',
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
                    <div className="flex items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 sm:px-5">
                        <p className="shrink-0 text-xs text-slate-500 sm:text-sm">
                            Showing {resource.from ?? 0}-{resource.to ?? 0} of{' '}
                            {resource.total} results
                        </p>

                        {resource.last_page > 3 ? (
                            // ---- Page-jump dropdown (>1 pages) ----
                            // Same mechanism as ResourceTable: Prev / a
                            // popover with a type-to-jump input plus a
                            // scrollable page list / Next. Picking a page
                            // does a plain full reload — no preserveState,
                            // no `only` — since that's what fixed the
                            // "lands back on page 1" bug in ResourceTable.
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
                                            <PopoverTrigger asChild>
                                                <button
                                                    type="button"
                                                    className="inline-flex h-8 items-center justify-center gap-1 rounded-md border border-slate-200 px-3 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50"
                                                >
                                                    Page {resource.current_page}{' '}
                                                    of {resource.last_page}
                                                </button>
                                            </PopoverTrigger>
                                            <PopoverContent
                                                align="center"
                                                className="w-48 space-y-2 p-2"
                                            >
                                                {/* Type-to-jump input — the
                                                    part that makes a long
                                                    page list actually usable
                                                    instead of just scrolling
                                                    through every page. */}
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

                                                {/* Scrollable browse list —
                                                    still available for
                                                    picking visually instead
                                                    of typing, if preferred. */}
                                                <div className="max-h-56 space-y-0.5 overflow-y-auto border-t border-slate-100 pt-1.5">
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
                            // ---- Standard button row (10 pages or fewer, unchanged) ----
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
                                                    'bg-blue-900 text-white hover:bg-blue-950',
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
