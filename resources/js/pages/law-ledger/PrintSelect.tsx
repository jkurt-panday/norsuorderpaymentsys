import { Head, router } from '@inertiajs/react';
import { Printer, ArrowLeft, Search, X } from 'lucide-react';
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
    summary: {
        totalCharges: number;
        totalPayments: number;
        outstandingBalance: number;
    };
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

export default function PrintSelect({
    students = [],
    selectedStudent,
    records = [],
    summary,
}: PrintSelectProps) {
    const [selected, setSelected] = useState<string | number>(selectedStudent || '');
    const [search, setSearch] = useState('');

    const isNumericId = typeof selected === 'number' || (typeof selected === 'string' && /^\d+$/.test(selected));

    const filteredStudents = useMemo(() => {
        const term = search.trim().toLowerCase();

        if (!term) {
            return students;
        }
        const matched = students.filter((s) => s.full_name.toLowerCase().includes(term));
        // starts-with results float to top, contains-only results follow
        return matched.sort((a, b) => {
            const aStarts = a.full_name.toLowerCase().startsWith(term) ? 0 : 1;
            const bStarts = b.full_name.toLowerCase().startsWith(term) ? 0 : 1;
            return aStarts - bStarts;
        });
    }, [students, search]);

    const handleSelect = (idOrName: string | number) => {
        setSelected(idOrName);

        const params: Record<string, any> = {};
        if (typeof idOrName === 'number' || /^\d+$/.test(String(idOrName))) {
            params.student_id = idOrName;
        } else {
            params.student = idOrName;
        }

        router.get(
            '/law-ledger/print-select',
            params,
            { preserveState: true },
        );
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
                            Select a law school student to review their transaction breakdown and print a formal SOA PDF.
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
                                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-[#8AA8CC]" />
                                <Input
                                    type="text"
                                    placeholder="Type to filter list..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="pl-9 pr-8"
                                />
                                {search && (
                                    <button
                                        onClick={() => setSearch('')}
                                        className="absolute right-2.5 top-2.5 text-[#8AA8CC] hover:text-[#0B3D91]"
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
                                    <option value="">-- Choose Student --</option>
                                    {filteredStudents.map((s) => (
                                        <option key={s.id} value={String(s.id)}>
                                            {s.full_name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* List for Desktop */}
                            <div className="hidden max-h-[380px] overflow-y-auto border border-[#EAF2FF] rounded-md md:block">
                                {filteredStudents.length === 0 ? (
                                    <p className="p-4 text-center text-xs text-[#8AA8CC]">
                                        No students found.
                                    </p>
                                ) : (
                                    <div className="divide-y divide-[#EAF2FF]">
                                        {filteredStudents.map((s) => {
                                            const isActive = String(selected) === String(s.id);
                                            return (
                                                <button
                                                    key={s.id}
                                                    type="button"
                                                    onClick={() => handleSelect(s.id)}
                                                    className={`w-full text-left px-3 py-2 text-xs transition-colors hover:bg-[#F3F8FF] ${
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
                    <div className="md:col-span-2 space-y-6">
                        {selected ? (
                            <div className="space-y-6">
                                {/* Stats Row */}
                                <div className="grid grid-cols-3 gap-4">
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
                                            {currency(summary.outstandingBalance)}
                                        </h3>
                                    </Card>
                                </div>

                                <Card className="border-[#CFE3FF] bg-white">
                                    <CardHeader className="flex flex-row items-center justify-between pb-3">
                                        <div>
                                            <CardTitle className="text-lg font-bold text-[#0B3D91]">
                                                {records[0]?.name || 'Student Record'}
                                            </CardTitle>
                                            <CardDescription className="text-xs text-[#7FA6D6]">
                                                {records.length} transactions on ledger
                                            </CardDescription>
                                        </div>
                                        <Button
                                            onClick={handleOpenPdf}
                                            className="bg-[#0F6FFF] hover:bg-[#0B5DDB] text-white"
                                            size="sm"
                                        >
                                            <Printer className="mr-1.5 h-4 w-4" /> Print Statement
                                        </Button>
                                    </CardHeader>
                                    <CardContent className="overflow-x-auto">
                                        <table className="w-full border-collapse text-left text-xs">
                                            <thead>
                                                <tr className="border-b border-[#CFE3FF] bg-[#F7FAFE] text-[#0B3D91] font-semibold">
                                                    <th className="py-3 px-3">Date</th>
                                                    <th className="py-3 px-3">S.Y. / Term</th>
                                                    <th className="py-3 px-3">Ref / OR #</th>
                                                    <th className="py-3 px-3">Particulars</th>
                                                    <th className="py-3 px-3">Type</th>
                                                    <th className="py-3 px-3 text-right">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {records.length === 0 ? (
                                                    <tr>
                                                        <td colSpan={6} className="text-center py-6 text-[#8AA8CC]">
                                                            No records found for this student.
                                                        </td>
                                                    </tr>
                                                ) : (
                                                    records.map((r) => (
                                                        <tr key={r.id} className="border-b border-[#EAF2FF] hover:bg-[#F3F8FF]">
                                                            <td className="py-2 px-3 text-[#334E68]">
                                                                {formatTransactionDate(r.transactionDate)}
                                                            </td>
                                                            <td className="py-2 px-3 text-[#334E68]">
                                                                {r.schoolYear} ({r.semesterOrSummer ?? '-'})
                                                            </td>
                                                            <td className="py-2 px-3 text-[#334E68]">
                                                                {r.referenceNo || '-'}
                                                            </td>
                                                            <td className="py-2 px-3 text-[#334E68]">
                                                                {r.particulars || '-'}
                                                            </td>
                                                            <td className="py-2 px-3">
                                                                <Badge
                                                                    variant={
                                                                        r.arOrPayment === 'AR'
                                                                            ? 'outline'
                                                                            : r.arOrPayment === 'Payment'
                                                                                ? 'secondary'
                                                                                : 'destructive'
                                                                    }
                                                                    className="text-[10px]"
                                                                >
                                                                    {r.arOrPayment}
                                                                </Badge>
                                                            </td>
                                                            <td className="py-2 px-3 text-right font-medium text-[#0B3D91]">
                                                                {currency(absAmount(r.amount))}
                                                            </td>
                                                        </tr>
                                                    ))
                                                )}
                                            </tbody>
                                        </table>
                                    </CardContent>
                                </Card>
                            </div>
                        ) : (
                            <Card className="border-dashed border-[#CFE3FF] bg-white flex flex-col items-center justify-center p-12 text-center">
                                <Printer className="h-12 w-12 text-[#8AA8CC] mb-4" />
                                <h3 className="text-lg font-semibold text-[#0B3D91]">
                                    No Student Selected
                                </h3>
                                <p className="text-sm text-[#7FA6D6] mt-1 max-w-sm">
                                    Choose a law school student from the left panel to preview their statement and generate a print-ready SOA PDF.
                                </p>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
