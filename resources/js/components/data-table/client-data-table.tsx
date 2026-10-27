'use client';

import { useTable } from '@tanstack/react-table';
import type { ColumnDef, RowData, SortingState } from '@tanstack/react-table';
import {
    ArrowUpDown,
    ArrowUp,
    ArrowDown,
    Search,
    RotateCw,
    SlidersHorizontal,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import { features } from './data-table-features';
import type { DataTableFeatures } from './data-table-features';
import { DataTablePagination } from './data-table-pagination';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
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

const formatColumnLabel = (columnId: string) => {
    const sentence = columnId.replaceAll('_', ' ').toLowerCase();

    return sentence.charAt(0).toUpperCase() + sentence.slice(1);
};

export interface ClientDataTableFilter<TData extends RowData> {
    column: keyof TData;
    label: string;
    options: { label: string; value: string }[];
    /** Optional fixed width for the trigger and its popup. */
    widthClassName?: string;
    /** Optional width override for the popup when options need more room. */
    contentWidthClassName?: string;
}

interface ClientDataTableProps<TData extends RowData> {
    columns: ColumnDef<DataTableFeatures, TData>[];
    data: TData[];
    /** Which fields to check when the user searches. */
    searchableColumns?: (keyof TData)[];
    /** Reusable client-side select filters. */
    filters?: ClientDataTableFilter<TData>[];
    /** Backward-compatible single-filter API. */
    filter?: ClientDataTableFilter<TData>;
    /** Stable key used to persist column visibility per table. */
    storageKey?: string;
    searchPlaceholder?: string;
    emptyMessage?: string;
}

/** For small datasets — search, sort, and pagination all happen in the browser. */
export function ClientDataTable<TData extends RowData>({
    columns,
    data,
    searchableColumns = [],
    filter,
    filters,
    storageKey = 'default',
    searchPlaceholder = 'Search...',
    emptyMessage = 'No results.',
}: ClientDataTableProps<TData>) {
    const activeFilters = useMemo(
        () => filters ?? (filter ? [filter] : []),
        [filter, filters],
    );
    const [searchInput, setSearchInput] = useState('');
    const [filterValues, setFilterValues] = useState<Record<string, string>>(
        {},
    );
    const [appliedSearch, setAppliedSearch] = useState('');
    const [sorting, setSorting] = useState<SortingState>([]);
    const columnVisibilityStorageKey = `client-data-table:${storageKey}:column-visibility`;

    const [columnVisibility, setColumnVisibility] = useState<
        Record<string, boolean>
    >(() => {
        if (typeof window === 'undefined') {
            return {};
        }

        try {
            const savedColumnVisibility = window.localStorage.getItem(
                columnVisibilityStorageKey,
            );

            if (!savedColumnVisibility) {
                return {};
            }

            const parsedColumnVisibility = JSON.parse(savedColumnVisibility);

            if (
                !parsedColumnVisibility ||
                typeof parsedColumnVisibility !== 'object' ||
                Array.isArray(parsedColumnVisibility)
            ) {
                return {};
            }

            return Object.fromEntries(
                Object.entries(parsedColumnVisibility).filter(
                    ([, value]) => typeof value === 'boolean',
                ),
            ) as Record<string, boolean>;
        } catch {
            return {};
        }
    });

    useEffect(() => {
        window.localStorage.setItem(
            columnVisibilityStorageKey,
            JSON.stringify(columnVisibility),
        );
    }, [columnVisibility, columnVisibilityStorageKey]);

    const [pagination, setPagination] = useState({
        pageIndex: 0,
        pageSize: 10,
    });

    const runSearch = () => setAppliedSearch(searchInput);

    const resetAll = () => {
        setSearchInput('');
        setAppliedSearch('');
        setFilterValues({});
        setSorting([]);
    };

    const filteredData = useMemo(() => {
        const needle = appliedSearch.toLowerCase();

        return data.filter((row) => {
            const matchesSearch =
                !needle ||
                searchableColumns.some((col) =>
                    String(row[col] ?? '')
                        .toLowerCase()
                        .includes(needle),
                );
            const matchesFilters = activeFilters.every((currentFilter) => {
                const selectedValue =
                    filterValues[String(currentFilter.column)];

                return (
                    !selectedValue ||
                    selectedValue === 'all' ||
                    String(row[currentFilter.column] ?? '').toLowerCase() ===
                        selectedValue.toLowerCase()
                );
            });

            return matchesSearch && matchesFilters;
        });
    }, [data, appliedSearch, searchableColumns, activeFilters, filterValues]);

    const table = useTable({
        features,
        data: filteredData,
        columns,
        onSortingChange: setSorting,
        onPaginationChange: setPagination,
        onColumnVisibilityChange: setColumnVisibility,
        state: { sorting, pagination, columnVisibility },
    });

    const { pageIndex, pageSize } = pagination;
    const totalRows = filteredData.length;
    const rangeStart = totalRows === 0 ? 0 : pageIndex * pageSize + 1;
    const rangeEnd = Math.min(totalRows, (pageIndex + 1) * pageSize);

    return (
        <div className="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
            {/* Toolbar */}
            <div className="flex items-center justify-between gap-3 px-4 py-4">
                <div className="flex flex-wrap items-center gap-2">
                    {searchableColumns.length > 0 && (
                        <>
                            <input
                                type="text"
                                placeholder={searchPlaceholder}
                                value={searchInput}
                                onChange={(e) => setSearchInput(e.target.value)}
                                onKeyDown={(e) =>
                                    e.key === 'Enter' && runSearch()
                                }
                                className="h-10 w-100 rounded-xl border border-input bg-background px-4 text-sm text-foreground outline-none placeholder:text-muted-foreground focus:border-primary focus:ring-2 focus:ring-ring/20"
                            />
                            <button
                                type="button"
                                onClick={runSearch}
                                aria-label="Search"
                                className="flex h-10 w-10 items-center justify-center rounded-xl bg-primary text-primary-foreground transition-colors hover:bg-primary/90"
                            >
                                <Search className="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                onClick={resetAll}
                                title="Reset table"
                                className="flex h-10 w-10 items-center justify-center rounded-xl border border-input bg-background text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                            >
                                <RotateCw className="h-4 w-4" />
                            </button>
                        </>
                    )}
                    <DropdownMenu>
                        <DropdownMenuTrigger
                            render={
                                <Button
                                    variant="outline"
                                    title="Toggle columns"
                                    className="h-10 rounded-xl bg-background"
                                >
                                    <SlidersHorizontal className="mr-2 h-4 w-4" />
                                    Columns
                                </Button>
                            }
                        />
                        <DropdownMenuContent align="start" className="w-48">
                            {table
                                .getAllColumns()
                                .filter((column) => column.getCanHide())
                                .map((column) => (
                                    <DropdownMenuCheckboxItem
                                        key={column.id}
                                        checked={column.getIsVisible()}
                                        onCheckedChange={(value) =>
                                            column.toggleVisibility(!!value)
                                        }
                                    >
                                        {formatColumnLabel(column.id)}
                                    </DropdownMenuCheckboxItem>
                                ))}
                        </DropdownMenuContent>
                    </DropdownMenu>
                    {activeFilters.map((currentFilter) => {
                        const filterKey = String(currentFilter.column);
                        const widthClassName =
                            currentFilter.widthClassName ?? 'w-50';
                        const contentWidthClassName =
                            currentFilter.contentWidthClassName ??
                            widthClassName;

                        return (
                            <Select
                                key={filterKey}
                                items={[
                                    {
                                        label: `All ${currentFilter.label}`,
                                        value: 'all',
                                    },
                                    ...currentFilter.options.map((option) => ({
                                        label: option.label,
                                        value: option.value.toLowerCase(),
                                    })),
                                ]}
                                value={filterValues[filterKey] ?? 'all'}
                                onValueChange={(value) => {
                                    setFilterValues((previous) => ({
                                        ...previous,
                                        [filterKey]: value ?? 'all',
                                    }));
                                    setPagination((previous) => ({
                                        ...previous,
                                        pageIndex: 0,
                                    }));
                                }}
                            >
                                <SelectTrigger
                                    className={`h-10! ${widthClassName} text-medium rounded-xl border-input bg-background px-4`}
                                >
                                    <SelectValue
                                        placeholder={`Select ${currentFilter.label}`}
                                    />
                                </SelectTrigger>
                                <SelectContent
                                    align="start"
                                    alignItemWithTrigger={false}
                                    className={`${contentWidthClassName} wrap-break-word whitespace-normal`}
                                >
                                    <SelectGroup>
                                        <SelectLabel>
                                            {currentFilter.label}
                                        </SelectLabel>
                                        <SelectItem value="all">
                                            All {currentFilter.label}
                                        </SelectItem>
                                        {currentFilter.options.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value.toLowerCase()}
                                                className="wrap-break-word whitespace-normal"
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        );
                    })}
                </div>
            </div>

            {/* Table */}
            <div className="custom-scrollbar rotate-180 overflow-x-auto">
                <div className="min-w-max rotate-180">
                    <Table>
                        <TableHeader>
                            {table.getHeaderGroups().map((headerGroup) => (
                                <TableRow
                                    key={headerGroup.id}
                                    className="border-y border-border bg-muted/50 hover:bg-muted/50"
                                >
                                    {headerGroup.headers.map((header) => {
                                        const canSort =
                                            header.column.getCanSort();
                                        const sortDir =
                                            header.column.getIsSorted();

                                        return (
                                            <TableHead
                                                key={header.id}
                                                className="px-4 py-4 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                            >
                                                {header.isPlaceholder ? null : canSort ? (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            header.column.toggleSorting(
                                                                sortDir ===
                                                                    'asc',
                                                            )
                                                        }
                                                        className="inline-flex items-center gap-1.5 hover:text-foreground"
                                                    >
                                                        <table.FlexRender
                                                            header={header}
                                                        />
                                                        {sortDir === 'asc' ? (
                                                            <ArrowUp className="h-3.5 w-3.5" />
                                                        ) : sortDir ===
                                                          'desc' ? (
                                                            <ArrowDown className="h-3.5 w-3.5" />
                                                        ) : (
                                                            <ArrowUpDown className="h-3.5 w-3.5 opacity-40" />
                                                        )}
                                                    </button>
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
                                table.getRowModel().rows.map((row, i) => (
                                    <TableRow
                                        key={row.id}
                                        className={
                                            (i % 2 === 0
                                                ? 'bg-card'
                                                : 'bg-muted/30') +
                                            ' border-b border-border transition-colors last:border-0 hover:bg-muted/60'
                                        }
                                    >
                                        {row.getVisibleCells().map((cell) => (
                                            <TableCell
                                                key={cell.id}
                                                className="px-4 py-3.5 text-sm text-foreground"
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
                                        className="h-24 text-center text-muted-foreground"
                                    >
                                        {emptyMessage}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>

            {/* Footer */}
            <div className="flex items-center justify-between px-4 py-4">
                <p className="text-sm text-muted-foreground">
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
