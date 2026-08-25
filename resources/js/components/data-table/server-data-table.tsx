import { useState } from 'react';
import { router } from '@inertiajs/react';
import {
    useTable,
    type ColumnDef,
    type RowData,
    ColumnVisibilityState,
} from '@tanstack/react-table';
import {
    ArrowUpDown,
    ArrowUp,
    ArrowDown,
    Search,
    RotateCw,
    SlidersHorizontal,
} from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { format } from 'date-fns';
import { DateRangeFilter } from './date-range-picker';
import { DataTablePagination } from './data-table-pagination';

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

interface ServerFilters {
    search: string;
    sort: string;
    direction: 'asc' | 'desc';
    date_from?: string;
    date_to?: string;
}

interface ServerPagination {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface ServerDataTableProps<TData extends RowData> {
    columns: ColumnDef<DataTableFeatures, TData>[];
    data: TData[];
    /** The route search/sort/pagination requests are sent to, e.g. "/staff/courses". */
    route: string;
    /** Whatever Laravel sent back as `filters` — reflects the CURRENT state of the query. */
    filters: ServerFilters;
    /** The Laravel paginator's meta fields (courses.current_page, .last_page, etc). */
    pagination: ServerPagination;
    /** Column ids that are allowed to be clicked for sorting — should mirror your backend whitelist. */
    sortableColumns?: string[];
    emptyMessage?: string;
}

/**
 * For large datasets. `data` is only ever the CURRENT PAGE — search, sort,
 * and pagination all round-trip to Laravel via router.get, which is the
 * single source of truth for ordering/filtering/paging.
 */
export function ServerDataTable<TData extends RowData>({
    columns,
    data,
    route,
    filters,
    pagination,
    sortableColumns = [],
    emptyMessage = 'No results.',
}: ServerDataTableProps<TData>) {
    const [search, setSearch] = useState(filters.search);
    // inside ServerDataTable:
    const [dateFrom, setDateFrom] = useState<Date | undefined>(
        filters.date_from ? new Date(filters.date_from) : undefined,
    );
    const [dateTo, setDateTo] = useState<Date | undefined>(
        filters.date_to ? new Date(filters.date_to) : undefined,
    );

    // inside ServerDataTable, alongside your other state:
    const [columnVisibility, setColumnVisibility] =
        useState<ColumnVisibilityState>({});

    const visit = (params: Record<string, string | number | undefined>) => {
        router.get(
            route,
            {
                search: filters.search,
                sort: filters.sort,
                direction: filters.direction,
                ...params,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const applyDateRange = ({
        from,
        to,
    }: {
        from: Date | undefined;
        to: Date | undefined;
    }) => {
        visit({
            date_from: from ? format(from, 'yyyy-MM-dd') : undefined,
            date_to: to ? format(to, 'yyyy-MM-dd') : undefined,
            page: 1,
        });
    };

    const runSearch = () => visit({ search, page: 1 }); // reset to page 1 on a new search

    const toggleSort = (columnId: string) => {
        const isSameColumn = filters.sort === columnId;
        const nextDirection =
            isSameColumn && filters.direction === 'asc' ? 'desc' : 'asc';
        visit({ sort: columnId, direction: nextDirection });
    };

    const resetAll = () => {
        setSearch('');
        setDateFrom(undefined);
        setDateTo(undefined);
        router.get(
            route,
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const rangeStart =
        pagination.total === 0
            ? 0
            : (pagination.current_page - 1) * pagination.per_page + 1;
    const rangeEnd = Math.min(
        pagination.total,
        pagination.current_page * pagination.per_page,
    );

    const columnLabels: Record<string, string> = {
      reference_number: "Reference No.",
        full_name: "Name",
        email: 'Email',
      course: 'Course',
      contact_num: "Contact No.",
      enrolled_under: "Enrolled Under",
        sy_last_attended: "SY Last Attended",
      semester: 'Semester',
        created_at: "Submitted",
    }

    const table = useTable({
        features,
        data,
        columns,
        manualSorting: true,
        manualPagination: true,
        onColumnVisibilityChange: setColumnVisibility,
        state: { columnVisibility },
    });

    return (
        <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="flex items-center justify-between gap-3 px-4 py-4">
                <div className="flex items-center gap-2">
                    <Input
                        placeholder="Search for ref num, first name, last name, email"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        onKeyDown={(e) => e.key === 'Enter' && runSearch()}
                        className="h-10 w-100 rounded-xl border border-slate-200 px-4 text-sm text-slate-700 outline-none placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                    />
                    <Button
                        onClick={runSearch}
                        variant="outline"
                        size="icon"
                        className="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600 text-white transition-colors hover:bg-blue-700"
                    >
                        <Search className="h-4 w-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={resetAll}
                        title="Reset table"
                        className="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 transition-colors hover:bg-slate-50"
                    >
                        <RotateCw className="h-4 w-4" />
                    </Button>
                    {/* in the toolbar, next to the search input */}
                    <DateRangeFilter
                        from={dateFrom}
                        to={dateTo}
                        onFromChange={setDateFrom}
                        onToChange={setDateTo}
                        onApply={applyDateRange}
                    />
                    {/* in the toolbar, next to your reset button */}
                    <DropdownMenu>
                        <DropdownMenuTrigger
                            render={
                                <Button variant="outline" title="Toggle columns">
                                    <SlidersHorizontal className="mr-2 h-4 w-4" />
                                    Columns
                                </Button>
                            }
                        />
                        <DropdownMenuContent align="start" className="w-40">
                            {table
                                .getAllColumns()
                                .filter((column) => column.getCanHide())
                                .map((column) => (
                                    <DropdownMenuCheckboxItem
                                        key={column.id}
                                        checked={column.getIsVisible()}
                                        onCheckedChange={(value) => column.toggleVisibility(!!value)}
                                    >
                                        {columnLabels[column.id] ?? column.id}
                                    </DropdownMenuCheckboxItem>
                                ))}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            <div className="overflow-hidden">
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow
                                key={headerGroup.id}
                                className="border-y border-slate-200 bg-slate-50/80 hover:bg-slate-50/80"
                            >
                                {headerGroup.headers.map((header) => {
                                    const canSort = sortableColumns.includes(
                                        header.column.id,
                                    );
                                    const isActive =
                                        filters.sort === header.column.id;

                                    return (
                                        <TableHead
                                            key={header.id}
                                            className="px-4 py-3 text-xs font-semibold tracking-wide text-slate-500 uppercase"
                                        >
                                            {header.isPlaceholder ? null : canSort ? (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="inline-flex items-center gap-1.5 hover:text-slate-700"
                                                    onClick={() =>
                                                        toggleSort(
                                                            header.column.id,
                                                        )
                                                    }
                                                >
                                                    <table.FlexRender
                                                        header={header}
                                                    />
                                                    {isActive &&
                                                    filters.direction ===
                                                        'asc' ? (
                                                        <ArrowUp className="ml-2 h-4 w-4" />
                                                    ) : isActive &&
                                                      filters.direction ===
                                                          'desc' ? (
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
                            table.getRowModel().rows.map((row, i) => (
                                <TableRow
                                    key={row.id}
                                    className={
                                        (i % 2 === 0
                                            ? 'bg-white'
                                            : 'bg-slate-200/60') +
                                        ' border-b border-slate-100 transition-colors last:border-0 hover:bg-gray-200'
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
            </div>

            <div className="flex items-center justify-between px-4 py-4">
                <p className="text-sm text-slate-500">
                    Showing {rangeStart}-{rangeEnd} ({pagination.total} total)
                </p>
                <DataTablePagination
                    currentPage={pagination.current_page}
                    totalPages={pagination.last_page}
                    onPageChange={(page) => visit({ page })}
                />
            </div>
        </div>
    );
}
