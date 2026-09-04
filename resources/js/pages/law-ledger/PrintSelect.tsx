import { Head, router } from '@inertiajs/react';
import { Printer, ArrowLeft, Search, X, Filter } from 'lucide-react';
import React, { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';

interface StudentItem {
    id: string | number;
    full_name: string;
}

interface LawLedgerRecord {
    id: number;
    name: string;
    course: string | null;
    schoolYear: string | null;
    semesterOrSummer: string | null;
    transactionDate: string | null;
    referenceNo: string | null;
    particulars: string | null;
    arOrPayment: string | null;
    amount: number | string | null;
}

interface PrintSelectProps {
    students: StudentItem[];
    selectedStudent: string | number | null;
    records: LawLedgerRecord[];
}

function currency(n: number) {
    return `₱${(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function absAmount(val: unknown): number {
    if (!val) {
        return 0;
    }

    const num = parseFloat(String(val).replace(/[^\d.]/g, ''));

    return isNaN(num) ? 0 : num;
}

function formatTransactionDate(value?: string | null) {
    if (!value) {
        return '-';
    }

    const normalized = String(value).trim();

    if (!normalized) {
        return '-';
    }

    const datePart = normalized.includes('T')
        ? normalized.split('T')[0]
        : normalized.split(' ')[0];
    const parsedDate = new Date(`${datePart}T00:00:00`);

    if (Number.isNaN(parsedDate.getTime())) {
        return datePart;
    }

    return parsedDate.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
    });
}

function transactionTimestamp(value?: string | null) {
    if (!value) {
        return 0;
    }

    const timestamp = new Date(value).getTime();

    return Number.isNaN(timestamp) ? 0 : timestamp;
}

export default function PrintSelect({
    students = [],
    selectedStudent,
    records = [],
}: PrintSelectProps) {
    const [selected, setSelected] = useState<string | number>(
        selectedStudent || '',
    );
    const [search, setSearch] = useState('');
    const [schoolYearFilter, setSchoolYearFilter] = useState('all');
    const [semesterFilter, setSemesterFilter] = useState('all');
    const [typeFilter, setTypeFilter] = useState('all');
    const [sortOrder, setSortOrder] = useState<'latest' | 'oldest'>('latest');

    const isNumericId =
        typeof selected === 'number' ||
        (typeof selected === 'string' && /^\d+$/.test(selected));

    const filteredStudents = useMemo(() => {
        const term = search.trim().toLowerCase();

        if (!term) {
            return students;
        }

        const matched = students.filter((s) =>
            s.full_name.toLowerCase().includes(term),
        );

        return matched.sort((a, b) => {
            const aStarts = a.full_name.toLowerCase().startsWith(term) ? 0 : 1;
            const bStarts = b.full_name.toLowerCase().startsWith(term) ? 0 : 1;

            return aStarts - bStarts;
        });
    }, [students, search]);

    const schoolYears = useMemo(
        () =>
            Array.from(
                new Set(
                    records
                        .map((record) => record.schoolYear)
                        .filter((value): value is string => Boolean(value)),
                ),
            ).sort((a, b) => b.localeCompare(a, undefined, { numeric: true })),
        [records],
    );

    const semesters = useMemo(
        () =>
            Array.from(
                new Set(
                    records
                        .map((record) => record.semesterOrSummer)
                        .filter((value): value is string => Boolean(value)),
                ),
            ).sort((a, b) => a.localeCompare(b, undefined, { numeric: true })),
        [records],
    );

    const recordTypes = useMemo(
        () =>
            Array.from(
                new Set(
                    records
                        .map((record) => record.arOrPayment)
                        .filter((value): value is string => Boolean(value)),
                ),
            ).sort(),
        [records],
    );

    const filteredRecords = useMemo(() => {
        return records
            .filter(
                (record) =>
                    (schoolYearFilter === 'all' ||
                        record.schoolYear === schoolYearFilter) &&
                    (semesterFilter === 'all' ||
                        record.semesterOrSummer === semesterFilter) &&
                    (typeFilter === 'all' || record.arOrPayment === typeFilter),
            )
            .sort((a, b) => {
                const dateDifference =
                    transactionTimestamp(b.transactionDate) -
                    transactionTimestamp(a.transactionDate);
                const latestDifference = dateDifference || b.id - a.id;

                return sortOrder === 'latest'
                    ? latestDifference
                    : -latestDifference;
            });
    }, [records, schoolYearFilter, semesterFilter, typeFilter, sortOrder]);

    const summary = useMemo(() => {
        let totalCharges = 0;
        let totalPayments = 0;

        filteredRecords.forEach((r) => {
            const amount = absAmount(r.amount);
            if (r.arOrPayment === 'AR') {
                totalCharges += amount;
            } else {
                totalPayments += amount;
            }
        });

        return {
            totalCharges,
            totalPayments,
            outstandingBalance: totalCharges - totalPayments,
        };
    }, [filteredRecords]);

    const hasActiveFilters =
        schoolYearFilter !== 'all' ||
        semesterFilter !== 'all' ||
        typeFilter !== 'all' ||
        sortOrder !== 'latest';

    const resetRecordFilters = () => {
        setSchoolYearFilter('all');
        setSemesterFilter('all');
        setTypeFilter('all');
        setSortOrder('latest');
    };

    const handleSelect = (idOrName: string | number) => {
        setSelected(idOrName);
        resetRecordFilters();

        const params: Record<string, any> = {};

        if (typeof idOrName === 'number' || /^\d+$/.test(String(idOrName))) {
            params.student_id = idOrName;
        } else {
            params.student = idOrName;
        }

        router.get('/law-ledger/print-select', params, {
            preserveState: true,
        });
    };

    const handleStudentSelect = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const val = e.target.value;
        const numVal = Number(val);
        handleSelect(isNaN(numVal) || !val ? val : numVal);
    };

    const handleOpenPdf = () => {
        if (!selected) {
            return;
        }

        const queryKey = isNumericId ? 'student_id' : 'student';
        window.open(
            `/law-ledger/pdf?${queryKey}=${encodeURIComponent(selected)}`,
            '_blank',
        );
    };

    return (
        <div className="min-h-full bg-[#FAFAF5] p-4 md:p-8">
            <Head title="Print Student Statement" />

            <div className="mx-auto max-w-5xl space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between border-b border-[#CFE3FF] pb-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => router.get('/law-ledger')}
                                className="border-[#CFE3FF] text-[#0B3D91]"
                            >
                                <ArrowLeft className="mr-1 h-4 w-4" /> Back
                            </Button>
                            <h1 className="text-2xl font-bold text-[#0B3D91]">
                                Student Statement Printer
                            </h1>
                        </div>
                        <p className="mt-1 text-sm text-[#5C7A9E]">
                            Select a law school student to review their
                            transaction breakdown and print a formal SOA PDF.
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                    {/* Left: Interactive List Search */}
                    <Card className="border-[#CFE3FF] bg-white md:col-span-1">
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm font-semibold text-[#0B3D91]">
                                Search Student
                            </CardTitle>
                            <CardDescription className="text-xs text-[#7FA6D6]">
                                Click a name to preview records
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="relative">
                                <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-[#8AA8CC]" />
                                <Input
                                    type="text"
                                    placeholder="Type to filter list..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="pr-8 pl-9"
                                />
                                {search && (
                                    <button
                                        onClick={() => setSearch('')}
                                        className="absolute top-2.5 right-2.5 text-[#8AA8CC] hover:text-[#0B3D91]"
                                    >
                                        <X className="h-4 w-4" />
                                    </button>
                                )}
                            </div>

                            {/* Dropdown for Mobile */}
                            <div className="block md:hidden">
                                <select
                                    value={String(selected)}
                                    onChange={handleStudentSelect}
                                    className="w-full rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm text-[#334E68] focus:ring-2 focus:ring-[#0F6FFF] focus:outline-none"
                                >
                                    <option value="">
                                        -- Choose Student --
                                    </option>
                                    {filteredStudents.map((s) => (
                                        <option key={s.id} value={String(s.id)}>
                                            {s.full_name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* List for Desktop */}
                            <div className="hidden max-h-[380px] overflow-y-auto rounded-md border border-[#EAF2FF] md:block">
                                {filteredStudents.length === 0 ? (
                                    <p className="p-4 text-center text-xs text-[#8AA8CC]">
                                        No students found.
                                    </p>
                                ) : (
                                    <div className="divide-y divide-[#EAF2FF]">
                                        {filteredStudents.map((s) => {
                                            const isActive =
                                                String(selected) ===
                                                String(s.id);

                                            return (
                                                <button
                                                    key={s.id}
                                                    type="button"
                                                    onClick={() =>
                                                        handleSelect(s.id)
                                                    }
                                                    className={`w-full px-3 py-2 text-left text-xs transition-colors hover:bg-[#F3F8FF] ${
                                                        isActive
                                                            ? 'bg-[#EAF2FF] font-medium text-[#0B3D91]'
                                                            : 'text-[#334E68]'
                                                    }`}
                                                >
                                                    {s.full_name}
                                                </button>
                                            );
                                        })}
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Right: Detailed SOA preview */}
                    <div className="space-y-6 md:col-span-2">
                        {selected ? (
                            <div className="space-y-6">
                                {/* Selected Student Header */}
                                <Card className="border-[#CFE3FF] bg-white">
                                    <CardHeader className="flex flex-row items-center justify-between pb-3">
                                        <div>
                                            <CardTitle className="text-lg font-bold text-[#0B3D91]">
                                                {records[0]?.name ||
                                                    'Student Record'}
                                            </CardTitle>
                                            <CardDescription className="text-xs text-[#7FA6D6]">
                                                Showing {filteredRecords.length}{' '}
                                                of {records.length} transactions
                                            </CardDescription>
                                        </div>
                                        <Button
                                            onClick={handleOpenPdf}
                                            className="bg-[#0F6FFF] text-white hover:bg-[#0B5DDB]"
                                            size="sm"
                                        >
                                            <Printer className="mr-1.5 h-4 w-4" />{' '}
                                            Print Statement
                                        </Button>
                                    </CardHeader>
                                </Card>

                                {/* Stats Row */}
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <Card className="border-[#CFE3FF] bg-white p-4">
                                        <p className="text-xs text-[#5C7A9E]">
                                            Total Billed Charges (AR)
                                        </p>
                                        <h3 className="mt-1 text-base font-bold text-[#0B3D91]">
                                            {currency(summary.totalCharges)}
                                        </h3>
                                    </Card>
                                    <Card className="border-[#CFE3FF] bg-white p-4">
                                        <p className="text-xs text-[#5C7A9E]">
                                            Total Payments Received
                                        </p>
                                        <h3 className="mt-1 text-base font-bold text-emerald-600">
                                            {currency(summary.totalPayments)}
                                        </h3>
                                    </Card>
                                    <Card className="border-[#CFE3FF] bg-white p-4">
                                        <p className="text-xs text-[#5C7A9E]">
                                            Outstanding Balance
                                        </p>
                                        <h3 className="mt-1 text-base font-bold text-[#0B3D91]">
                                            {currency(
                                                summary.outstandingBalance,
                                            )}
                                        </h3>
                                    </Card>
                                </div>

                                <Card className="border-[#CFE3FF] bg-white">
                                    <CardContent className="pt-6">
                                        {/* Transaction Filters */}
                                        <div className="mb-5 rounded-lg border border-[#EAF2FF] bg-[#F7FAFE] p-4">
                                            <div className="mb-3 flex items-center justify-between gap-3">
                                                <div className="flex items-center gap-2 text-sm font-semibold text-[#0B3D91]">
                                                    <Filter className="h-4 w-4" />
                                                    Transaction Filters
                                                </div>
                                                {hasActiveFilters && (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={
                                                            resetRecordFilters
                                                        }
                                                        className="h-7 text-xs text-[#0F6FFF]"
                                                    >
                                                        Reset
                                                    </Button>
                                                )}
                                            </div>
                                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                                <label className="space-y-1 text-xs text-[#5C7A9E]">
                                                    <span>School Year</span>
                                                    <select
                                                        value={schoolYearFilter}
                                                        onChange={(event) =>
                                                            setSchoolYearFilter(
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        className="w-full rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm text-[#334E68] focus:ring-2 focus:ring-[#0F6FFF] focus:outline-none"
                                                    >
                                                        <option value="all">
                                                            All school years
                                                        </option>
                                                        {schoolYears.map(
                                                            (schoolYear) => (
                                                                <option
                                                                    key={
                                                                        schoolYear
                                                                    }
                                                                    value={
                                                                        schoolYear
                                                                    }
                                                                >
                                                                    {schoolYear}
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>
                                                </label>
                                                <label className="space-y-1 text-xs text-[#5C7A9E]">
                                                    <span>Term</span>
                                                    <select
                                                        value={semesterFilter}
                                                        onChange={(event) =>
                                                            setSemesterFilter(
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        className="w-full rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm text-[#334E68] focus:ring-2 focus:ring-[#0F6FFF] focus:outline-none"
                                                    >
                                                        <option value="all">
                                                            All terms
                                                        </option>
                                                        {semesters.map(
                                                            (semester) => (
                                                                <option
                                                                    key={
                                                                        semester
                                                                    }
                                                                    value={
                                                                        semester
                                                                    }
                                                                >
                                                                    {semester}
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>
                                                </label>
                                                <label className="space-y-1 text-xs text-[#5C7A9E]">
                                                    <span>Type</span>
                                                    <select
                                                        value={typeFilter}
                                                        onChange={(event) =>
                                                            setTypeFilter(
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        className="w-full rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm text-[#334E68] focus:ring-2 focus:ring-[#0F6FFF] focus:outline-none"
                                                    >
                                                        <option value="all">
                                                            All types
                                                        </option>
                                                        {recordTypes.map(
                                                            (recordType) => (
                                                                <option
                                                                    key={
                                                                        recordType
                                                                    }
                                                                    value={
                                                                        recordType
                                                                    }
                                                                >
                                                                    {recordType}
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>
                                                </label>
                                                <label className="space-y-1 text-xs text-[#5C7A9E]">
                                                    <span>Order</span>
                                                    <select
                                                        value={sortOrder}
                                                        onChange={(event) =>
                                                            setSortOrder(
                                                                event.target
                                                                    .value as
                                                                    | 'latest'
                                                                    | 'oldest',
                                                            )
                                                        }
                                                        className="w-full rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm text-[#334E68] focus:ring-2 focus:ring-[#0F6FFF] focus:outline-none"
                                                    >
                                                        <option value="latest">
                                                            Latest first
                                                        </option>
                                                        <option value="oldest">
                                                            Oldest first
                                                        </option>
                                                    </select>
                                                </label>
                                            </div>
                                        </div>

                                        <div className="overflow-x-auto">
                                            <table className="w-full border-collapse text-left text-xs">
                                                <thead>
                                                    <tr className="border-b border-[#CFE3FF] bg-[#F7FAFE] font-semibold text-[#0B3D91]">
                                                        <th className="px-3 py-3">
                                                            Date
                                                        </th>
                                                        <th className="px-3 py-3">
                                                            S.Y. / Term
                                                        </th>
                                                        <th className="px-3 py-3">
                                                            Ref / OR #
                                                        </th>
                                                        <th className="px-3 py-3">
                                                            Particulars
                                                        </th>
                                                        <th className="px-3 py-3">
                                                            Type
                                                        </th>
                                                        <th className="px-3 py-3 text-right">
                                                            Amount
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {filteredRecords.length ===
                                                    0 ? (
                                                        <tr>
                                                            <td
                                                                colSpan={6}
                                                                className="py-6 text-center text-[#8AA8CC]"
                                                            >
                                                                No transactions
                                                                match the
                                                                selected
                                                                filters.
                                                            </td>
                                                        </tr>
                                                    ) : (
                                                        filteredRecords.map(
                                                            (r) => (
                                                                <tr
                                                                    key={r.id}
                                                                    className="border-b border-[#EAF2FF] hover:bg-[#F3F8FF]"
                                                                >
                                                                    <td className="px-3 py-2 text-[#334E68]">
                                                                        {formatTransactionDate(
                                                                            r.transactionDate,
                                                                        )}
                                                                    </td>
                                                                    <td className="px-3 py-2 text-[#334E68]">
                                                                        {
                                                                            r.schoolYear
                                                                        }{' '}
                                                                        (
                                                                        {r.semesterOrSummer ??
                                                                            '-'}
                                                                        )
                                                                    </td>
                                                                    <td className="px-3 py-2 text-[#334E68]">
                                                                        {r.referenceNo ||
                                                                            '-'}
                                                                    </td>
                                                                    <td className="px-3 py-2 text-[#334E68]">
                                                                        {r.particulars ||
                                                                            '-'}
                                                                    </td>
                                                                    <td className="px-3 py-2">
                                                                        <Badge
                                                                            variant={
                                                                                r.arOrPayment ===
                                                                                'AR'
                                                                                    ? 'outline'
                                                                                    : r.arOrPayment ===
                                                                                        'Payment'
                                                                                      ? 'secondary'
                                                                                      : 'destructive'
                                                                            }
                                                                            className="text-[10px]"
                                                                        >
                                                                            {
                                                                                r.arOrPayment
                                                                            }
                                                                        </Badge>
                                                                    </td>
                                                                    <td className="px-3 py-2 text-right font-medium text-[#0B3D91]">
                                                                        {currency(
                                                                            absAmount(
                                                                                r.amount,
                                                                            ),
                                                                        )}
                                                                    </td>
                                                                </tr>
                                                            ),
                                                        )
                                                    )}
                                                </tbody>
                                            </table>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        ) : (
                            <Card className="flex flex-col items-center justify-center border-dashed border-[#CFE3FF] bg-white p-12 text-center">
                                <Printer className="mb-4 h-12 w-12 text-[#8AA8CC]" />
                                <h3 className="text-lg font-semibold text-[#0B3D91]">
                                    No Student Selected
                                </h3>
                                <p className="mt-1 max-w-sm text-sm text-[#7FA6D6]">
                                    Choose a law school student from the left
                                    panel to preview their statement and
                                    generate a print-ready SOA PDF.
                                </p>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
