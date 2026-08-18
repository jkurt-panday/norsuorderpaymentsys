import { Head, router } from '@inertiajs/react';
<<<<<<< HEAD
import { Printer, ArrowLeft, Search, X } from 'lucide-react';
import React, { useMemo, useState } from 'react';
=======
import { ArrowLeft, FileText, Printer } from 'lucide-react';
import React, { useState } from 'react';
>>>>>>> origin
ate } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  C<<<<<<< HEAD
  CardHeader,
  CardTitle,
  CardDescription,
} from '@/components/ui/card';
=======
  CardDescription,
  CardHeader,
  CardTitle,
  CardFooter,
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
>>>>>>> origin
mber;
  arOrPayment: string;
  amount: number;
  status: string;
  remark: string;
  inputBy: string;
}

interface PrintSelectProps {
  students: string[];
  selectedStudent: string | null;
  records?: LawLedgerRecord[];
  summary?: {
    totalCharges: number;
    totalPayments: number;
    outstandingBalance: number;
  };
}

function currency(n: number) {
  return `₱${(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function<<<<<<< HEAD
  selectedStudent: string | null;
=======
  selectedStudent?: string;
>>>>>>> origin
mber {
  if (!val) {
return 0;
}

  const num = parseFloat(String(val).replace(/[^\d.]/g, ''));

  return isNaN(num) ? 0 : num;
}

function formatTransactionDate(value?: str<<<<<<< HEAD
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
=======
  return `₱${(n ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function formatDate(value?: string | null) {
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
>>>>>>> origin
on getStatusBadgeClass(status: string) {
  const statusUpper = status?.toUpperCase() || '';

  if (statusUpper === 'PAID'<<<<<<< HEAD
=======
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

>>>>>>> origin
slate-200 bg-slate-50 text-slate-700';
}

export default function PrintSelect({
  students,
  selectedStudent,
  records = [],
  summary,
}: PrintSelectProps) {<<<<<<< HEAD
    return 'border-emerald-200 bg-emerald-50 text-emerald-700';
  }

  if (statusUpper === 'PENDING') {
    return 'border-amber-200 bg-amber-50 text-amber-700';
  }

  if (statusUpper === 'OVERDUE') {
    return 'border-red-200 bg-red-50 text-red-700';
  }

  if (statusUpper === 'PARTIAL PAYMENT') {
    return 'border-blue-200 bg-blue-50 text-blue-700';
  }

  return 'border-slate-200 bg-slate-50 text-slate-700';
}

export default function PrintSelect({
  students,
  selectedStudent,
  records = [],
  summary,
}: PrintSelectProps) {
  const [selected, setSelected] = useState(selectedStudent || '');
  const [search, setSearch] = useState('');

  const filteredStudents = useMemo(() => {
    const term = search.trim().toLowerCase();

    if (!term) {
      return students;
    }

    return students.filter((name) => name.toLowerCase().includes(term));
  }, [students, search]);

  const handleSelect = (name: string) => {
    setSelected(name);
    router.get(
      '/law-ledger/print-select',
      { student: name },
      { preserveState: true },
    );
  };

  const handleStudentSelect = (e: React.ChangeEvent<HTMLSelectElement>) => {
    handleSelect(e.target.value);
  };

  const handleOpenPdf = () => {
    if (!selected) {
return;
}

    window.open(
      `/law-ledger/pdf?student=${encodeURIComponent(selected)}`,
      '_blank',
    );
  };

  return (
    <div className="min-h-screen bg-[#FAFAF5] p-4 md:p-8">
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
              Select a law school student to review their transaction
              breakdown and print a formal SOA PDF.
            </p>
          </div>
        </div>

        {/* Selection Card with Searchable Combobox */}
        <Card className="overflow-visible border-[#CFE3FF] bg-white">
          <CardHeader>
            <CardTitle className="text-base text-[#0B3D91]">
              Select Law School Student
            </CardTitle>
            <CardDescription className="text-[#7FA6D6]">
              Type to search — {students.length} student
              {students.length === 1 ? '' : 's'} on record.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="space-y-3">
              {/* Search box */}
              <div className="relative w-full sm:w-2/3">
                <Search className="absolute top-1/2 left-2.5 h-4 w-4 -translate-y-1/2 text-[#7FA6D6]" />
                <input
                  type="text"
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  placeholder="Type a name to filter the list below..."
                  className="w-full rounded-md border border-[#CFE3FF] bg-white py-2 pr-8 pl-8 text-sm focus:ring-2 focus:ring-[#0F6FFF] focus:outline-none"
                />
                {search && (
                  <button
                    type="button"
                    onClick={() => setSearch('')}
                    className="absolute top-1/2 right-2.5 -translate-y-1/2 text-[#7FA6D6] hover:text-[#0B3D91]"
                    aria-label="Clear search"
                  >
                    <X className="h-4 w-4" />
                  </button>
                )}
              </div>

              <div className="flex flex-col items-center gap-4 sm:flex-row">
                <select
                  className="w-full rounded-md border border-[#CFE3FF] bg-white p-2.5 text-sm focus:ring-2 focus:ring-[#0F6FFF] focus:outline-none sm:w-2/3"
                  value={selected}
                  onChange={handleStudentSelect}
                  size={
                    search
                      ? Math.min(
                          Math.max(filteredStudents.length, 1) + 1,
                          8,
                        )
                      : 1
                  }
                >
                  <option value="">
                    -- Select Student Name --
                  </option>
                  {filteredStudents.map((name) => (
                    <option key={name} value={name}>
                      {name}
                    </option>
                  ))}
                  {search && filteredStudents.length === 0 && (
                    <option value="" disabled>
                      No students match "{search}"
                    </option>
                  )}
                </select>

                <Button
                  disabled={!selected}
                  onClick={handleOpenPdf}
                  className="w-full bg-[#0F6FFF] text-white hover:bg-[#0B5DDB] sm:w-auto"
                >
                  <Printer className="mr-2 h-4 w-4" />
                  Print PDF Statement
                </Button>
              </div>

              {search && (
                <p className="text-xs text-[#7FA6D6]">
                  {filteredStudents.length} of {students.length} student
                  {students.length === 1 ? '' : 's'} match "{search}"
                </p>
              )}
=======
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

function getTransactionTypeBadgeClass(type: string) {
  const typeUpper = type?.toUpperCase() || '';

  if (typeUpper === 'AR' || typeUpper === 'ASSESSMENT') {
    return 'bg-purple-100 text-purple-700 border-purple-200';
  }

  if (typeUpper === 'PAYMENT') {
    return 'bg-green-100 text-green-700 border-green-200';
  }

  return 'bg-gray-100 text-gray-700 border-gray-200';
}

export default function PrintSelect({ students, selectedStudent, records, summary }: PrintSelectProps) {
  const [selectedStudentState, setSelectedStudentState] = useState(selectedStudent || '');

  const handleStudentChange = (value: string | null) => {
    setSelectedStudentState(value ?? '');

    if (value) {
      router.get(`/law-ledger/print-select?student=${encodeURIComponent(value)}`);
    }
  };

  const handleGeneratePdf = () => {
    if (selectedStudentState) {
      window.open(`/law-ledger/pdf?student=${encodeURIComponent(selectedStudentState)}`, '_blank');
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50/30 p-4 md:p-6 lg:p-8">
      <Head title="Print Statement - Law School Ledger" />

      <div className="max-w-5xl mx-auto space-y-6">
        {/* Header */}
        <div className="flex items-center gap-4">
          <Button
            variant="ghost"
            size="sm"
            onClick={() => router.get('/law-ledger')}
            className="h-9"
          >
            <ArrowLeft className="h-4 w-4 mr-2" />
            Back to Ledger
          </Button>
          <div>
            <h1 className="text-2xl font-bold tracking-tight text-slate-900">Print Statement of Account</h1>
            <p className="text-sm text-slate-500 mt-0.5">Generate PDF statements for students</p>
          </div>
        </div>

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
                    {students.map(student => (
                      <SelectItem key={student} value={student}>{student}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <Button
                onClick={handleGeneratePdf}
                disabled={!selectedStudentState}
                className="h-10 bg-blue-600 hover:bg-blue-700 text-white shadow-sm"
              >
                <Printer className="h-4 w-4 mr-2" />
                Generate PDF
              </Button>
>>>>>>> origin
t-[#7FA6D6]" />
                <input
                  type<<<<<<< HEAD
        {/* Student Breakdown */}
        {selected && (
          <div className="space-y-6">
            {/* Metric Overview */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
              <Card className="border-[#CFE3FF] bg-white">
                <CardHeader className="pb-2">
                  <CardTitle className="text-xs font-medium text-[#5C7A9E]">
                    Total Assessments (AR)
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="text-xl font-bold text-[#0B3D91]">
                    {currency(summary?.totalAssessments ?? 0)}
                  </div>
                </CardContent>
              </Card>

              <Card className="border-[#CFE3FF] bg-white">
                <CardHeader className="pb-2">
                  <CardTitle className="text-xs font-medium text-[#5C7A9E]">
                    Total Payments Received
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="text-xl font-bold text-emerald-600">
                    {currency(summary?.totalPayments ?? 0)}
                  </div>
                </CardContent>
              </Card>

              <Card className="border-[#CFE3FF] bg-white">
                <CardHeader className="pb-2">
                  <CardTitle className="text-xs font-medium text-[#5C7A9E]">
                    Current Outstanding Balance
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div
                    className={`text-xl font-bold ${(summary?.outstandingBalance ?? 0) > 0 ? 'text-amber-600' : 'text-[#0B3D91]'}`}
                  >
                    {currency(summary?.outstandingBalance ?? 0)}
                  </div>
=======
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
                  <div className="text-2xl font-bold text-slate-900">{currency(summary?.totalAssessments || 0)}</div>
                </CardContent>
              </Card>

              <Card className="border-slate-200 shadow-sm bg-white">
                <CardHeader className="pb-2">
                  <CardTitle className="text-sm font-medium text-slate-600">Total Payments</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-bold text-slate-900">{currency(summary?.totalPayments || 0)}</div>
                </CardContent>
              </Card>

              <Card className="border-slate-200 shadow-sm bg-white">
                <CardHeader className="pb-2">
                  <CardTitle className="text-sm font-medium text-slate-600">Outstanding Balance</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-bold text-slate-900">{currency(summary?.outstandingBalance || 0)}</div>
>>>>>>> origin
--
                   </option>
                   {displayStudents.map((<<<<<<< HEAD
            {/* Preview Table */}
            <Card className="border-[#CFE3FF] bg-white">
              <CardHeader className="border-b border-[#EAF2FF] pb-3">
                <div className="flex items-center justify-between">
                  <div>
                    <CardTitle className="text-base text-[#0B3D91]">
                      {selected}
                    </CardTitle>
                    <CardDescription className="text-xs text-[#7FA6D6]">
                      {records.length} total ledger entry/entries found
                    </CardDescription>
                  </div>
                  {records[0]?.course ? (
                    <Badge
                      variant="outline"
                      className="border-[#B9D8FF] bg-[#EAF2FF] text-[#0B62E0]"
                    >
                      {records[0].course}
                    </Badge>
                  ) : (
                    <Badge
                      variant="outline"
                      className="border-[#B9D8FF] bg-[#EAF2FF] text-[#0B62E0]"
                    >
                      Law School
                    </Badge>
                  )}
                </div>
              </CardHeader>
              <CardContent className="overflow-x-auto pt-4">
                <table className="w-full border-collapse text-sm">
                  <thead>
                    <tr className="border-b border-[#CFE3FF] bg-[#F3F8FF] text-left text-[#5C7A9E]">
                      <th className="px-3 py-2">Date</th>
                      <th className="px-3 py-2">S.Y. / Term</th>
                      <th className="px-3 py-2">Ref / JEV #</th>
                      <th className="px-3 py-2">Particulars</th>
                      <th className="px-3 py-2">Type</th>
                      <th className="px-3 py-2 text-right">Amount</th>
                      <th className="px-3 py-2">Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    {records.length === 0 ? (
                      <tr>
                        <td
                          colSpan={7}
                          className="py-6 text-center text-[#8AA8CC]"
                        >
                          No records found for this student.
                        </td>
                      </tr>
                    ) : (
                      records.map((r) => (
                        <tr
                          key={r.id}
                          className="border-b border-[#EAF2FF] hover:bg-[#F3F8FF]"
                        >
                          <td className="px-3 py-2 text-[#334E68]">
                            {formatTransactionDate(r.transactionDate)}
                          </td>
                          <td className="px-3 py-2 text-[#334E68]">
                            {r.schoolYear}
                            {r.semesterOrSummer
                              ? ` (${r.semesterOrSummer})`
                              : ''}
                          </td>
                          <td className="px-3 py-2 text-[#334E68]">
                            {r.referenceNo || '-'}
                          </td>
                          <td className="px-3 py-2 text-[#334E68]">
                            {r.particulars || '-'}
                          </td>
                          <td className="px-3 py-2 font-medium text-[#0B3D91]">
                            {r.arOrPayment}
                          </td>
                          <td className="px-3 py-2 text-right font-medium text-[#0B3D91]">
                            {currency(absAmount(r.amount))}
                          </td>
                          <td className="px-3 py-2">
                            <Badge
                              variant="outline"
                              className={getStatusBadgeClass(r.status)}
                            >
                              {r.status || '-'}
                            </Badge>
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </CardContent>
            </Card>
          </div>
=======
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
>>>>>>> origin
me="border-[#B9D8FF] bg-[#EAF2FF] text-[#0B62E0]"
                    >
                      {records[0].course}
                    </Badge>
                  ) : (
                    <Badge
                      variant="outline"
                      className="border-[#B9D8FF] bg-[#EAF2FF] text-[#0B62E0]"
                    >
                      Law School
                    </Badge>
                  )}
                </div>
              </CardHeader>
              <CardContent className="overflow-x-auto pt-4">
                <table className="w-full border-collapse text-sm">
                  <thead>
                    <tr className="border-b border-[#CFE3FF] bg-[#F3F8FF] text-left text-[#5C7A9E]">
                      <th className="px-3 py-2">Date</th>
                      <th className="px-3 py-2">S.Y. / Term</th>
                      <th className="px-3 py-2">Ref / JEV #</th>
                      <th className="px-3 py-2">Particulars</th>
                      <th className="px-3 py-2">Type</th>
                      <th className="px-3 py-2 text-right">Amount</th>
                      <th className="px-3 py-2">Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    {records.length === 0 ? (
                      <tr>
                        <td
                          colSpan={7}
                          className="py-6 text-center text-[#8AA8CC]"
                        >
                          No records found for this student.
                        </td>
                      </tr>
                    ) : (
                      records.map((r) => (
                        <tr
                          key={r.id}
                          className="border-b border-[#EAF2FF] hover:bg-[#F3F8FF]"
                        >
                          <td className="px-3 py-2 text-[#334E68]">
                            {formatTransactionDate(r.transactionDate)}
                          </td>
                          <td className="px-3 py-2 text-[#334E68]">
                            {r.schoolYear}
                            {r.semesterOrSummer
                              ? ` (${r.semesterOrSummer})`
                              : ''}
                          </td>
                          <td className="px-3 py-2 text-[#334E68]">
                            {r.referenceNo || '-'}
                          </td>
                          <td className="px-3 py-2 text-[#334E68]">
                            {r.particulars || '-'}
                          </td>
                          <td className="px-3 py-2 font-medium text-[#0B3D91]">
                            {r.arOrPayment}
                          </td>
                          <td className="px-3 py-2 text-right font-medium text-[#0B3D91]">
                            {currency(absAmount(r.amount))}
                          </td>
                          <td className="px-3 py-2">
                            <Badge
                              variant="outline"
                              className={getStatusBadgeClass(r.status)}
                            >
                              {r.status || '-'}
                            </Badge>
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </CardContent>
            </Card>
          </div>
        )}
      </div>
    </div>
  );
}
