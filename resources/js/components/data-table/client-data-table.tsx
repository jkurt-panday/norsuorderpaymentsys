'use client';

import { useMemo, useState } from 'react';
import {
    useTable,
    type ColumnDef,
    type RowData,
    type SortingState,
} from '@tanstack/react-table';
import { ArrowUpDown, ArrowUp, ArrowDown, Search } from 'lucide-react';

import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';

import { features, type DataTableFeatures } from './data-table-features';

interface ClientDataTableProps<TData extends RowData> {
    columns: ColumnDef<DataTableFeatures, TData>[];
    data: TData[];
    /** Which fields to check when the user types in the search box. */
    searchableColumns?: (keyof TData)[];
    emptyMessage?: string;
}

/**
 * For small datasets (roughly under a thousand rows). Everything — search,
 * sort, pagination — happens in the browser against `data`, which is
 * assumed to already be the FULL set (no Laravel round trips after mount).
 */
export function ClientDataTable<TData extends RowData>({
    columns,
    data,
    searchableColumns = [],
    emptyMessage = 'No results.',
}: ClientDataTableProps<TData>) {
    // `searchInput` is what the user is typing; `appliedSearch` is what's
    // actually used to filter. They only sync up when runSearch() fires —
    // that's what makes this button/Enter-triggered instead of live-as-you-type.
    const [searchInput, setSearchInput] = useState('');
    const [appliedSearch, setAppliedSearch] = useState('');
    const [sorting, setSorting] = useState<SortingState>([]);

    const runSearch = () => setAppliedSearch(searchInput);

    // Plain JS filter across the given fields — simpler than wiring up
    // TanStack's global-filter feature for a "search multiple columns" need.
    const filteredData = useMemo(() => {
        if (!appliedSearch || searchableColumns.length === 0) return data;

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
        state: { sorting },
    });

    return (
        <div>
            {searchableColumns.length > 0 && (
                <div className="flex items-center gap-2 py-4">
                    <Input
                        placeholder="Search..."
                        value={searchInput}
                        onChange={(e) => setSearchInput(e.target.value)}
                        onKeyDown={(e) => e.key === 'Enter' && runSearch()}
                        className="max-w-sm"
                    />
                    <Button onClick={runSearch} variant="outline" size="icon">
                        <Search className="h-4 w-4" />
                    </Button>
                </div>
            )}

            <div className="overflow-hidden border">
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id}>
                                {headerGroup.headers.map((header) => {
                                    const canSort = header.column.getCanSort();
                                    const sortDir = header.column.getIsSorted();

                                    return (
                                        <TableHead key={header.id}>
                                            {header.isPlaceholder ? null : canSort ? (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="-ml-3"
                                                    onClick={() =>
                                                        header.column.toggleSorting(
                                                            sortDir === 'asc',
                                                        )
                                                    }
                                                >
                                                    <table.FlexRender
                                                        header={header}
                                                    />
                                                    {sortDir === 'asc' ? (
                                                        <ArrowUp className="ml-2 h-4 w-4" />
                                                    ) : sortDir === 'desc' ? (
                                                        <ArrowDown className="ml-2 h-4 w-4" />
                                                    ) : (
                                                        <ArrowUpDown className="ml-2 h-4 w-4 opacity-40" />
                                                    )}
                                                </Button>
                                            ) : (
                                                <table.FlexRender
                                                    header={header}
                                                />
                                            )}
                                        </TableHead>
                                    );
                                })}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {table.getRowModel().rows?.length ? (
                            table.getRowModel().rows.map((row) => (
                                <TableRow key={row.id}>
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell key={cell.id}>
                                            <table.FlexRender cell={cell} />
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell
                                    colSpan={columns.length}
                                    className="h-24 text-center"
                                >
                                    {emptyMessage}
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>

            <div className="flex items-center justify-end space-x-2 py-4">
                <div className="flex-1 text-sm text-muted-foreground">
                    {filteredData.length} row(s) total.
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => table.previousPage()}
                    disabled={!table.getCanPreviousPage()}
                >
                    Previous
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => table.nextPage()}
                    disabled={!table.getCanNextPage()}
                >
                    Next
                </Button>
            </div>
        </div>
    );
}
