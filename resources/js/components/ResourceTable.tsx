import { Head, Link, router, usePoll } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import {
    Plus,
    Pencil,
    Trash2,
    ChevronLeft,
    ChevronRight,
    Search,
    ArrowUpDown,
    ArrowUp,
    ArrowDown,
    Check,
    RefreshCw,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import React, { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import { useConfirm } from '@/components/confirm-dialog';
import { Card, CardContent } from '@/components/ui/card';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { CardTitle } from '@/components/ui/card';

// ============ TYPE DEFINITIONS ============
export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface PaginatedData<T> {
    data: T[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
    current_page: number;
    last_page: number;
}

export interface ColumnDef<T> {
    header: string;
    render: (row: T, index: number) => React.ReactNode;
    className?: string;
    width?: string;
    sortable?: boolean | string;
}

export interface SortOptionDef {
    label: string;
    sort: string;
    direction: 'asc' | 'desc';
}

export interface ResourceTableProps<T extends { id: number }> {
    title: string;
    description?: string;
    icon?: LucideIcon;
    addLabel: string;
    addHref: NonNullable<InertiaLinkProps['href']>;
    columns: ColumnDef<T>[];
    resource: PaginatedData<T>;
    resourceKey?: string;
    editHref: (row: T) => NonNullable<InertiaLinkProps['href']>;
    deleteUrl: (id: number) => string;
    emptyIcon: LucideIcon;
    emptyMessage: string;
    deleteConfirmMessage?: string;
    actionsWidth?: string;
    filters?: React.ReactNode;
    pollInterval?: number;
    highlightDuration?: number;
    searchPlaceholder?: string;
    searchValue?: string;
    onSearchChange?: (value: string) => void;
    /** Called when the search is actually submitted (button click or Enter). Only relevant in controlled mode. */
    onSearchSubmit?: (value: string) => void;
    sortOptions?: SortOptionDef[];
}

function rowSignature(row: unknown): string {
    try {
        return JSON.stringify(row);
    } catch {
        return '';
    }
}

// ============ COMPONENT ============
export default function ResourceTable<T extends { id: number }>({
    title,
    description,
    icon: HeaderIcon,
    addLabel,
    addHref,
    columns,
    resource,
    resourceKey,
    editHref,
    deleteUrl,
    emptyIcon: EmptyIcon,
    emptyMessage,
    deleteConfirmMessage = 'Are you sure you want to delete this record?',
    actionsWidth = '140px',
    filters,
    pollInterval,
    highlightDuration = 2000,
    searchPlaceholder = 'Search...',
    searchValue,
    onSearchChange,
    onSearchSubmit,
    sortOptions,
}: ResourceTableProps<T>) {
    const confirm = useConfirm();

    const [optimisticSort, setOptimisticSort] = useState<{
        key: string;
        direction: 'asc' | 'desc';
    } | null>(null);

    function compareValues(a: unknown, b: unknown): number {
        if (a === b) {
            return 0;
        }

        if (a === null || a === undefined) {
            return -1;
        }

        if (b === null || b === undefined) {
            return 1;
        }

        const aNum = typeof a === 'number' ? a : Number(a);
        const bNum = typeof b === 'number' ? b : Number(b);

        if (!Number.isNaN(aNum) && !Number.isNaN(bNum)) {
            return aNum - bNum;
        }

        const aDate = Date.parse(String(a));
        const bDate = Date.parse(String(b));

        if (!Number.isNaN(aDate) && !Number.isNaN(bDate)) {
            return aDate - bDate;
        }

        return String(a).localeCompare(String(b));
    }

    const displayData = useMemo(() => {
        if (!optimisticSort) {
            return resource.data;
        }

        return [...resource.data].sort((rowA, rowB) => {
            const { key: sortKey, direction } = optimisticSort;
            const valueA = (rowA as Record<string, unknown>)[sortKey];
            const valueB = (rowB as Record<string, unknown>)[sortKey];
            const result = compareValues(valueA, valueB);

            return direction === 'asc' ? result : -result;
        });
    }, [optimisticSort, resource.data]);

    function optimisticallySort(sortKey: string, direction: 'asc' | 'desc') {
        setOptimisticSort({ key: sortKey, direction });
    }

    const prevSignatures = useRef<Map<number, string> | null>(null);
    const [highlightedIds, setHighlightedIds] = useState<Set<number>>(
        new Set(),
    );
    const highlightTimers = useRef<Map<number, ReturnType<typeof setTimeout>>>(
        new Map(),
    );

    useEffect(() => {
        const nextSignatures = new Map<number, string>();
        const changed: number[] = [];

        for (const row of resource.data) {
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
    }, [resource.data]);

    const { start, stop } = usePoll(
        pollInterval ?? 300000,
        {
            only: resourceKey ? [resourceKey] : undefined,
        },
        { autoStart: false },
    );

    useEffect(() => {
        if (!pollInterval) {
            return;
        }

        if (process.env.NODE_ENV !== 'production' && !resourceKey) {
            console.warn(
                "[ResourceTable] pollInterval is set without resourceKey — polling will reload the entire page payload instead of just this table's data.",
            );
        }

        start();

        return () => stop();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [pollInterval, resourceKey]);

    const handleDeleteClick = async (id: number) => {
        if (pollInterval) {
            stop();
        }

        const ok = await confirm({
            title: 'Confirm Delete',
            description: deleteConfirmMessage,
            confirmLabel: 'Delete',
            variant: 'destructive',
        });

        if (!ok) {
            if (pollInterval) {
                start();
            }

            return;
        }

        router.delete(deleteUrl(id), {
            preserveScroll: true,
            onError: () => {
                toast.error('Something went wrong while deleting.');
            },
            onFinish: () => {
                if (pollInterval) {
                    start();
                }
            },
        });
    };

    const [isNavigating, setIsNavigating] = useState(false);

    const navigateWithParams = (
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

        if (pollInterval) {
            stop();
        }

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

                    if (pollInterval) {
                        start();
                    }
                },
            },
        );
    };

    // ---- Built-in search (only when the parent isn't already
    // controlling search itself via searchValue/onSearchChange) --------
    // Submits on button click / Enter key press instead of live-as-you-type,
    // so the request only fires once the person is done typing and confirms.
    // Note: matching should be case-insensitive on the BACKEND — if you're
    // on Postgres, plain `LIKE` is case-sensitive there, so the controller
    // needs `ILIKE` (or `whereRaw('LOWER(col) LIKE ?', ...)`) for "NoRsu" to
    // match "norsu". No frontend change can fix that half of this.
    const isControlledSearch = onSearchChange !== undefined;

    const [internalSearch, setInternalSearch] = useState<string>(() => {
        if (typeof window === 'undefined') {
            return '';
        }

        return new URLSearchParams(window.location.search).get('search') ?? '';
    });

    const effectiveSearchValue = isControlledSearch
        ? (searchValue ?? '')
        : internalSearch;

    const handleSearchInputChange = (value: string) => {
        if (isControlledSearch) {
            onSearchChange?.(value);
        } else {
            setInternalSearch(value);
        }
    };

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (isControlledSearch) {
            onSearchSubmit?.(effectiveSearchValue);

            return;
        }

        navigateWithParams({ search: effectiveSearchValue || undefined });
    };

    const urlParams =
        typeof window !== 'undefined'
            ? new URLSearchParams(window.location.search)
            : new URLSearchParams();
    const currentSort = urlParams.get('sort') ?? '';
    const currentDirection =
        (urlParams.get('direction') as 'asc' | 'desc') || 'asc';

    const handleSortClick = (sortKey: string) => {
        const isActiveColumn = currentSort === sortKey;

        if (!isActiveColumn) {
            optimisticallySort(sortKey, 'asc');
            navigateWithParams({ sort: sortKey, direction: 'asc' });
        } else if (currentDirection === 'asc') {
            optimisticallySort(sortKey, 'desc');
            navigateWithParams({ sort: sortKey, direction: 'desc' });
        } else {
            setOptimisticSort(null);
            navigateWithParams({ sort: undefined, direction: undefined });
        }
    };

    const activeSortValue = sortOptions?.find(
        (o) => o.sort === currentSort && o.direction === currentDirection,
    )
        ? `${currentSort}:${currentDirection}`
        : '';

    const handleSortSelectChange = (value: string) => {
        const [sort, direction] = value.split(':') as [string, 'asc' | 'desc'];
        optimisticallySort(sort, direction);
        navigateWithParams({ sort, direction });
    };
    // ---- Full reset button --------------------------------------
    // Unlike the plain reload above (same view, fresh data), this clears
    // search, sort, and any query-string filters entirely and returns the
    // table to its default unfiltered/unsorted state — a hard reset, not
    // just a refetch. Deliberately NOT scoped via `only`/`preserveState`
    // so the URL and every derived prop genuinely clear.

    const [isResetting, setIsResetting] = useState(false);

    const handleReloadAndReset = () => {
        setInternalSearch('');

        if (isControlledSearch) {
            onSearchChange?.('');
            onSearchSubmit?.('');
        }

        const url = new URL(window.location.href);
        url.search = '';

        router.get(
            url.pathname,
            {},
            {
                preserveScroll: true,
                replace: true,
                showProgress: false,
                onStart: () => setIsResetting(true),
                onFinish: () => setIsResetting(false),
            },
        );
    };

    // ---- Page-jump dropdown (copied from RequestTable, unchanged) ----
    // Once there are more than 3 pages, the button row is replaced with
    // Prev / a searchable "Page X of Y" dropdown / Next. Deliberately a
    // plain full reload (no preserveState, no `only`) — that's what fixed
    // the "click page 2, land back on page 1" bug there.
    const [pageJumpOpen, setPageJumpOpen] = useState(false);
    const [pageJumpInput, setPageJumpInput] = useState('');

    const navigateToPage = (page: number) => {
        const url = new URL(window.location.href);

        if (page > 1) {
            url.searchParams.set('page', String(page));
        } else {
            url.searchParams.delete('page');
        }

        if (pollInterval) {
            stop();
        }

        router.get(
            url.pathname + url.search,
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    if (pollInterval) {
                        start();
                    }
                },
            },
        );
    };

    return (
        <div className="mx-auto min-h-screen w-full max-w-7xl min-w-0 space-y-4 bg-page-background p-3 sm:p-6">
            <Head title={title} />

            <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-border bg-card p-5 text-card-foreground shadow-sm">
                <div className="flex items-center gap-4">
                    {HeaderIcon && (
                        <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary/10 dark:bg-primary/20">
                            <HeaderIcon className="h-5 w-5 text-primary" />
                        </div>
                    )}
                    <div>
                        <CardTitle className="bg-[#3399FF] font-['Poppins'] bg-clip-text text-3xl font-black tracking-tight text-transparent sm:text-4xl">
                            {title}
                        </CardTitle>
                        {description && (
                            <p className="text-sm text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>
                </div>
                <Link
                    href={addHref}
                    className="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground shadow-sm transition-colors hover:bg-primary/90"
                >
                    <Plus className="h-4 w-4" />
                    {addLabel}
                </Link>
            </div>

            <Card className="w-full gap-0 overflow-hidden rounded-2xl border-border bg-card py-0 text-card-foreground shadow-sm">
                <div className="relative h-0.5 w-full overflow-hidden bg-transparent">
                    {(isNavigating || isResetting) && (
                        <div className="absolute inset-0 animate-[table-loading-bar_1s_ease-in-out_infinite] bg-blue-500" />
                    )}
                </div>
                <style>{`
                    @keyframes table-loading-bar {
                        0% { transform: translateX(-100%); }
                        100% { transform: translateX(100%); }
                    }
                `}</style>
                {/* ---- Toolbar row: search form (button/Enter submit) + optional extra filters + sort dropdown ---- */}
                <div className="relative z-10 flex flex-wrap items-center justify-between gap-3 border-b border-border bg-card p-4">
                    <form
                        onSubmit={handleSearchSubmit}
                        className="flex w-full max-w-md flex-1 items-center gap-2"
                    >
                        <div className="relative flex-1">
                            <input
                                type="text"
                                value={effectiveSearchValue}
                                onChange={(e) =>
                                    handleSearchInputChange(e.target.value)
                                }
                                placeholder={searchPlaceholder}
                                className="h-10 w-full rounded-xl border border-input bg-background px-3 text-sm text-foreground outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-ring"
                            />
                        </div>
                        <button
                            type="submit"
                            aria-label="Search"
                            title="Search"
                            className="flex h-10 w-10 shrink-0 cursor-pointer items-center justify-center rounded-xl bg-blue-600 text-white transition-colors hover:bg-blue-400"
                        >
                            <Search className="h-4 w-4" />
                        </button>
                    </form>
                    <div className="flex items-center gap-2">
                        {filters}
                        {sortOptions && sortOptions.length > 0 && (
                            <Popover>
                                <PopoverTrigger>
                                    <span
                                        role="button"
                                        tabIndex={0}
                                        className={`inline-flex h-10 w-10 cursor-pointer items-center justify-center rounded-xl border text-muted-foreground transition-colors hover:bg-muted ${
                                            activeSortValue
                                                ? 'border-primary/50 bg-primary/10 text-primary'
                                                : 'border-input bg-background'
                                        }`}
                                        aria-label="Sort"
                                        title="Sort"
                                    >
                                        <ArrowUpDown className="h-4 w-4" />
                                    </span>
                                </PopoverTrigger>
                                <PopoverContent
                                    align="end"
                                    className="w-52 space-y-1 border-border bg-popover p-2 text-popover-foreground"
                                >
                                    <p className="px-2 py-1.5 text-xs font-semibold text-muted-foreground uppercase">
                                        Sort by
                                    </p>
                                    {sortOptions.map((opt) => {
                                        const optionValue = `${opt.sort}:${opt.direction}`;
                                        const isActive =
                                            optionValue === activeSortValue;

                                        return (
                                            <button
                                                key={optionValue}
                                                type="button"
                                                onClick={() =>
                                                    handleSortSelectChange(
                                                        optionValue,
                                                    )
                                                }
                                                className={`flex w-full cursor-pointer items-center justify-between rounded-lg px-2 py-2 text-left text-sm transition-colors ${
                                                    isActive
                                                        ? 'bg-primary/10 font-medium text-primary'
                                                        : 'text-foreground hover:bg-muted'
                                                }`}
                                            >
                                                {opt.label}
                                                {isActive && (
                                                    <Check className="h-3.5 w-3.5" />
                                                )}
                                            </button>
                                        );
                                    })}
                                </PopoverContent>
                            </Popover>
                        )}
                        <button
                            type="button"
                            onClick={handleReloadAndReset}
                            disabled={isResetting}
                            className="inline-flex h-10 w-10 cursor-pointer items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                            aria-label="Reload and reset table"
                            title="Reload and reset table"
                        >
                            <RefreshCw
                                className={`h-4 w-4 ${isResetting ? 'animate-spin' : ''}`}
                            />
                        </button>
                    </div>
                </div>

                <CardContent className="overflow-x-auto p-0">
                    <Table
                        className={`min-w-200 table-fixed transition-opacity duration-150 ${
                            isNavigating ? 'opacity-60' : 'opacity-100'
                        }`}
                    >
                        <colgroup>
                            {columns.map((col, i) => (
                                <col
                                    key={i}
                                    style={
                                        col.width
                                            ? { width: col.width }
                                            : undefined
                                    }
                                />
                            ))}
                            <col style={{ width: actionsWidth }} />
                        </colgroup>
                        <TableHeader>
                            <TableRow className="border-b border-border hover:bg-transparent">
                                {columns.map((col, i) => {
                                    const sortKey =
                                        typeof col.sortable === 'string'
                                            ? col.sortable
                                            : null;
                                    const isActiveSort =
                                        sortKey !== null &&
                                        currentSort === sortKey;

                                    const headerContent = (
                                        <span className="inline-flex items-center gap-1.5">
                                            {col.header}
                                            {sortKey ? (
                                                isActiveSort ? (
                                                    currentDirection ===
                                                    'asc' ? (
                                                        <ArrowUp className="h-3 w-3 text-primary" />
                                                    ) : (
                                                        <ArrowDown className="h-3 w-3 text-primary" />
                                                    )
                                                ) : (
                                                    <ArrowUpDown className="h-3 w-3 text-muted-foreground" />
                                                )
                                            ) : (
                                                col.sortable && (
                                                    <ArrowUpDown className="h-3 w-3 text-muted-foreground" />
                                                )
                                            )}
                                        </span>
                                    );

                                    return (
                                        <TableHead
                                            key={i}
                                            className={`h-11 truncate bg-muted/50 text-xs font-semibold tracking-wide text-muted-foreground uppercase ${
                                                i === 0 ? 'pl-6' : ''
                                            } ${sortKey ? 'cursor-pointer select-none hover:text-foreground' : ''}`}
                                            onClick={
                                                sortKey
                                                    ? () =>
                                                          handleSortClick(
                                                              sortKey,
                                                          )
                                                    : undefined
                                            }
                                        >
                                            {headerContent}
                                        </TableHead>
                                    );
                                })}
                                <TableHead className="h-11 bg-muted/50 pr-14 text-right text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {displayData.length > 0 ? (
                                displayData.map((row, i) => {
                                    const isHighlighted = highlightedIds.has(
                                        row.id,
                                    );

                                    return (
                                        <TableRow
                                            key={row.id}
                                            className={`border-b border-border/50 transition-colors duration-700 last:border-0 hover:bg-muted/40 ${
                                                isHighlighted
                                                    ? 'animate-[row-flash_2s_ease-out] bg-primary/10'
                                                    : i % 2 === 1
                                                      ? 'bg-muted/20'
                                                      : 'bg-card'
                                            }`}
                                        >
                                            {columns.map((col, j) => (
                                                <TableCell
                                                    key={j}
                                                    className={`py-3 text-sm ${col.className ?? 'truncate text-foreground'} ${
                                                        j === 0 ? 'pl-6' : ''
                                                    }`}
                                                >
                                                    {col.render(row, i)}
                                                </TableCell>
                                            ))}
                                            <TableCell className="py-3 pr-10 text-right">
                                                <div className="mr-2 inline-flex overflow-hidden rounded-full shadow-sm">
                                                    <Link
                                                        href={editHref(row)}
                                                        className="flex h-8 w-8 cursor-pointer items-center justify-center bg-amber-400 text-white transition-colors hover:bg-amber-500"
                                                        aria-label="Edit"
                                                        title="Edit"
                                                    >
                                                        <Pencil className="h-4 w-4" />
                                                    </Link>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            handleDeleteClick(
                                                                row.id,
                                                            )
                                                        }
                                                        className="flex h-8 w-8 items-center justify-center border-l border-white/20 bg-red-600 text-white transition-colors hover:bg-red-700 cursor-pointer"
                                                        aria-label="Delete"
                                                        title="Delete"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })
                            ) : (
                                <TableRow className="hover:bg-transparent">
                                    <TableCell
                                        colSpan={columns.length + 1}
                                        className="h-56"
                                    >
                                        <div className="flex flex-col items-center justify-center gap-2 text-muted-foreground">
                                            <EmptyIcon
                                                className="h-8 w-8"
                                                strokeWidth={1.5}
                                            />
                                            <p className="text-sm font-medium text-muted-foreground">
                                                {emptyMessage}
                                            </p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </CardContent>

                {resource.data.length > 0 && (
                    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-3">
                        <p className="shrink-0 text-xs text-muted-foreground sm:text-sm">
                            Showing {resource.from ?? 0}-{resource.to ?? 0} of{' '}
                            {resource.total} results
                        </p>

                        {resource.last_page > 5 ? (
                            // ---- Page-jump dropdown (>3 pages) ----
                            // Copied from RequestTable: Prev / a popover with a
                            // type-to-jump input plus a scrollable page list /
                            // Next. Picking a page does a plain full reload —
                            // no preserveState, no `only`.
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
                                         <button
                                             type="button"
                                             disabled={!prevLink?.url}
                                             onClick={() =>
                                                 navigateToPage(
                                                     resource.current_page - 1,
                                                 )
                                             }
                                             aria-label="Previous page"
                                             className="inline-flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-md border border-slate-200 text-sm text-slate-600 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                                         >
                                            <ChevronLeft className="h-4 w-4" />
                                        </button>

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
                                                className="w-48 space-y-2 border-border bg-popover p-2 text-popover-foreground"
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
                                                        className="h-8 w-full min-w-0 rounded-md border border-input bg-background px-2 text-sm text-foreground outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-ring"
                                                        autoFocus
                                                    />
                                                    <button
                                                        type="submit"
                                                        className="h-8 shrink-0 cursor-pointer rounded-md bg-blue-900 px-2.5 text-xs font-semibold text-white transition-colors hover:bg-blue-950"
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
                                                             className={`flex w-full cursor-pointer items-center rounded-md px-2 py-1.5 text-left text-sm transition-colors ${
                                                                page ===
                                                                resource.current_page
                                                                    ? 'bg-primary/10 font-medium text-primary'
                                                                    : 'text-foreground hover:bg-muted'
                                                            }`}
                                                        >
                                                            Page {page}
                                                        </button>
                                                    ))}
                                                </div>
                                            </PopoverContent>
                                        </Popover>

                                         <button
                                             type="button"
                                             disabled={!nextLink?.url}
                                             onClick={() =>
                                                 navigateToPage(
                                                     resource.current_page + 1,
                                                 )
                                             }
                                             aria-label="Next page"
                                             className="inline-flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-md border border-slate-200 text-sm text-slate-600 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                                         >
                                            <ChevronRight className="h-4 w-4" />
                                        </button>
                                    </div>
                                );
                            })()
                        ) : (
                            // ---- Standard button row (3 pages or fewer, unchanged) ----
                            <div className="flex items-center gap-1.5">
                                {resource.links.map((link, index) => {
                                    const rawLabel = link.label
                                        .replace(/&laquo;|&raquo;/g, '')
                                        .trim();
                                    const isPrev =
                                        rawLabel.toLowerCase() === 'previous';
                                    const isNext =
                                        rawLabel.toLowerCase() === 'next';

                                    return (
                                        <Link
                                            key={index}
                                            href={link.url ?? '#'}
                                            preserveScroll
                                            aria-label={
                                                isPrev
                                                    ? 'Previous page'
                                                    : isNext
                                                      ? 'Next page'
                                                      : rawLabel
                                            }
                                            as={link.url ? 'a' : 'span'}
                                            className={`inline-flex h-8 w-8 items-center justify-center rounded-lg border text-sm font-medium transition-colors ${
                                                link.active
                                                    ? 'border-primary bg-primary text-primary-foreground hover:bg-primary/90'
                                                    : link.url
                                                      ? 'border-input bg-background text-foreground hover:bg-muted'
                                                      : 'cursor-not-allowed border-border text-muted-foreground/40'
                                            } ${link.active ? 'rounded-full' : ''}`}
                                        >
                                            {isPrev ? (
                                                <ChevronLeft className="h-4 w-4" />
                                            ) : isNext ? (
                                                <ChevronRight className="h-4 w-4" />
                                            ) : (
                                                rawLabel
                                            )}
                                        </Link>
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
