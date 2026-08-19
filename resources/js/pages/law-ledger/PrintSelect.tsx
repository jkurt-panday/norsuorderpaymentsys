import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    FileText,
    Printer,
    Search,
    X,
} from 'lucide-react';
import React, { useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { CalendarField } from '@/components/ui/calendar';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Combobox,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
} from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
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

interface LawLedgerRecord {
    id: string | number;
    transactionDate?: string | null;
    schoolYear?: string | null;
    semesterOrSummer?: string | null;
    referenceNo?: string | null;
    particulars?: string | null;
    arOrPayment: string;
    amount: number;
    status: string;
    remark?: string;
    inputBy?: string;
    course?: string;
}

interface PrintSelectProps {
    students: string[];
    selectedStudent: string | null;
    records?: LawLedgerRecord[];
    summary?: {
        totalCharges?: number;
        totalPayments?: number;
        outstandingBalance?: number;
    };
    filterOptions?: {
        courses: string[];
        schoolYears: string[];
        semesters: string[];
        statuses: string[];
        types?: string[];
    };
}

function currency(n: number): string {
    return `₱${(n ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function absAmount(val: unknown): number {
    if (!val) {
        return 0;
    }

    const num = parseFloat(String(val).replace(/[^\d.]/g, ''));

    return isNaN(num) ? 0 : num;
}

function formatDate(value?: string | null): string {
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

    return parsedDate.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function getStatusBadgeVariant(status: string) {
    const statusUpper = status?.toUpperCase() || '';

    if (statusUpper === 'PAID' || statusUpper === 'SETTLED') {
        return 'default';
    }

    if (statusUpper === 'PENDING') {
        return 'secondary';
    }

    if (statusUpper === 'OVERDUE') {
        return 'destructive';
    }

    if (statusUpper === 'PARTIAL PAYMENT') {
        return 'outline';
    }

    return 'outline';
}

function getStatusBadgeClass(status: string): string {
    const statusUpper = status?.toUpperCase() || '';

    if (statusUpper === 'PAID' || statusUpper === 'SETTLED') {
        return 'bg-green-100 text-green-700 border-green-200 hover:bg-green-100';
    }

    if (statusUpper === 'PENDING') {
        return 'bg-yellow-100 text-yellow-700 border-yellow-200 hover:bg-yellow-100';
    }

    if (statusUpper === 'OVERDUE') {
        return 'bg-red-100 text-red-700 border-red-200 hover:bg-red-100';
    }

    if (statusUpper === 'PARTIAL PAYMENT') {
        return 'bg-blue-100 text-blue-700 border-blue-200 hover:bg-blue-100';
    }

    return 'bg-gray-100 text-gray-700 border-gray-200 hover:bg-gray-100';
}

function getTransactionTypeBadgeClass(type: string): string {
    const typeUpper = type?.toUpperCase() || '';

    if (typeUpper === 'AR' || typeUpper === 'ASSESSMENT') {
        return 'bg-purple-100 text-purple-700 border-purple-200';
    }

    if (typeUpper === 'PAYMENT') {
        return 'bg-green-100 text-green-700 border-green-200';
    }

    return 'bg-gray-100 text-gray-700 border-gray-200';
}

export default function PrintSelect({
    students,
    selectedStudent,
    records = [],
    summary,
    filterOptions,
}: PrintSelectProps) {
    const [selectedStudentState, setSelectedStudentState] = useState(
        selectedStudent || '',
    );
    const [searchQuery, setSearchQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [typeFilter, setTypeFilter] = useState('');
    const [dateFromFilter, setDateFromFilter] = useState('');
    const [dateToFilter, setDateToFilter] = useState('');

    const handleStudentChange = (value: string | null) => {
        setSelectedStudentState(value ?? '');

        if (value) {
            router.get(
                `/law-ledger/print-select?student=${encodeURIComponent(value)}`,
            );
        } else {
            router.get('/law-ledger/print-select');
        }
    };

    const handleGeneratePdf = () => {
        if (selectedStudentState) {
            window.open(
                `/law-ledger/pdf?student=${encodeURIComponent(selectedStudentState)}`,
                '_blank',
            );
        }
    };

    const clearFilters = () => {
        setSearchQuery('');
        setStatusFilter('');
        setTypeFilter('');
        setDateFromFilter('');
        setDateToFilter('');
    };

    const hasActiveFilters =
        Boolean(searchQuery) ||
        Boolean(statusFilter) ||
        Boolean(typeFilter) ||
        Boolean(dateFromFilter) ||
        Boolean(dateToFilter);

    const filteredRecords = records.filter((record) => {
        const searchableFields = [
            record.transactionDate,
            record.schoolYear,
            record.semesterOrSummer,
            record.referenceNo,
            record.particulars,
            record.arOrPayment,
            record.course,
            record.status,
            record.remark,
            record.inputBy,
        ];

        const searchLower = searchQuery.toLowerCase();
        const searchMatch =
            !searchQuery ||
            searchableFields.some((field) =>
                (field ?? '').toLowerCase().includes(searchLower),
            );

        if (!searchMatch) {
            return false;
        }

        const statusMatch =
            !statusFilter ||
            (record.status ?? '').toLowerCase() === statusFilter.toLowerCase();

        const typeMatch =
            !typeFilter ||
            (record.arOrPayment ?? '').toLowerCase() ===
                typeFilter.toLowerCase();

        const dateMatch = (() => {
            if (!dateFromFilter && !dateToFilter) {
                return true;
            }

            const recDate = record.transactionDate
                ? new Date(record.transactionDate).getTime()
                : NaN;

            if (isNaN(recDate)) {
                return false;
            }

            if (
                dateFromFilter &&
                recDate < new Date(dateFromFilter).getTime()
            ) {
                return false;
            }

            if (dateToFilter && recDate > new Date(dateToFilter).getTime()) {
                return false;
            }

            return true;
        })();

        return statusMatch && typeMatch && dateMatch;
    });

    const isAssessment = (r: LawLedgerRecord) =>
        (r.arOrPayment ?? '').toUpperCase() === 'AR' ||
        (r.arOrPayment ?? '').toUpperCase() === 'ASSESSMENT';

    const totalAssessments =
        summary?.totalCharges ??
        records
            .filter(isAssessment)
            .reduce((sum, record) => sum + absAmount(record.amount), 0);

    const totalPayments =
        summary?.totalPayments ??
        records
            .filter(
                (record) =>
                    (record.arOrPayment ?? '').toUpperCase() === 'PAYMENT',
            )
            .reduce((sum, record) => sum + absAmount(record.amount), 0);

    const outstandingBalance =
        summary?.outstandingBalance ?? totalAssessments - totalPayments;

    return (
        <div className="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50/30 p-4 md:p-6 lg:p-8">
            <Head title="Print Statement - Law School Ledger" />

            <div className="mx-auto max-w-5xl space-y-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => router.get('/law-ledger')}
                        className="h-9"
                    >
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        Back to Ledger
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-slate-900">
                            Print Statement of Account
                        </h1>
                        <p className="mt-0.5 text-sm text-slate-500">
                            Generate PDF statements for students
                        </p>
                    </div>
                </div>

                {/* Student Selection */}
                <Card className="border-slate-200 bg-white shadow-sm">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base font-semibold text-slate-900">
                            <FileText className="h-4 w-4 text-blue-600" />
                            Select Student
                        </CardTitle>
                        <CardDescription className="text-slate-500">
                            Choose a student to view and print their statement
                            of account
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-col items-start gap-4 sm:flex-row sm:items-center">
                            <div className="w-full flex-1 sm:w-auto">
                                <Combobox
                                    items={students}
                                    value={selectedStudentState}
                                    onValueChange={handleStudentChange}
                                >
                                    <ComboboxInput
                                        placeholder="Search or select a student..."
                                        className="h-10 w-full border-slate-200 bg-white sm:w-96"
                                        showClear={!!selectedStudentState}
                                    />
                                    <ComboboxContent>
                                        <ComboboxEmpty>
                                            No students found.
                                        </ComboboxEmpty>
                                        <ComboboxList>
                                            {(student) => (
                                                <ComboboxItem
                                                    key={student}
                                                    value={student}
                                                >
                                                    {student}
                                                </ComboboxItem>
                                            )}
                                        </ComboboxList>
                                    </ComboboxContent>
                                </Combobox>
                            </div>

                            <Button
                                onClick={handleGeneratePdf}
                                disabled={!selectedStudentState}
                                className="h-10 w-full bg-blue-600 text-white shadow-sm hover:bg-blue-700 sm:w-auto"
                            >
                                <Printer className="mr-2 h-4 w-4" />
                                Generate PDF
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {/* Student Records and Summary */}
                {selectedStudentState && records && records.length > 0 && (
                    <>
                        {/* Summary Cards */}
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <Card className="border-slate-200 bg-white shadow-sm">
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-sm font-medium text-slate-600">
                                        Total Assessments
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold text-slate-900">
                                        {currency(totalAssessments)}
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="border-slate-200 bg-white shadow-sm">
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-sm font-medium text-slate-600">
                                        Total Payments
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold text-slate-900">
                                        {currency(totalPayments)}
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="border-slate-200 bg-white shadow-sm">
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-sm font-medium text-slate-600">
                                        Outstanding Balance
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold text-slate-900">
                                        {currency(outstandingBalance)}
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Student Ledger Table */}
                        <Card className="border-slate-200 bg-white shadow-sm">
                            <CardHeader className="flex flex-row items-start justify-between space-y-0">
                                <div className="flex flex-col gap-4">
                                    <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                                        <div className="space-y-1">
                                            <CardTitle className="text-base font-semibold text-slate-900">
                                                {selectedStudentState}
                                            </CardTitle>
                                            <CardDescription className="text-slate-500">
                                                Showing {filteredRecords.length}{' '}
                                                of {records.length} transaction
                                                {records.length === 1
                                                    ? ''
                                                    : 's'}
                                            </CardDescription>
                                        </div>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-3">
                                        <div className="relative w-full sm:w-72">
                                            <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-slate-400" />
                                            <Input
                                                placeholder="Search by date, ref #, particulars, AR/payment, status..."
                                                className="h-9 border-slate-200 bg-slate-50 pr-8 pl-9 text-sm"
                                                value={searchQuery}
                                                onChange={(e) =>
                                                    setSearchQuery(
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                            {searchQuery && (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setSearchQuery('')
                                                    }
                                                    className="absolute top-1/2 right-2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                                                    aria-label="Clear search"
                                                >
                                                    <X className="h-3.5 w-3.5" />
                                                </button>
                                            )}
                                        </div>

                                        <Select
                                            value={statusFilter}
                                            onValueChange={(value) =>
                                                setStatusFilter(value ?? '')
                                            }
                                        >
                                            <SelectTrigger className="h-9 w-full border-slate-200 bg-slate-50 text-sm sm:w-44">
                                                <SelectValue placeholder="All Statuses" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="">
                                                    All Statuses
                                                </SelectItem>
                                                {(
                                                    filterOptions?.statuses ??
                                                    []
                                                ).map((st) => (
                                                    <SelectItem
                                                        key={st}
                                                        value={st}
                                                    >
                                                        {st}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>

                                        <Select
                                            value={typeFilter}
                                            onValueChange={(value) =>
                                                setTypeFilter(value ?? '')
                                            }
                                        >
                                            <SelectTrigger className="h-9 w-full border-slate-200 bg-slate-50 text-sm sm:w-44">
                                                <SelectValue placeholder="All Types (AR/Payment)" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="">
                                                    All Types (AR/Payment)
                                                </SelectItem>
                                                {(
                                                    filterOptions?.types ?? [
                                                        'AR',
                                                        'Payment',
                                                        'Adjustment',
                                                    ]
                                                ).map((t) => (
                                                    <SelectItem
                                                        key={t}
                                                        value={t}
                                                    >
                                                        {t}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>

                                        <CalendarField
                                            label="From"
                                            value={
                                                dateFromFilter
                                                    ? new Date(
                                                          `${dateFromFilter}T00:00:00`,
                                                      )
                                                    : null
                                            }
                                            onSelect={(date) =>
                                                setDateFromFilter(
                                                    date
                                                        ? date
                                                              .toISOString()
                                                              .split('T')[0]
                                                        : '',
                                                )
                                            }
                                        />

                                        <CalendarField
                                            label="To"
                                            value={
                                                dateToFilter
                                                    ? new Date(
                                                          `${dateToFilter}T00:00:00`,
                                                      )
                                                    : null
                                            }
                                            onSelect={(date) =>
                                                setDateToFilter(
                                                    date
                                                        ? date
                                                              .toISOString()
                                                              .split('T')[0]
                                                        : '',
                                                )
                                            }
                                        />

                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={clearFilters}
                                            disabled={!hasActiveFilters}
                                            className="h-9 border-slate-200 text-slate-600 hover:bg-slate-100"
                                        >
                                            <X className="mr-1.5 h-4 w-4" />
                                            Clear Filters
                                        </Button>
                                    </div>
                                </div>
                                {records[0]?.course && (
                                    <Badge
                                        variant="outline"
                                        className="ml-4 border-slate-300 bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-700"
                                    >
                                        Course: {records[0].course}
                                    </Badge>
                                )}
                            </CardHeader>
                            <CardContent className="p-0">
                                <div className="overflow-x-auto">
                                    <Table>
                                        <TableHeader>
                                            <TableRow className="bg-slate-50 hover:bg-slate-50">
                                                <TableHead>Date</TableHead>
                                                <TableHead>
                                                    School Year
                                                </TableHead>
                                                <TableHead>
                                                    Reference/JEV No.
                                                </TableHead>
                                                <TableHead>
                                                    Particulars
                                                </TableHead>
                                                <TableHead>
                                                    AR/Payment
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    Amount
                                                </TableHead>
                                                <TableHead>Status</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {filteredRecords.length === 0 ? (
                                                <TableRow>
                                                    <TableCell
                                                        colSpan={7}
                                                        className="h-24"
                                                    >
                                                        <div className="flex flex-col items-center justify-center gap-3 text-center text-slate-500">
                                                            <Search className="h-8 w-8 text-slate-300" />
                                                            <div>
                                                                <p className="text-sm font-medium">
                                                                    No
                                                                    transactions
                                                                    match your
                                                                    filters
                                                                </p>
                                                                <p className="mt-0.5 text-xs text-slate-400">
                                                                    Try
                                                                    adjusting
                                                                    your search
                                                                    or filter
                                                                    criteria
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            ) : (
                                                filteredRecords.map(
                                                    (record) => (
                                                        <TableRow
                                                            key={record.id}
                                                            className="transition-colors hover:bg-slate-50"
                                                        >
                                                            <TableCell>
                                                                {formatDate(
                                                                    record.transactionDate,
                                                                )}
                                                            </TableCell>
                                                            <TableCell>
                                                                {record.schoolYear
                                                                    ? `${record.schoolYear}${record.semesterOrSummer ? ` (${record.semesterOrSummer})` : ''}`
                                                                    : '-'}
                                                            </TableCell>
                                                            <TableCell className="font-mono text-sm">
                                                                {record.referenceNo ||
                                                                    '-'}
                                                            </TableCell>
                                                            <TableCell>
                                                                {record.particulars ||
                                                                    '-'}
                                                            </TableCell>
                                                            <TableCell>
                                                                <Badge
                                                                    variant="outline"
                                                                    className={`${getTransactionTypeBadgeClass(record.arOrPayment)} border text-xs`}
                                                                >
                                                                    {record.arOrPayment ||
                                                                        '-'}
                                                                </Badge>
                                                            </TableCell>
                                                            <TableCell className="text-right font-medium">
                                                                {currency(
                                                                    record.amount,
                                                                )}
                                                            </TableCell>
                                                            <TableCell>
                                                                <Badge
                                                                    variant={getStatusBadgeVariant(
                                                                        record.status,
                                                                    )}
                                                                    className={`${getStatusBadgeClass(record.status)} border text-xs`}
                                                                >
                                                                    {record.status ||
                                                                        '-'}
                                                                </Badge>
                                                            </TableCell>
                                                        </TableRow>
                                                    ),
                                                )
                                            )}
                                        </TableBody>
                                    </Table>
                                </div>
                            </CardContent>
                        </Card>
                    </>
                )}

                {selectedStudentState && (!records || records.length === 0) && (
                    <Card className="border-slate-200 bg-white shadow-sm">
                        <CardContent className="py-12 text-center">
                            <FileText className="mx-auto mb-3 h-12 w-12 text-slate-300" />
                            <p className="text-sm font-medium text-slate-600">
                                No records found
                            </p>
                            <p className="mt-1 text-xs text-slate-400">
                                This student has no transactions on record
                            </p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </div>
    );
}
