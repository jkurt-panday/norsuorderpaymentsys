import React, { useEffect, useRef, useState } from 'react';
import { Head, Link, router, usePoll } from '@inertiajs/react';
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
    type LucideIcon,
} from 'lucide-react';
import { toast } from 'sonner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Card, CardContent } from '@/components/ui/card';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useConfirm } from '@/components/confirm-dialog';

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
    /**
     * Laravel's paginator already includes these in its JSON output — just
     * declaring them here so the frontend can use them (e.g. to build a
     * page-jump dropdown once there are too many pages for a button row).
     */
    current_page: number;
    last_page: number;
}

export interface ColumnDef<T> {
    /** Column header label */
    header: string;
    /**
     * Renders the cell content for a given row.
     * `index` is the row's position within the CURRENT PAGE (0-based) —
     * use it with `resource.from` to build a sequential display number,
     * e.g. render: (row, index) => (resource.from ?? 1) + index
     */
    render: (row: T, index: number) => React.ReactNode;
    /** Optional extra classes for the <td> */
    className?: string;
    /**
     * Fixed column width (e.g. '20%', '160px'). Without this, a long text
     * column (like a description) will greedily eat all remaining space and
     * push everything after it — including Actions — far out to the right
     * with a big dead gap in between. Give your widest text column a
     * reasonable cap (e.g. '40%') to prevent that.
     */
    width?: string;
    /**
     * Enables sorting on this column.
     * - Pass a string (the backend sort key, e.g. 'member_code') to make the
     *   header clickable: clicking toggles asc/desc and submits `?sort=` +
     *   `?direction=` via a live router.get, same mechanism as search.
     * - Pass `true` for the old decorative-only behavior (arrow icon shown,
     *   header not clickable, no request sent) — kept for backward compat.
     */
    sortable?: boolean | string;
}

/**
 * A single preset entry in the sort dropdown (e.g. "Newest first").
 * Submits `?sort=` + `?direction=` via the same navigateWithParams
 * mechanism used by clickable column headers, so both stay in sync.
 */
export interface SortOptionDef {
    /** Label shown in the dropdown, e.g. 'Newest' */
    label: string;
    /** Backend sort key, e.g. 'created_at'. Must be in the controller's sortableColumns allowlist. */
    sort: string;
    /** Sort direction for this option */
    direction: 'asc' | 'desc';
}

