'use client';

import {
    useTable
    
    
    
} from '@tanstack/react-table';
import type {ColumnDef, RowData, SortingState} from '@tanstack/react-table';
import {
    ArrowUpDown,
    ArrowUp,
    ArrowDown,
    Search,
    RotateCw,
} from 'lucide-react';
import { useMemo, useState } from 'react';


import { features  } from './data-table-features';
import type {DataTableFeatures} from './data-table-features';
import { DataTablePagination } from './data-table-pagination';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

interface ClientDataTableProps<TData extends RowData> {
    columns: ColumnDef<DataTableFeatures, TData>[];
    data: TData[];
    /** Which fields to check when the user searches. */
    searchableColumns?: (keyof TData)[];
    emptyMessage?: string;
}

/** For small datasets — search, sort, and pagination all happen in the browser. */
export function ClientDataTable<TData extends RowData>({
    columns,
    data,
    searchableColumns = [],
    emptyMessage = 'No results.',
}: ClientDataTableProps<TData>) {
    const [searchInput, setSearchInput] = useState('');
    const [appliedSearch, setAppliedSearch] = useState('');
    const [sorting, setSorting] = useState<SortingState>([]);
    const [pagination, setPagination] = useState({
        pageIndex: 0,
        pageSize: 10,
    });

    const runSearch = () => setAppliedSearch(searchInput);

    const resetAll = () => {
        setSearchInput('');
        setAppliedSearch('');
        setSorting([]);
    };

    const filteredData = useMemo(() => {
        if (!appliedSearch || searchableColumns.length === 0) {
return data;
}

        const needle = appliedSearch.toLowerCase();

        return data.filter((row) =>
            searchableColumns.some((col) =>
                String(row[col] ?? '')
                    .toLowerCase()
                    .includes(needle),
            ),
        );
    }, [data, appliedSearch, searchableColumns]);

    const table = useTable({
        features,
        data: filteredData,
        columns,
        onSortingChange: setSorting,
        onPaginationChange: setPagination,
        state: { sorting, pagination },
    });

    const { pageIndex, pageSize } = pagination;
    const totalRows = filteredData.length;
    const totalPages = table.getPageCount();
    const rangeStart = totalRows === 0 ? 0 : pageIndex * pageSize + 1;
    const rangeEnd = Math.min(totalRows, (pageIndex + 1) * pageSize);

    return (
        <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            {/* Toolbar */}
            <div className="flex items-center justify-between gap-3 px-4 py-4">
                <div className="flex items-center gap-2">
                    {searchableColumns.length > 0 && (
                        <>
                            <input
                                type="text"
                                placeholder="Search..."
                                value={searchInput}
                                onChange={(e) => setSearchInput(e.target.value)}
                                onKeyDown={(e) =>
                                    e.key === 'Enter' && runSearch()
                                }
                                className="h-10 w-100 rounded-xl border border-slate-200 px-4 text-sm text-slate-700 outline-none placeholder:text-slate-400 focus:border-blue-300 focus:ring-2 focus:ring-blue-100"
                            />
                            <button
                                type="button"
                                onClick={runSearch}
                                aria-label="Search"
                                className="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600 text-white transition-colors hover:bg-blue-700"
                            >
                                <Search className="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                onClick={resetAll}
                                title="Reset table"
                                className="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 transition-colors hover:bg-slate-50"
                            >
                                <RotateCw className="h-4 w-4" />
                            </button>
                        </>
                    )}
                </div>
            </div>

            {/* Table */}
            <Table>
                <TableHeader>
                    {table.getHeaderGroups().map((headerGroup) => (
                        <TableRow
                            key={headerGroup.id}
                            className="border-y border-slate-200 bg-slate-50/80 hover:bg-slate-50/80"
                        >
                            {headerGroup.headers.map((header) => {
                                const canSort = header.column.getCanSort();
                                const sortDir = header.column.getIsSorted();

                                return (
                                    <TableHead
                                        key={header.id}
                                        className="px-4 py-3 text-xs font-semibold tracking-wide text-slate-500 uppercase"
                                    >
                                        {header.isPlaceholder ? null : canSort ? (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    header.column.toggleSorting(
                                                        sortDir === 'asc',
                                                    )
                                                }
                                                className="inline-flex items-center gap-1.5 hover:text-slate-700"
                                            >
                                                <table.FlexRender
                                                    header={header}
                                                />
                                                {sortDir === 'asc' ? (
                                                    <ArrowUp className="h-3.5 w-3.5" />
                                                ) : sortDir === 'desc' ? (
                                                    <ArrowDown className="h-3.5 w-3.5" />
                                                ) : (
                                                    <ArrowUpDown className="h-3.5 w-3.5 opacity-40" />
                                                )}
                                            </button>
                                        ) : (
                                            <table.FlexRender header={header} />
                                        )}
                                    </TableHead>
                                );
                            })}
                        </TableRow>
                    ))}
                </TableHeader>
                <TableBody>
                    {table.getRowModel().rows?.length ? (
                        table.getRowModel().rows.map((row, i) => (
                            <TableRow
                                key={row.id}
                                className={
                                    (i % 2 === 0
                                        ? 'bg-white'
                                        : 'bg-slate-50/60') +
                                    ' border-b border-slate-100 transition-colors last:border-0 hover:bg-blue-50/40'
                                }
                            >
                                {row.getVisibleCells().map((cell) => (
                                    <TableCell
                                        key={cell.id}
                                        className="px-4 py-3.5 text-sm text-slate-700"
                                    >
                                        <table.FlexRender cell={cell} />
                                    </TableCell>
                                ))}
                            </TableRow>
                        ))
                    ) : (
                        <TableRow>
                            <TableCell
                                colSpan={columns.length}
                                className="h-24 text-center text-slate-400"
                            >
                                {emptyMessage}
                            </TableCell>
                        </TableRow>
                    )}
                </TableBody>
            </Table>

            {/* Footer */}
            <div className="flex items-center justify-between px-4 py-4">
                <p className="text-sm text-slate-500">
                    Showing {rangeStart}-{rangeEnd} of {totalRows} results
                </p>
                <DataTablePagination
                    currentPage={pageIndex + 1}
                    totalPages={table.getPageCount()}
                    onPageChange={(page) =>
                        setPagination((p) => ({ ...p, pageIndex: page - 1 }))
                    }
                />
            </div>
        </div>
    );
}
