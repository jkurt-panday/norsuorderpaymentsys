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

interface LawLedgerRecord {
  id: number;
  lastName: string;
  firstName: string;
  middleInitial: string;
  name: string;
  course: string;
  schoolYear: string;
  semesterOrSummer: string;
  units: number;
  transactionDate: string;
  referenceNo: string;
  particulars: string;
  tuitionPerUnitOrFeePerSemester: number;
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
    totalAssessments: number;
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

function getStatusBadgeClass(status: string) {
  const statusUpper = status?.toUpperCase() || '';

  if (statusUpper === 'PAID' || statusUpper === 'SETTLED') {
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
            </div>
          </CardContent>
        </Card>

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
                </CardContent>
              </Card>
            </div>

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
        )}
      </div>
    </div>
  );
}
