import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Search,
    DollarSign,
    GraduationCap,
    Layers,
    Wallet,
    PlusCircle,
    Pencil,
    Trash2,
} from 'lucide-react';
import React, { useState, useEffect, useRef } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
    CardFooter,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';

export interface LedgerRecord {
    id: string | number;
    name: string;
    course: string;
    schoolYear: string;
    term: string;
    units: number;
    transactionDate: string;
    referenceNo: string;
    particulars: string;
    ratePerUnit: number;
    amount: number;
    arPayment: 'AR' | 'Payment' | 'Adjustment';
    remark: string;
    inputBy: string;
}

export interface LedgerPaginator {
    data: LedgerRecord[];
    links?: { url: string | null; label: string; active: boolean }[];
    meta?: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    current_page?: number;
    last_page?: number;
    per_page?: number;
    total?: number;
}

function currency(n: number) {
    return `₱${(n ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function formatTransactionDate(value?: string | null) {
    if (!value) {
        return '-';
    }

    const normalized = String(value).trim();

    if (!normalized) {
        return '-';
    }

    // Extract YYYY-MM-DD date part to prevent browser timezone shifting
    const datePart = normalized.includes('T')
        ? normalized.split('T')[0]
        : normalized.split(' ')[0];
    const parsedDate = new Date(`${datePart}T00:00:00`);
    if (Number.isNaN(parsedDate.getTime())) return datePart;
    return parsedDate.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
    });
}

interface IndexProps {
    records?: LedgerPaginator;
    filters?: { search?: string; year?: string; month?: string };
    availableYears?: number[];
    stats?: {
        totalStudents?: number;
        totalUnits?: number;
        totalCharges?: number;
        totalPayments?: number;
        outstandingBalance?: number;
    };
}

export default function Index({
    records,
    filters = {},
    stats,
    availableYears = [],
}: IndexProps) {
    const { flash } = usePage<{
        flash?: { success?: string; error?: string };
    }>().props;
    const rows: LedgerRecord[] = records?.data ?? [];
    const [searchQuery, setSearchQuery] = useState(filters?.search ?? '');
    const [selectedYear, setSelectedYear] = useState(filters?.year ?? '');
    const [selectedMonth, setSelectedMonth] = useState(filters?.month ?? '');
    const importForm = useForm<{ file: File | null }>({ file: null });
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    // Debounced search — fires 300ms after the user stops typing
    useEffect(() => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            applyFilters(searchQuery, selectedYear, selectedMonth);
        }, 300);
        return () => {
            if (debounceRef.current) clearTimeout(debounceRef.current);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchQuery]);

    const applyFilters = (
        nextSearch = searchQuery,
        nextYear = selectedYear,
        nextMonth = selectedMonth,
    ) => {
        const params: Record<string, string> = {};

        if (nextSearch.trim()) {
            params.search = nextSearch.trim();
        }

        if (nextYear) {
            params.year = nextYear;
        }

        if (nextMonth) {
            params.month = nextMonth;
        }

        router.get('/graduate-ledger', params, {
            preserveState: true,
            replace: true,
        });
    };

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (debounceRef.current) clearTimeout(debounceRef.current);
        applyFilters();
    };

    const handleFilterChange = () => {
        const params: Record<string, string> = {};

        if (searchQuery.trim()) {
            params.search = searchQuery.trim();
        }

        if (selectedYear) {
            params.year = selectedYear;
        }

        if (selectedMonth) {
            params.month = selectedMonth;
        }

        router.get('/graduate-ledger', params, {
            preserveState: true,
            replace: true,
        });
    };

    // ---- Server-backed Metric Summary ----
    const totalStudents = stats?.totalStudents ?? 0;
    const totalUnits = stats?.totalUnits ?? 0;
    const totalCharges = stats?.totalCharges ?? 0;
    const outstandingBalance = stats?.outstandingBalance ?? 0;

    const currentPage =
        records?.meta?.current_page ?? records?.current_page ?? 1;
    const lastPage = records?.meta?.last_page ?? records?.last_page ?? 1;
    const totalRecordCount =
        records?.meta?.total ?? records?.total ?? rows.length;
    const paginationLinks = records?.links ?? [];

    return (
        <div className="min-h-screen bg-[#FAFAF5] p-4 md:p-8">
            <Head title="Graduate School Ledger" />

            {flash?.success && (
                <div className="mx-auto mb-4 flex max-w-7xl items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                    <span className="text-emerald-500">✓</span> {flash.success}
                </div>
            )}
            {flash?.error && (
                <div className="mx-auto mb-4 flex max-w-7xl items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">
                    <span>✗</span> {flash.error}
                </div>
            )}
            {importForm.errors.file && (
                <div className="mx-auto mb-4 flex max-w-7xl items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">
                    <span>✗</span> {importForm.errors.file}
                </div>
            )}

            {importForm.processing && (
                <div className="mx-auto mb-4 max-w-7xl space-y-2 rounded-lg border border-[#CFE3FF] bg-white p-4 shadow-xs">
                    <div className="flex items-center justify-between text-sm font-medium text-[#0B3D91]">
                        <span>Uploading and processing ledger data...</span>
                        <span>
                            {importForm.progress
                                ? `${importForm.progress.percentage}%`
                                : 'Processing...'}
                        </span>
                    </div>
                    <div className="h-2 w-full overflow-hidden rounded-full bg-[#EAF2FF]">
                        <div
                            className="h-full bg-[#0F6FFF] transition-all duration-150"
                            style={{
                                width: `${importForm.progress ? importForm.progress.percentage : 100}%`,
                            }}
                        />
                    </div>
                </div>
            )}

            <div className="mx-auto max-w-7xl space-y-6">
                {/* Top Header / Action Bar */}
                <div className="flex flex-col gap-4 border-b border-[#CFE3FF] pb-5 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-bold tracking-tight text-[#0B3D91]">
                                Graduate School Ledger
                            </h1>
                            <Badge
                                variant="outline"
                                className="border-[#B9D8FF] bg-[#EAF2FF] font-semibold text-[#0B62E0]"
                            >
                                Postgraduate Registry
                            </Badge>
                        </div>
                        <p className="mt-0.5 text-sm text-[#5C7A9E]">
                            Tuition, fees, and payment transactions by student.
                        </p>
                    </div>

                    <div className="flex w-full flex-col gap-2 sm:flex-row sm:items-center sm:justify-end md:w-auto">
                        <form
                            onSubmit={handleSearchSubmit}
                            className="flex w-full flex-wrap items-center gap-2 sm:w-auto"
                        >
                            <div className="relative w-full sm:w-64">
                                <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-[#7FA6D6]" />
                                <Input
                                    type="search"
                                    placeholder="Search name, course, or OR/JEV #..."
                                    className="h-9 border-[#CFE3FF] bg-white pl-8 focus-visible:ring-[#0F6FFF]"
                                    value={searchQuery}
                                    onChange={(e) =>
                                        setSearchQuery(e.target.value)
                                    }
                                />
                            </div>

                            {/* Dynamic year filter from DB */}
                            <select
                                value={selectedYear}
                                onChange={(e) => {
                                    const nextYear = e.target.value;
                                    setSelectedYear(nextYear);
                                    applyFilters(
                                        searchQuery,
                                        nextYear,
                                        selectedMonth,
                                    );
                                }}
                                className="h-9 rounded-md border border-[#CFE3FF] bg-white px-3 text-sm text-[#0B3D91]"
                            >
                                <option value="">All Years</option>
                                {availableYears.map((yr) => (
                                    <option key={yr} value={String(yr)}>
                                        {yr}
                                    </option>
                                ))}
                            </select>

                            <select
                                value={selectedMonth}
                                onChange={(e) => {
                                    const nextMonth = e.target.value;
                                    setSelectedMonth(nextMonth);
                                    applyFilters(
                                        searchQuery,
                                        selectedYear,
                                        nextMonth,
                                    );
                                }}
                                className="h-9 rounded-md border border-[#CFE3FF] bg-white px-3 text-sm text-[#0B3D91]"
                            >
                                <option value="">All Months</option>
                                <option value="1">Jan</option>
                                <option value="2">Feb</option>
                                <option value="3">Mar</option>
                                <option value="4">Apr</option>
                                <option value="5">May</option>
                                <option value="6">Jun</option>
                                <option value="7">Jul</option>
                                <option value="8">Aug</option>
                                <option value="9">Sep</option>
                                <option value="10">Oct</option>
                                <option value="11">Nov</option>
                                <option value="12">Dec</option>
                            </select>
                        </form>

                        <div className="ml-auto flex flex-wrap items-center justify-end gap-2">
                            <label className="inline-flex cursor-pointer items-center rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm font-medium text-[#0B3D91] transition-colors hover:bg-[#F3F8FF]">
                                <input
                                    type="file"
                                    accept=".csv,.xlsx,.xls"
                                    className="hidden"
                                    onChange={(e) => {
                                        const file =
                                            e.target.files?.[0] ?? null;
                                        importForm.setData('file', file);

                                        if (file) {
                                            importForm.post(
                                                '/graduate-ledger/import',
                                                {
                                                    forceFormData: true,
                                                    preserveScroll: true,
                                                    onSuccess: () => {
                                                        importForm.reset(
                                                            'file',
                                                        );
                                                        e.currentTarget.value =
                                                            '';
                                                    },
                                                },
                                            );
                                        }
                                    }}
                                />
                                Import Excel/CSV
                            </label>

                            <Button
                                className="bg-[#0F6FFF] text-white hover:bg-[#0B5DDB]"
                                onClick={() =>
                                    router.get('/graduate-ledger/add')
                                }
                            >
                                <PlusCircle className="mr-1.5 h-4 w-4" />
                                New Transaction
                            </Button>
                        </div>
                    </div>
                </div>

                {/* Metrics Row */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card className="border border-[#CFE3FF] bg-white shadow-xs">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-[#5C7A9E]">
                                Students on Ledger
                            </CardTitle>
                            <GraduationCap className="h-4 w-4 text-[#0F6FFF]" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold tracking-tight text-[#0B3D91]">
                                {totalStudents}
                            </div>
                            <p className="mt-1 text-[10px] text-[#8AA8CC]">
                                Unique active students
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border border-[#CFE3FF] bg-white shadow-xs">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-[#5C7A9E]">
                                Total Units
                            </CardTitle>
                            <Layers className="h-4 w-4 text-[#0F6FFF]" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold tracking-tight text-[#0B3D91]">
                                {totalUnits}
                            </div>
                            <p className="mt-1 text-[10px] text-[#8AA8CC]">
                                Total units enrolled
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border border-[#CFE3FF] bg-white shadow-xs">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-[#5C7A9E]">
                                Total Charges (AR)
                            </CardTitle>
                            <DollarSign className="h-4 w-4 text-[#0F6FFF]" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold tracking-tight text-[#0B3D91]">
                                {currency(totalCharges)}
                            </div>
                            <p className="mt-1 text-[10px] text-[#8AA8CC]">
                                Total tuition + fees billed
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border border-[#CFE3FF] bg-white shadow-xs">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-[#5C7A9E]">
                                Outstanding Balance
                            </CardTitle>
                            <Wallet className="h-4 w-4 text-[#0F6FFF]" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold tracking-tight text-[#0B3D91]">
                                {currency(outstandingBalance)}
                            </div>
                            <p className="mt-1 text-[10px] text-[#8AA8CC]">
                                Net pending balance
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Ledger Table */}
                <Card className="border border-[#CFE3FF] bg-white">
                    <CardHeader className="pb-2">
                        <CardTitle className="text-md text-[#0B3D91]">
                            Transaction Ledger
                        </CardTitle>
                        <CardDescription className="text-[#7FA6D6]">
                            Showing {rows.length} of {totalRecordCount} record
                            {totalRecordCount === 1 ? '' : 's'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full border-collapse text-sm">
                            <thead>
                                <tr className="border-b border-[#CFE3FF] bg-[#F3F8FF]">
                                    <th className="py-2 pr-4 pl-2 text-left font-medium whitespace-nowrap text-[#5C7A9E]">
                                        Name
                                    </th>
                                    <th className="py-2 pr-4 text-left font-medium whitespace-nowrap text-[#5C7A9E]">
                                        Course
                                    </th>
                                    <th className="py-2 pr-4 text-left font-medium whitespace-nowrap text-[#5C7A9E]">
                                        S.Y.
                                    </th>
                                    <th className="py-2 pr-4 text-left font-medium whitespace-nowrap text-[#5C7A9E]">
                                        Term
                                    </th>
                                    <th className="py-2 pr-4 text-right font-medium whitespace-nowrap text-[#5C7A9E]">
                                        Units
                                    </th>
                                    <th className="py-2 pr-4 text-left font-medium whitespace-nowrap text-[#5C7A9E]">
                                        Trans. Date
                                    </th>
                                    <th className="py-2 pr-4 text-left font-medium whitespace-nowrap text-[#5C7A9E]">
                                        Ref. (JEV/OR #)
                                    </th>
                                    <th className="py-2 pr-4 text-left font-medium whitespace-nowrap text-[#5C7A9E]">
                                        Particulars
                                    </th>
                                    <th className="py-2 pr-4 text-right font-medium whitespace-nowrap text-[#5C7A9E]">
                                        Rate/Unit
                                    </th>
                                    <th className="py-2 pr-4 text-left font-medium whitespace-nowrap text-[#5C7A9E]">
                                        AR/Payment
                                    </th>
                                    <th className="py-2 pr-4 text-right font-medium whitespace-nowrap text-[#5C7A9E]">
                                        Amount
                                    </th>
                                    <th className="py-2 pr-4 text-left font-medium whitespace-nowrap text-[#5C7A9E]">
                                        Remark
                                    </th>
                                    <th className="py-2 pr-4 text-left font-medium whitespace-nowrap text-[#5C7A9E]">
                                        Input By
                                    </th>
                                    <th className="py-2 pr-2 text-center font-medium whitespace-nowrap text-[#5C7A9E]">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={14}
                                            className="py-8 text-center text-sm text-[#8AA8CC]"
                                        >
                                            No transactions found. Upload a
                                            CSV/Excel file or add one manually.
                                        </td>
                                    </tr>
                                ) : (
                                    rows.map((r) => (
                                        <tr
                                            key={r.id}
                                            className="border-b border-[#EAF2FF] hover:bg-[#F3F8FF]"
                                        >
                                            <td className="py-2 pr-4 pl-2 font-medium whitespace-nowrap text-[#0B3D91]">
                                                {r.name}
                                            </td>
                                            <td className="py-2 pr-4 text-[#334E68]">
                                                {r.course}
                                            </td>
                                            <td className="py-2 pr-4 text-[#334E68]">
                                                {r.schoolYear}
                                            </td>
                                            <td className="py-2 pr-4 text-[#334E68]">
                                                {r.term}
                                            </td>
                                            <td className="py-2 pr-4 text-right text-[#334E68]">
                                                {r.units}
                                            </td>
                                            <td className="py-2 pr-4 whitespace-nowrap text-[#334E68]">
                                                {formatTransactionDate(
                                                    r.transactionDate,
                                                )}
                                            </td>
                                            <td className="py-2 pr-4 whitespace-nowrap text-[#334E68]">
                                                {r.referenceNo}
                                            </td>
                                            <td className="py-2 pr-4 text-[#334E68]">
                                                {r.particulars}
                                            </td>
                                            <td className="py-2 pr-4 text-right text-[#334E68]">
                                                {currency(r.ratePerUnit)}
                                            </td>
                                            <td className="py-2 pr-4">
                                                <Badge
                                                    variant="outline"
                                                    className="border-[#B9D8FF] bg-[#EAF2FF] text-[#0B62E0]"
                                                >
                                                    {r.arPayment}
                                                </Badge>
                                            </td>
                                            <td className="py-2 pr-4 text-right font-medium text-[#0B3D91]">
                                                {currency(r.amount)}
                                            </td>
                                            <td className="py-2 pr-4 text-[#8AA8CC]">
                                                {r.remark}
                                            </td>
                                            <td className="py-2 pr-4 text-[#8AA8CC]">
                                                {r.inputBy}
                                            </td>
                                            <td className="py-2 pr-2 text-center whitespace-nowrap">
                                                <button
                                                    onClick={() =>
                                                        router.get(
                                                            `/graduate-ledger/${r.id}/edit`,
                                                        )
                                                    }
                                                    className="mr-1 inline-flex items-center justify-center rounded p-1.5 text-[#0B62E0] transition-colors hover:bg-[#EAF2FF]"
                                                    title="Edit"
                                                >
                                                    <Pencil className="h-3.5 w-3.5" />
                                                </button>
                                                <button
                                                    onClick={() =>
                                                        handleDelete(
                                                            r.id,
                                                            r.name,
                                                        )
                                                    }
                                                    className="inline-flex items-center justify-center rounded p-1.5 text-red-500 transition-colors hover:bg-red-50"
                                                    title="Delete"
                                                >
                                                    <Trash2 className="h-3.5 w-3.5" />
                                                </button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </CardContent>

                    {/* Pagination Footer */}
                    {paginationLinks.length > 3 && (
                        <CardFooter className="flex flex-col items-center justify-between gap-4 border-t border-[#CFE3FF] pt-4 pb-4 sm:flex-row">
                            <div className="text-xs text-[#5C7A9E]">
                                Page{' '}
                                <span className="font-semibold text-[#0B3D91]">
                                    {currentPage}
                                </span>{' '}
                                of{' '}
                                <span className="font-semibold text-[#0B3D91]">
                                    {lastPage}
                                </span>
                            </div>

                            <Pagination className="mx-0 w-auto justify-end">
                                <PaginationContent className="gap-1">
                                    {paginationLinks.map((link, index) => {
                                        const isPrev = index === 0;
                                        const isNext =
                                            index ===
                                            paginationLinks.length - 1;
                                        const isEllipsis = link.label === '...';

                                        if (isPrev) {
                                            return (
                                                <PaginationItem key={index}>
                                                    <PaginationPrevious
                                                        href={link.url ?? '#'}
                                                        onClick={(e) => {
                                                            e.preventDefault();

                                                            if (link.url) {
                                                                router.get(
                                                                    link.url,
                                                                    {},
                                                                    {
                                                                        preserveState: true,
                                                                        preserveScroll: true,
                                                                    },
                                                                );
                                                            }
                                                        }}
                                                        className={
                                                            !link.url
                                                                ? 'pointer-events-none opacity-50'
                                                                : 'cursor-pointer'
                                                        }
                                                    />
                                                </PaginationItem>
                                            );
                                        }

                                        if (isNext) {
                                            return (
                                                <PaginationItem key={index}>
                                                    <PaginationNext
                                                        href={link.url ?? '#'}
                                                        onClick={(e) => {
                                                            e.preventDefault();

                                                            if (link.url) {
                                                                router.get(
                                                                    link.url,
                                                                    {},
                                                                    {
                                                                        preserveState: true,
                                                                        preserveScroll: true,
                                                                    },
                                                                );
                                                            }
                                                        }}
                                                        className={
                                                            !link.url
                                                                ? 'pointer-events-none opacity-50'
                                                                : 'cursor-pointer'
                                                        }
                                                    />
                                                </PaginationItem>
                                            );
                                        }

                                        if (isEllipsis) {
                                            return (
                                                <PaginationItem key={index}>
                                                    <PaginationEllipsis />
                                                </PaginationItem>
                                            );
                                        }

                                        return (
                                            <PaginationItem key={index}>
                                                <PaginationLink
                                                    href={link.url ?? '#'}
                                                    isActive={link.active}
                                                    onClick={(e) => {
                                                        e.preventDefault();

                                                        if (link.url) {
                                                            router.get(
                                                                link.url,
                                                                {},
                                                                {
                                                                    preserveState: true,
                                                                    preserveScroll: true,
                                                                },
                                                            );
                                                        }
                                                    }}
                                                    className={`cursor-pointer ${
                                                        link.active
                                                            ? 'bg-[#0F6FFF] text-white hover:bg-[#0B5DDB]'
                                                            : 'text-[#0B3D91]'
                                                    }`}
                                                >
                                                    {link.label}
                                                </PaginationLink>
                                            </PaginationItem>
                                        );
                                    })}
                                </PaginationContent>
                            </Pagination>
                        </CardFooter>
                    )}
                </Card>
            </div>
        </div>
    );
}
