'use client';

import {
    useTable,
    type ColumnDef,
    type RowData,
    type SortingState,
} from '@tanstack/react-table';
import { useState, useEffect, useRef } from 'react';
import { router } from '@inertiajs/react';
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
import { Search } from 'lucide-react';
import { features, type DataTableFeatures } from './data-table-features';

interface DataTableProps<TData extends RowData> {
    columns: ColumnDef<DataTableFeatures, TData>[];
    data: TData[];
    mode?: 'client' | 'server';
    searchRoute?: string;
    initialSearch?: string;
    initialSort?: SortingState; // e.g. [{ id: 'course_code', desc: false }], from filters.sort/direction
    emptyMessage?: string;
}

/**
 * Generic, reusable table. Any module in your Order of Payment system
 * (OOPs, Disbursement Vouchers, Obligation Requests, etc.) reuses this
 * exact component — only `columns` and `data` change per page.
 */
export function DataTable<TData extends RowData>({
    columns,
    data,
    mode = 'client',
    searchRoute,
    initialSearch = '',
    initialSort = [],
    emptyMessage = 'No results.',
}: DataTableProps<TData>) {
    const [search, setSearch] = useState(initialSearch);
    const [sorting, setSorting] = useState<SortingState>(initialSort);

    const handleSortingChange = (updater: any) => {
        const next = typeof updater === 'function' ? updater(sorting) : updater;
        setSorting(next);

        if (mode === 'server' && searchRoute) {
            const sort = next[0]?.id;
            const direction = next[0]?.desc ? 'desc' : 'asc';

            router.get(
                searchRoute,
                { search, sort, direction },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }
        // in client mode, setSorting above is enough — createSortedRowModel()
        // picks up the new state and reorders `data` automatically
    };

    const runSearch = () => {
        if (searchRoute) {
            router.get(
                searchRoute,
                { search },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }
    };

    // In client mode, typing directly filters the in-memory data via TanStack's
    // own filter state — no network request, no router.get.
    const table = useTable({
        features,
        data,
        columns,
        manualSorting: mode === 'server',
        manualFiltering: mode === 'server',
        manualPagination: mode === 'server',
    });

    return (
        <>
            {searchRoute && (
                <div className="flex items-center gap-2 py-4">
                    <Input
                        placeholder="Search..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
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
                                {headerGroup.headers.map((header) => (
                                    <TableHead key={header.id}>
                                        {header.isPlaceholder ? null : (
                                            <table.FlexRender header={header} />
                                        )}
                                    </TableHead>
                                ))}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {table.getRowModel().rows?.length ? (
                            table.getRowModel().rows.map((row) => (
                                <TableRow key={row.id}>
                                    {row.getAllCells().map((cell) => (
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
        </>
    );
}
