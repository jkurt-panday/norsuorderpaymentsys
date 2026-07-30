import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Pencil, Trash2, ChevronLeft, ChevronRight, type LucideIcon } from 'lucide-react';
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
}

export interface ResourceTableProps<T extends { id: number }> {
    /** Page <title> and heading */
    title: string;
    /** Label for the "add new" button */
    addLabel: string;
    /** href for the "add new" button (from a Wayfinder route function, e.g. create()) */
    addHref: string | { url: string; method?: string };
    /** Column definitions, in display order */
    columns: ColumnDef<T>[];
    /** Paginated resource data (Laravel's ->paginate() shape) */
    resource: PaginatedData<T>;
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
     * Optional content rendered between the header row and the table card —
     * e.g. a search bar, once this resource has enough rows to need one.
     */
    filters?: React.ReactNode;
}

// ============ COMPONENT ============
export default function ResourceTable<T extends { id: number }>({
    title,
    addLabel,
    addHref,
    columns,
    resource,
    editHref,
    deleteUrl,
    emptyIcon: EmptyIcon,
    emptyMessage,
    deleteConfirmMessage = 'Are you sure you want to delete this record?',
    actionsWidth = '96px',
    filters,
}: ResourceTableProps<T>) {
    const confirm = useConfirm();

    const handleDeleteClick = async (id: number) => {
        const ok = await confirm({
            title: 'Confirm Delete',
            description: deleteConfirmMessage,
            confirmLabel: 'Delete',
            variant: 'destructive',
        });

        if (!ok) return;

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
        });
    };

    return (
        // Greyish page background so the white title bar / filter card /
        // table card actually stand out, matching the Requests page.
        <div className="min-h-screen bg-slate-50 mx-auto min-w-0 w-full max-w-7xl space-y-4 p-3 sm:p-6">
            <Head title={title} />

            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-3">
                    <h2 className="text-lg font-semibold text-slate-900">{title}</h2>
                    <span className="text-sm text-slate-500">
                        {resource.total.toLocaleString()} total
                    </span>
                </div>
                <Link
                    href={addHref}
                    className="inline-flex items-center gap-2 rounded-md bg-blue-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-blue-950"
                >
                    <Plus className="h-4 w-4" />
                    {addLabel}
                </Link>
            </div>

            {filters}

            <Card className="w-full overflow-hidden py-0 border-slate-200/70 shadow-sm">
                <CardContent className="overflow-x-auto p-0">
                    <Table className="min-w-[640px] table-fixed">
                        <colgroup>
                            {columns.map((col, i) => (
                                <col key={i} style={col.width ? { width: col.width } : undefined} />
                            ))}
                            <col style={{ width: actionsWidth }} />
                        </colgroup>
                        <TableHeader>
                            <TableRow className="border-b border-slate-200 hover:bg-transparent">
                                {columns.map((col, i) => (
                                    <TableHead
                                        key={i}
                                        className={`h-11 truncate bg-slate-50/80 text-xs font-semibold uppercase tracking-wide text-slate-600 ${
                                            i === 0 ? 'pl-6' : ''
                                        }`}
                                    >
                                        {col.header}
                                    </TableHead>
                                ))}
                                <TableHead className="h-11 bg-white pr-6 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {resource.data.length > 0 ? (
                                resource.data.map((row, i) => (
                                    <TableRow
                                        key={row.id}
                                        className={`border-b border-slate-100 transition-colors last:border-0 hover:bg-blue-50/40 ${
                                            i % 2 === 1 ? 'bg-slate-50/30' : ''
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
                                                onClick={() => handleDeleteClick(row.id)}
                                                className="flex h-8 w-8 items-center justify-center border-l border-white/20 bg-red-600 text-white transition-colors hover:bg-red-700"
                                                aria-label="Delete"
                                                title="Delete"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </div>
                                        </TableCell>
                                    </TableRow>
                                ))
                            ) : (
                                <TableRow className="hover:bg-transparent">
                                    <TableCell colSpan={columns.length + 1} className="h-56">
                                        <div className="flex flex-col items-center justify-center gap-2 text-slate-400">
                                            <EmptyIcon className="h-8 w-8" strokeWidth={1.5} />
                                            <p className="text-sm font-medium text-slate-500">{emptyMessage}</p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </CardContent>

                {resource.data.length > 0 && (
                    <div className="flex items-center justify-between border-t border-slate-200 px-5 py-3">
                        <p className="text-sm text-slate-500">
                            Showing {resource.from ?? 0} to {resource.to ?? 0} of {resource.total} results
                        </p>
                        <div className="flex items-center gap-1">
                            {resource.links.map((link, index) => {
                                const rawLabel = link.label.replace(/&laquo;|&raquo;/g, '').trim();
                                const isPrev = rawLabel.toLowerCase() === 'previous';
                                const isNext = rawLabel.toLowerCase() === 'next';

                                return (
                                    <Link
                                        key={index}
                                        href={link.url ?? '#'}
                                        preserveScroll
                                        aria-label={isPrev ? 'Previous page' : isNext ? 'Next page' : rawLabel}
                                        as={link.url ? 'a' : 'span'}
                                        className={`inline-flex h-8 w-8 items-center justify-center rounded-md text-sm font-medium transition-colors ${
                                            link.active
                                                ? 'bg-blue-900 text-white hover:bg-blue-950'
                                                : link.url
                                                ? 'text-slate-600 hover:bg-slate-100'
                                                : 'cursor-not-allowed text-slate-300'
                                        }`}
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
                    </div>
                )}
            </Card>
        </div>
    );
}