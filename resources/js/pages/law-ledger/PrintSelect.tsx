import { Head, router } from '@inertiajs/react';
import { Printer, ArrowLeft, Search, X } from 'lucide-react';
import React, { useState } from 'react';

import {
<<<<<<< HEAD
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
    students?: StudentItem[];
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
=======
  CardDescription,
  CardHeader,
  CardTitle,
  CardFooter,
  CardContent,
  Card,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

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
    totalAssessments?: number;
    totalPayments?: number;
    outstandingBalance?: number;
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

  const datePart = normalized.includes('T') ? normalized.split('T')[0] : normalized.split(' ')[0];
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
}: PrintSelectProps) {
  const [selectedStudentState, setSelectedStudentState] = useState(selectedStudent || '');

  const handleStudentChange = (value: string | null) => {
    setSelectedStudentState(value ?? '');

    if (value) {
      router.get(`/law-ledger/print-select?student=${encodeURIComponent(value)}`);
>>>>>>> 1c0c01473aaa7337a8c0deb7fca3a03823e4c703
    }

    const num = parseFloat(String(val).replace(/[^\d.]/g, ''));

    return isNaN(num) ? 0 : num;
}

function formatTransactionDate(value?: string | null) {
    if (!value) {
        return '-';
    }

<<<<<<< HEAD
    const normalized = String(value).trim();
=======
  return (
    <div className="min-h-full bg-[#FAFAF5] p-4 md:p-8">
      <Head title="Print Student Statement" />
>>>>>>> 1c0c01473aaa7337a8c0deb7fca3a03823e4c703

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
    selectedStudent,
    records = [],
    summary,
}: PrintSelectProps) {
    const [selected, setSelected] = useState<string | number>(selectedStudent || '');
    const [search, setSearch] = useState('');
    const [searchResults, setSearchResults] = useState<StudentItem[]>([]);
    const [isSearching, setIsSearching] = useState(false);
    const [hasSearched, setHasSearched] = useState(false);

    const isNumericId = typeof selected === 'number' || (typeof selected === 'string' && /^\d+$/.test(selected));

    const fetchStudents = async (query: string) => {
        setIsSearching(true);

        try {
            const res = await fetch(`/law-ledger/students/search?q=${encodeURIComponent(query)}&limit=50`);
            const data: string[] = await res.json();
            setSearchResults(
                data.map((name) => ({
                    id: name,
                    full_name: name,
                })),
            );
        } catch {
            setSearchResults([]);
        } finally {
            setIsSearching(false);
            setHasSearched(true);
        }
    };

    React.useEffect(() => {
        const timer = setTimeout(() => {
            fetchStudents(search.trim());
        }, 150);

        return () => clearTimeout(timer);
    }, [search]);

    const handleSearchFocus = () => {
        if (!hasSearched && !search.trim()) {
            fetchStudents('');
        }
    };

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
                                    placeholder="Type to search students..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    onFocus={handleSearchFocus}
                                    className="pl-9 pr-8"
                                />
                                {search && (
                                    <button
                                        onClick={() => {
                                            setSearch('');
                                            setSearchResults([]);
                                        }}
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
                                    {searchResults.map((s) => (
                                        <option key={s.id} value={String(s.id)}>
                                            {s.full_name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* List for Desktop */}
                            <div className="hidden max-h-[380px] overflow-y-auto border border-[#EAF2FF] rounded-md md:block">
                                {isSearching ? (
                                    <p className="p-4 text-center text-xs text-[#8AA8CC]">
                                        Searching...
                                    </p>
                                ) : searchResults.length === 0 ? (
                                    <p className="p-4 text-center text-xs text-[#8AA8CC]">
                                        {search ? 'No students found.' : 'Type a name to search for students.'}
                                    </p>
                                ) : (
                                    <div className="divide-y divide-[#EAF2FF]">
                                        {searchResults.map((s) => {
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
<<<<<<< HEAD
    );
}
=======

        {/* Student Selection */}
        <Card className="border-slate-200 shadow-sm bg-white">
          <CardHeader>
            <CardTitle className="text-base font-semibold text-slate-900 flex items-center gap-2">
              <FileText className="h-4 w-4 text-blue-600" />
              Select Student
            </CardTitle>
            <CardDescription className="text-slate-500">
              Choose a student to view and print their statement of account
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="flex flex-col sm:flex-row items-start sm:items-center gap-4">
              <div className="flex-1 w-full sm:w-auto">
                <Select value={selectedStudentState} onValueChange={handleStudentChange}>
                  <SelectTrigger className="h-10 bg-white border-slate-200 w-full sm:w-96">
                    <SelectValue placeholder="Select a student" />
                  </SelectTrigger>
                  <SelectContent>
                    {students.map((student) => (
                      <SelectItem key={student} value={student}>
                        {student}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <Button
                onClick={handleGeneratePdf}
                disabled={!selectedStudentState}
                className="h-10 bg-blue-600 hover:bg-blue-700 text-white shadow-sm w-full sm:w-auto"
              >
                <Printer className="h-4 w-4 mr-2" />
                Generate PDF
              </Button>
            </div>
          </CardContent>
        </Card>

        {/* Student Records and Summary */}
        {selectedStudentState && records && records.length > 0 && (
          <>
            {/* Summary Cards */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <Card className="border-slate-200 shadow-sm bg-white">
                <CardHeader className="pb-2">
                  <CardTitle className="text-sm font-medium text-slate-600">Total Assessments</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-bold text-slate-900">
                    {currency(
                      records
                        .filter((record) => (record.arOrPayment?.toUpperCase() === 'AR' || record.arOrPayment?.toUpperCase() === 'ASSESSMENT'))
                        .reduce((sum, record) => sum + absAmount(record.amount), 0)
                    )}
                  </div>
                </CardContent>
              </Card>

              <Card className="border-slate-200 shadow-sm bg-white">
                <CardHeader className="pb-2">
                  <CardTitle className="text-sm font-medium text-slate-600">Total Payments</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-bold text-slate-900">
                    {currency(
                      records
                        .filter((record) => record.arOrPayment?.toUpperCase() === 'PAYMENT')
                        .reduce((sum, record) => sum + absAmount(record.amount), 0)
                    )}
                  </div>
                </CardContent>
              </Card>

              <Card className="border-slate-200 shadow-sm bg-white">
                <CardHeader className="pb-2">
                  <CardTitle className="text-sm font-medium text-slate-600">Outstanding Balance</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-bold text-slate-900">
                    {currency(
                      records
                        .filter((record) => (record.arOrPayment?.toUpperCase() === 'AR' || record.arOrPayment?.toUpperCase() === 'ASSESSMENT'))
                        .reduce((sum, record) => sum + absAmount(record.amount), 0) -
                      records
                        .filter((record) => record.arOrPayment?.toUpperCase() === 'PAYMENT')
                        .reduce((sum, record) => sum + absAmount(record.amount), 0)
                    )}
                  </div>
                </CardContent>
              </Card>
            </div>

            {/* Student Ledger Table */}
            <Card className="border-slate-200 shadow-sm bg-white">
              <CardHeader className="flex flex-row items-start justify-between space-y-0">
                <div>
                  <CardTitle className="text-base font-semibold text-slate-900">
                    {selectedStudentState}
                  </CardTitle>
                  <CardDescription className="text-slate-500 mt-1">
                    Showing {records.length} transaction{records.length === 1 ? '' : 's'}
                  </CardDescription>
                </div>
                {records[0]?.course && (
                  <Badge variant="outline" className="bg-slate-100 text-slate-700 border-slate-300 font-semibold px-3 py-1 text-sm">
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
                        <TableHead>School Year</TableHead>
                        <TableHead>Reference/JEV No.</TableHead>
                        <TableHead>Particulars</TableHead>
                        <TableHead>AR/Payment</TableHead>
                        <TableHead className="text-right">Amount</TableHead>
                        <TableHead>Status</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {records.map((record) => (
                        <TableRow key={record.id} className="hover:bg-slate-50 transition-colors">
                          <TableCell>{formatDate(record.transactionDate)}</TableCell>
                          <TableCell>{record.schoolYear ? `${record.schoolYear}${record.semesterOrSummer ? ` (${record.semesterOrSummer})` : ''}` : '-'}</TableCell>
                          <TableCell className="font-mono text-sm">{record.referenceNo || '-'}</TableCell>
                          <TableCell>{record.particulars || '-'}</TableCell>
                          <TableCell>
                            <Badge
                              variant="outline"
                              className={`${getTransactionTypeBadgeClass(record.arOrPayment)} border text-xs`}
                            >
                              {record.arOrPayment || '-'}
                            </Badge>
                          </TableCell>
                          <TableCell className="text-right font-medium">{currency(record.amount)}</TableCell>
                          <TableCell>
                            <Badge
                              variant={getStatusBadgeVariant(record.status)}
                              className={`${getStatusBadgeClass(record.status)} border text-xs`}
                            >
                              {record.status || '-'}
                            </Badge>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
              </CardContent>
            </Card>
          </>
        )}

        {selectedStudentState && (!records || records.length === 0) && (
          <Card className="border-slate-200 shadow-sm bg-white">
            <CardContent className="text-center py-12">
              <FileText className="h-12 w-12 text-slate-300 mx-auto mb-3" />
              <p className="text-sm font-medium text-slate-600">No records found</p>
              <p className="text-xs text-slate-400 mt-1">
                This student has no transactions on record
              </p>
            </CardContent>
          </Card>
        )}
      </div>
    </div>
  );
}
>>>>>>> 1c0c01473aaa7337a8c0deb7fca3a03823e4c703