export interface ResourceTableProps<T extends { id: number }> {
    /** Page <title> and heading */
    title: string;
    /**
     * Optional one-line subtitle under the title, e.g.
     * "Manage and maintain UACS accounts used for financial transactions."
     */
    description?: string;
    /**
     * Optional icon shown in a small rounded circle to the left of the title,
     * matching the design reference's header block. Omit to render the
     * original plain-text header.
     */
    icon?: LucideIcon;
    /** Label for the "add new" button */
    addLabel: string;
    /** href for the "add new" button (from a Wayfinder route function, e.g. create()) */
    addHref: string | { url: string; method?: string };
    /** Column definitions, in display order */
    columns: ColumnDef<T>[];
    /** Paginated resource data (Laravel's ->paginate() shape) */
    resource: PaginatedData<T>;
    /** The Inertia prop key that `resource` is bound to on this page (e.g. 'requests').
     *  Required when `pollInterval` is set, so polling can reload *only* this prop
     *  instead of the whole page payload. */
    resourceKey?: string;
    /** Builds the edit href for a given row (e.g. (row) => edit(row.id)) */
    editHref: (row: T) => string | { url: string; method?: string };
    /** Builds the delete URL for a given row id (e.g. (id) => destroy(id).url) */
    deleteUrl: (id: number) => string;
    /** Icon shown in the empty state */
    emptyIcon: LucideIcon;
    /** Message shown in the empty state */
    emptyMessage: string;
    /** Text shown in the delete confirmation body (defaults to a generic message) */
    deleteConfirmMessage?: string;
    /** Fixed width for the Actions column. Defaults to '96px' (two pill buttons). */
    actionsWidth?: string;
    /**
     * Optional content rendered inside the toolbar row, to the right of the
     * built-in search input (e.g. an extra select/filter control). Omit for
     * just search + sort + the decorative list-view button.
     */
    filters?: React.ReactNode;
    /**
     * Enables live polling for this table. Pass an interval in ms (e.g. 5000).
     * Omit entirely to leave the table static (no polling, no behavior change).
     * Requires `resourceKey` to be set.
     */
    pollInterval?: number;
    /**
     * How long the highlight animation stays on a new/changed row, in ms.
     * Defaults to 2000.
     */
    highlightDuration?: number;
    /**
     * Placeholder text for the built-in search box, e.g.
     * "Search by object code or account title...".
     */
    searchPlaceholder?: string;
    /**
     * Optional controlled search value + handler, for pages that want to
     * manage search state themselves (e.g. combining it with other filters,
     * the way RequestTable's parent pages do). When provided, pass
     * `onSearchSubmit` too — ResourceTable calls it when the person presses
     * Enter or clicks the search button, and defers entirely to the parent
     * otherwise.
     *
     * When omitted (the common case), ResourceTable manages the search box
     * on its own: typing only updates local state, and pressing Enter or
     * clicking the search button issues a `router.get` request with
     * `?search=...` added to the current URL, scoped to `resourceKey` via
     * Inertia's partial reload. Submit-based (not live-as-you-type) is
     * deliberate — a fresh search always clears `page` since the old page
     * number may not exist in the new results, so live search would bounce
     * someone back to page 1 mid-keystroke. The matching Laravel controller
     * just needs to read `$request->query('search')` and apply it as a
     * `where(...)->orWhere(...)` filter before `paginate()`.
     */
    searchValue?: string;
    onSearchChange?: (value: string) => void;
    /** Called when the search is actually submitted (button click or Enter). Only relevant in controlled mode. */
    onSearchSubmit?: (value: string) => void;
    /**
     * Preset sort options shown in a dropdown (e.g. Newest, Oldest, A–Z, Z–A).
     * Each option submits `?sort=` + `?direction=` via the same live
     * navigateWithParams mechanism used by clickable column headers, so
     * picking a dropdown option and clicking a header both stay in sync.
     * Omit entirely to hide the sort dropdown.
     *
     * The `sort` key for each option must be present in the matching
     * controller's `sortableColumns` allowlist (BaseResourceController),
     * or the backend will silently ignore it.
     */
    sortOptions?: SortOptionDef[];
}

