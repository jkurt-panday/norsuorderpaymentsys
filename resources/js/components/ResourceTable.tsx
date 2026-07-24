import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Pencil, Trash2, X, ChevronLeft, ChevronRight, type LucideIcon } from 'lucide-react';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Card, CardContent } from '@/components/ui/card';

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
    /** Renders the cell content for a given row */
    render: (row: T) => React.ReactNode;
    /** Optional extra classes for the <td> */
    className?: string;
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
}: ResourceTableProps<T>) {
    const [deleteTargetId, setDeleteTargetId] = useState<number | null>(null);
    const [isDeleting, setIsDeleting] = useState<boolean>(false);

    const openDeleteModal = (id: number) => {
        setDeleteTargetId(id);
    };

    const closeDeleteModal = () => {
        if (isDeleting) {
            return;
        }
        setDeleteTargetId(null);
    };

    const confirmDelete = () => {
        if (deleteTargetId === null) {
            return;
        }

        setIsDeleting(true);

        router.delete(deleteUrl(deleteTargetId), {
            preserveScroll: true,
            onFinish: () => {
                setIsDeleting(false);
                setDeleteTargetId(null);
            },
        });
    };

    return (
        <div className="mx-auto w-full max-w-7xl space-y-4 p-4 sm:p-6">
            <Head title={title} />

            <div className="flex items-center justify-between">
                <h2 className="text-lg font-semibold text-slate-900">{title}</h2>
                <Link
                    href={addHref}
                    className="inline-flex items-center gap-2 rounded-md bg-blue-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-blue-950"
                >
                    <Plus className="h-4 w-4" />
                    {addLabel}
                </Link>
            </div>

            <Card className="w-full overflow-hidden py-0">
                <CardContent className="overflow-x-auto p-0">
                    <Table className="min-w-[640px]">
                        <TableHeader>
                            <TableRow className="border-b border-slate-200 hover:bg-transparent">
                                {columns.map((col, i) => (
                                    <TableHead
                                        key={i}
                                        className={`h-11 whitespace-nowrap bg-slate-50/80 text-xs font-semibold uppercase tracking-wide text-slate-500 ${
                                            i === 0 ? 'pl-6' : ''
                                        }`}
                                    >
                                        {col.header}
                                    </TableHead>
                                ))}
                                <TableHead className="h-11 bg-slate-50/80 pr-6 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {resource.data.length > 0 ? (
                                resource.data.map((row, i) => (
                                    <TableRow
                                        key={row.id}
                                        className={`border-b border-slate-100 transition-colors last:border-0 hover:bg-slate-50/70 ${
                                            i % 2 === 1 ? 'bg-slate-50/30' : ''
                                        }`}
                                    >
                                        {columns.map((col, j) => (
                                            <TableCell
                                                key={j}
                                                className={`py-3 text-sm ${col.className ?? 'text-slate-700'} ${
                                                    j === 0 ? 'pl-6' : ''
                                                }`}
                                            >
                                                {col.render(row)}
                                            </TableCell>
                                        ))}
                                        <TableCell className="py-3 pr-6 text-right">
                                            <div className="flex items-center justify-end gap-1.5">
                                                <Link
                                                    href={editHref(row)}
                                                    className="inline-flex h-8 w-8 items-center justify-center rounded-md bg-amber-400 text-white transition-colors hover:bg-amber-500"
                                                    aria-label="Edit"
                                                >
                                                    <Pencil className="h-3.5 w-3.5" />
                                                </Link>
                                                <button
                                                    type="button"
                                                    onClick={() => openDeleteModal(row.id)}
                                                    className="inline-flex h-8 w-8 items-center justify-center rounded-md bg-red-600 text-white transition-colors hover:bg-red-700"
                                                    aria-label="Delete"
                                                >
                                                    <Trash2 className="h-3.5 w-3.5" />
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

            {/* Delete Confirmation Modal */}
            {deleteTargetId !== null && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4"
                    onClick={closeDeleteModal}
                >
                    <div
                        className="w-full max-w-md rounded-lg bg-white shadow-xl"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div className="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                            <h5 className="text-base font-semibold text-slate-900">Confirm Delete</h5>
                            <button
                                type="button"
                                onClick={closeDeleteModal}
                                disabled={isDeleting}
                                className="text-slate-400 hover:text-slate-600 disabled:opacity-50"
                                aria-label="Close"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        </div>
                        <div className="px-5 py-4">
                            <p className="text-sm text-slate-700">{deleteConfirmMessage}</p>
                            <p className="mt-1 text-xs text-red-600">This action cannot be undone.</p>
                        </div>
                        <div className="flex justify-end gap-2 border-t border-slate-200 px-5 py-4">
                            <button
                                type="button"
                                onClick={closeDeleteModal}
                                disabled={isDeleting}
                                className="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 disabled:opacity-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                onClick={confirmDelete}
                                disabled={isDeleting}
                                className="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-red-700 disabled:opacity-50"
                            >
                                {isDeleting ? 'Deleting...' : 'Delete'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}