// Shallow content signature for a row, used to detect "changed" (not just "new").
// Falls back gracefully for any row shape — doesn't need to know the row's fields.
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
    actionsWidth = '96px',
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

    // ---- Optimistic client-side sort -------------------------------------
    // The table is server-paginated, so a true "sort everything" always
    // needs a round trip (other pages' rows aren't loaded in the browser).
    // But we DO already have the current page's rows sitting in memory, so
    // the moment someone clicks a sort, we immediately re-order just those
    // visible rows in JS — no waiting to see anything move — and then
    // quietly swap in the authoritative server result once it arrives
    // (which will normally match, since it's the same rows re-sorted).
    const [displayData, setDisplayData] = useState<T[]>(resource.data);

    useEffect(() => {
        setDisplayData(resource.data);
    }, [resource.data]);

    function compareValues(a: unknown, b: unknown): number {
        if (a === b) return 0;
        if (a === null || a === undefined) return -1;
        if (b === null || b === undefined) return 1;

        // Numeric compare when both sides parse cleanly as numbers.
        const aNum = typeof a === 'number' ? a : Number(a);
        const bNum = typeof b === 'number' ? b : Number(b);
        if (!Number.isNaN(aNum) && !Number.isNaN(bNum)) {
            return aNum - bNum;
        }

        // Date compare (handles ISO timestamp strings like created_at).
        const aDate = Date.parse(String(a));
        const bDate = Date.parse(String(b));
        if (!Number.isNaN(aDate) && !Number.isNaN(bDate)) {
            return aDate - bDate;
        }

        // Fall back to locale-aware string compare.
        return String(a).localeCompare(String(b));
    }

    function optimisticallySort(sortKey: string, direction: 'asc' | 'desc') {
        setDisplayData((current) => {
            const sorted = [...current].sort((rowA, rowB) => {
                const valueA = (rowA as Record<string, unknown>)[sortKey];
                const valueB = (rowB as Record<string, unknown>)[sortKey];
                const result = compareValues(valueA, valueB);
                return direction === 'asc' ? result : -result;
            });
            return sorted;
        });
    }

    // ---- Live update detection -------------------------------------------
    // Tracks id -> content signature from the last render, so we can tell
    // which rows are brand new vs. changed vs. untouched after a poll.
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

        // Clean up pending timers on unmount.
        return () => {
            highlightTimers.current.forEach((t) => clearTimeout(t));
            highlightTimers.current.clear();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [resource.data]);

    // ---- Polling -------------------------------------------------------
    // `only` scopes the reload to this resource's prop so polling stays cheap —
    // without it, every tick would re-fetch the entire page's props.
    const { start, stop } = usePoll(
        pollInterval ?? 15000,
        {
            only: resourceKey ? [resourceKey] : undefined,
            preserveScroll: true,
            preserveState: true,
        },
        { autoStart: false },
    );

    useEffect(() => {
        if (!pollInterval) return;

        if (process.env.NODE_ENV !== 'production' && !resourceKey) {
            console.warn(
                "[ResourceTable] pollInterval is set without resourceKey — polling will reload the entire page payload instead of just this table's data.",
            );
        }

        start();
        return () => stop();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [pollInterval, resourceKey]);

    // ---- Delete flow (pauses polling while the confirm dialog is open) -----
    const handleDeleteClick = async (id: number) => {
        if (pollInterval) stop();

        const ok = await confirm({
            title: 'Confirm Delete',
            description: deleteConfirmMessage,
            confirmLabel: 'Delete',
            variant: 'destructive',
        });

        if (!ok) {
            if (pollInterval) start();
            return;
        }

        router.delete(deleteUrl(id), {
            preserveScroll: true,
            // No toast.success here on purpose. A redirect back to this page is
            // "successful" from Inertia's point of view even when the backend
            // rejected the delete (e.g. return back()->with('error', '...') for
            // a record still in use) — it's still just a normal 302 response.
            // The actual outcome (deleted vs. still-in-use) should come from the
            // backend's flash message, surfaced by a flash-listening useEffect
            // on the page that renders this table.
            onError: () => {
                toast.error('Something went wrong while deleting.');
            },
            onFinish: () => {
                if (pollInterval) start();
            },
        });
    };

    // ---- Unified query navigation -----------------------------------
    // Single helper that reads the current URL, applies param overrides,
    // resets to page 1 (since any new search/sort invalidates the
    // old page number), and issues a scoped Inertia reload. Search and sort
    // both funnel through this so they compose correctly instead of
    // clobbering each other.
    const [isNavigating, setIsNavigating] = useState(false);
    const [pageJumpOpen, setPageJumpOpen] = useState(false);
    const [pageJumpInput, setPageJumpInput] = useState('');

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
                onFinish: () => setIsNavigating(false),
            },
        );
    };

    // Jumps straight to an arbitrary page number — used by the page-jump
    // dropdown once there are too many pages for a button row to make
    // sense. Unlike navigateWithParams (which always clears `page`, since
    // any new sort/search invalidates the old page number), this is the
    // one place that's SUPPOSED to set `page` — everything else in the
    // current URL (search, sort, direction, etc.) is left untouched.
    //
    // Deliberately a STANDARD full visit here — no preserveState, no
    // `only`. Those partial-reload optimizations are what caused the
    // "click page 2, land back on page 1" bug: this now behaves exactly
    // like the plain page-number <Link> buttons below, which never had
    // that problem.
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
                onStart: () => setIsNavigating(true),
                onFinish: () => setIsNavigating(false),
            },
        );
    };

    // ---- Built-in search (only when the parent isn't already
    // controlling search itself via searchValue/onSearchChange) --------
    // Submits on Enter / a Search button click — NOT live as you type.
    // The earlier live-debounced version fired navigateWithParams() on
    // every keystroke, and since a fresh search always clears `page`
    // (correctly — the old page number may not even exist in the new
    // results), that meant typing a single character while on page 2+
    // instantly bounced back to page 1. Submit-based search fixes that:
    // typing only ever updates local state, nothing navigates until the
    // person actually confirms the search.
    const isControlledSearch = onSearchChange !== undefined;

    const [internalSearch, setInternalSearch] = useState<string>(() => {
        if (typeof window === 'undefined') return '';
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

    // ---- Sorting ------------------------------------------------------
    // Reads the current sort column/direction straight from the URL so the
    // header arrows AND the sort dropdown reflect state after a page
    // reload/poll, not just local component state.
    const urlParams =
        typeof window !== 'undefined'
            ? new URLSearchParams(window.location.search)
            : new URLSearchParams();
    const currentSort = urlParams.get('sort') ?? '';
    const currentDirection =
        (urlParams.get('direction') as 'asc' | 'desc') || 'asc';

    const handleSortClick = (sortKey: string) => {
        const isActiveColumn = currentSort === sortKey;

        // Cycle: unsorted -> asc -> desc -> unsorted (back to neutral arrows).
        if (!isActiveColumn) {
            optimisticallySort(sortKey, 'asc');
            navigateWithParams({ sort: sortKey, direction: 'asc' });
        } else if (currentDirection === 'asc') {
            optimisticallySort(sortKey, 'desc');
            navigateWithParams({ sort: sortKey, direction: 'desc' });
        } else {
            // Going back to unsorted has no single "correct" client-side
            // order to preview (it's whatever the backend's default order
            // is), so just fire the request as before.
            navigateWithParams({ sort: undefined, direction: undefined });
        }
    };

    // Value driving the sort menu. Encodes sort+direction into a single
    // string ("created_at:desc") for easy comparison against each option.
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

    return (
        // Greyish page background so the white title bar / filter card /
        // table card actually stand out, matching the Requests page.
        <div className="mx-auto min-h-screen w-full max-w-7xl min-w-0 space-y-4 bg-slate-50 p-3 sm:p-6">
            <Head title={title} />

            {/* ---- Header block: icon circle + title + description + Add button ---- */}
            <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200/70 bg-white p-5 shadow-sm">
                <div className="flex items-center gap-4">
                    {HeaderIcon && (
                        <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-blue-100">
                            <HeaderIcon className="h-5 w-5 text-blue-600" />
                        </div>
                    )}
                    <div>
                        <h2 className="text-xl font-bold text-slate-900">
                            {title}
                        </h2>
                        {description && (
                            <p className="text-sm text-slate-500">
                                {description}
                            </p>
                        )}
                    </div>
                </div>
                <Link
                    href={addHref}
                    className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700"
                >
                    <Plus className="h-4 w-4" />
                    {addLabel}
                </Link>
            </div>

            <Card className="w-full overflow-hidden rounded-2xl border-slate-200/70 py-0 shadow-sm">
                {/* Subtle local loading indicator — scoped to this table only,
                    replaces Inertia's global top progress bar (which we
                    suppress via showProgress: false) so clicking sort/search
                    still gives instant visual feedback without a jarring
                    full-page line. */}
                <div className="relative h-0.5 w-full overflow-hidden bg-transparent">
                    {isNavigating && (
                        <div className="absolute inset-0 animate-[table-loading-bar_1s_ease-in-out_infinite] bg-blue-500" />
                    )}
                </div>
                <style>{`
                    @keyframes table-loading-bar {
                        0% { transform: translateX(-100%); }
                        100% { transform: translateX(100%); }
                    }
                `}</style>
                {/* ---- Toolbar row: search + optional extra filters + sort dropdown + list-view button ---- */}
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-4">
                    <div className="relative w-full max-w-md flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            type="text"
                            value={effectiveSearchValue}
                            onChange={(e) =>
                                handleSearchInputChange(e.target.value)
                            }
                            placeholder={searchPlaceholder}
                            className="h-10 w-full rounded-xl border border-slate-200 bg-white pr-3 pl-9 text-sm text-slate-700 outline-none placeholder:text-slate-400 focus:ring-2 focus:ring-blue-200"
                        />
                    </div>
                    <div className="flex items-center gap-2">
                        {filters}
                        {sortOptions && sortOptions.length > 0 && (
                            <Popover>
                                <PopoverTrigger asChild>
                                    <button
                                        type="button"
                                        className={`inline-flex h-10 w-10 items-center justify-center rounded-xl border text-slate-500 transition-colors hover:bg-slate-50 ${
                                            activeSortValue
                                                ? 'border-blue-300 bg-blue-50 text-blue-600'
                                                : 'border-slate-200 bg-white'
                                        }`}
                                        aria-label="Sort"
                                        title="Sort"
                                    >
                                        <ArrowUpDown className="h-4 w-4" />
                                    </button>
                                </PopoverTrigger>
                                <PopoverContent
                                    align="end"
                                    className="w-52 space-y-1 p-2"
                                >
                                    <p className="px-2 py-1.5 text-xs font-semibold text-slate-500 uppercase">
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
                                                className={`flex w-full items-center justify-between rounded-lg px-2 py-2 text-left text-sm transition-colors ${
                                                    isActive
                                                        ? 'bg-blue-50 font-medium text-blue-700'
                                                        : 'text-slate-600 hover:bg-slate-50'
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
                    </div>
                </div>

                <CardContent className="overflow-x-auto p-0">
                    <Table
                        className={`min-w-[640px] table-fixed transition-opacity duration-150 ${
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
                            <TableRow className="border-b border-slate-200 hover:bg-transparent">
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
                                                        <ArrowUp className="h-3 w-3 text-blue-600" />
                                                    ) : (
                                                        <ArrowDown className="h-3 w-3 text-blue-600" />
                                                    )
                                                ) : (
                                                    <ArrowUpDown className="h-3 w-3 text-slate-400" />
                                                )
                                            ) : (
                                                col.sortable && (
                                                    <ArrowUpDown className="h-3 w-3 text-slate-400" />
                                                )
                                            )}
                                        </span>
                                    );

                                    return (
                                        <TableHead
                                            key={i}
                                            className={`h-11 truncate bg-slate-50/80 text-xs font-semibold tracking-wide text-slate-600 uppercase ${
                                                i === 0 ? 'pl-6' : ''
                                            } ${sortKey ? 'cursor-pointer select-none hover:text-slate-900' : ''}`}
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
                                <TableHead className="h-11 bg-white pr-6 text-right text-xs font-semibold tracking-wide text-slate-500 uppercase">
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
                                            className={`border-b border-slate-100 transition-colors duration-700 last:border-0 hover:bg-blue-50/40 ${
                                                isHighlighted
                                                    ? 'animate-[row-flash_2s_ease-out] bg-blue-50'
                                                    : i % 2 === 1
                                                      ? 'bg-slate-50/30'
                                                      : ''
                                            }`}
                                        >
                                            {columns.map((col, j) => (
                                                <TableCell
                                                    key={j}
                                                    className={`py-3 text-sm ${col.className ?? 'truncate text-slate-700'} ${
                                                        j === 0 ? 'pl-6' : ''
                                                    }`}
                                                >
                                                    {col.render(row, i)}
                                                </TableCell>
                                            ))}
                                            <TableCell className="py-3 pr-6 text-right">
                                                {/* Connected pill action group — matches the
                                                    Requests page's View/Process/Edit style
                                                    instead of two separate floating squares. */}
                                                <div className="inline-flex overflow-hidden rounded-full shadow-sm">
                                                    <Link
                                                        href={editHref(row)}
                                                        className="flex h-8 w-8 items-center justify-center bg-amber-400 text-white transition-colors hover:bg-amber-500"
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
                                                        className="flex h-8 w-8 items-center justify-center border-l border-white/20 bg-red-600 text-white transition-colors hover:bg-red-700"
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
                                        <div className="flex flex-col items-center justify-center gap-2 text-slate-400">
                                            <EmptyIcon
                                                className="h-8 w-8"
                                                strokeWidth={1.5}
                                            />
                                            <p className="text-sm font-medium text-slate-500">
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
                    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-3">
                        <p className="shrink-0 text-sm text-slate-500">
                            Showing {resource.from ?? 0} to {resource.to ?? 0}{' '}
                            of {resource.total} entries
                        </p>

                        {resource.last_page > 10 ? (
                            // ---- Page-jump dropdown ----
                            // Once there are more than 10 pages, a button row
                            // (even Laravel's elided "1 2 3 ... 8 9 10" version)
                            // stops being a great way to navigate — so instead,
                            // show Prev / a dropdown listing every page number /
                            // Next. Picking a page jumps straight there while
                            // keeping any active search/sort/filter params intact.
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
                                            className={`inline-flex h-8 w-8 items-center justify-center rounded-lg border text-sm font-medium transition-colors ${
                                                prevLink?.url
                                                    ? 'border-slate-200 text-slate-600 hover:bg-slate-50'
                                                    : 'cursor-not-allowed border-slate-100 text-slate-300'
                                            }`}
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
                                            <PopoverTrigger asChild>
                                                <button
                                                    type="button"
                                                    className="inline-flex h-8 items-center justify-center gap-1 rounded-lg border border-slate-200 px-3 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50"
                                                >
                                                    Page {resource.current_page}{' '}
                                                    of {resource.last_page}
                                                </button>
                                            </PopoverTrigger>
                                            <PopoverContent
                                                align="center"
                                                className="w-48 space-y-2 p-2"
                                            >
                                                {/* Type-to-jump — the actual
                                                    "find it if the list gets
                                                    long" input, since a plain
                                                    scrollable list alone gets
                                                    tedious past a handful of
                                                    pages. */}
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
                                                        className="h-8 shrink-0 rounded-md bg-blue-600 px-2.5 text-xs font-semibold text-white transition-colors hover:bg-blue-700"
                                                    >
                                                        Go
                                                    </button>
                                                </form>

                                                {/* Scrollable browse list —
                                                    stays available for
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
                                                                    ? 'bg-blue-50 font-medium text-blue-700'
                                                                    : 'text-slate-600 hover:bg-slate-50'
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
                                            className={`inline-flex h-8 w-8 items-center justify-center rounded-lg border text-sm font-medium transition-colors ${
                                                nextLink?.url
                                                    ? 'border-slate-200 text-slate-600 hover:bg-slate-50'
                                                    : 'cursor-not-allowed border-slate-100 text-slate-300'
                                            }`}
                                        >
                                            <ChevronRight className="h-4 w-4" />
                                        </button>
                                    </div>
                                );
                            })()
                        ) : (
                            // ---- Standard button row (10 pages or fewer) ----
                            <div className="flex max-w-full flex-nowrap items-center gap-1.5 overflow-x-auto">
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
                                            className={`inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border text-sm font-medium transition-colors ${
                                                link.active
                                                    ? 'border-blue-600 bg-blue-600 text-white hover:bg-blue-700'
                                                    : link.url
                                                      ? 'border-slate-200 text-slate-600 hover:bg-slate-50'
                                                      : 'cursor-not-allowed border-slate-100 text-slate-300'
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
