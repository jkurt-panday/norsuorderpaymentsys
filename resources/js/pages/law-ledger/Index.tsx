<<<<<<< HEAD
import { Head, router, useForm } from '@inertiajs/react';
=======
import { Head, router } from '@inertiajs/react';
>>>>>>> origin
import {
  Search,
  DollarSign,
  GraduationCap,
  Wallet,
  AlertTriangle,
  PlusCircle,
  Scale,
  Pencil,
  Trash2,
} from 'lucide-react';
import React, { useState } from 'react';
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
import { Separator } from '@/components/ui/separator';

export interface LawLedgerRecord {
  id: string | number;
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

export interface LawLedgerPaginator {
  data: LawLedgerRecord[];
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
  const datePart = normalized.includes('T') ? normalized.split('T')[0] : normalized.split(' ')[0];
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

function statusBadgeVariant(status: string | null | undefined) {
  const s = (status ?? '').toLowerCase();

  if (s === 'paid' || s === 'settled') {
return 'bg-emerald-50 text-emerald-700 border-emerald-200';
}

  if (s === 'pending') {
return 'bg-amber-50 text-amber-700 border-amber-200';
}

  if (s === 'overdue') {
return 'bg-red-50 text-red-700 border-red-200';
}

  if (s === 'partial payment') {
return 'bg-blue-50 text-blue-700 border-blue-200';
}

  return 'bg-slate-50 text-slate-700 border-slate-200';
}

interface IndexProps {
  records?: LawLedgerPaginator;
  filters?: {
    search?: string;
    school_year?: string;
    semester_or_summer?: string;
    course?: string;
    status?: string;
    date_from?: string;
    date_to?: string;
  };
  stats?: {
    totalStudents?: number;
<<<<<<< HEAD
    totalAssessments?: number;
=======
    totalUnits?: number;
    totalCharges?: number;
>>>>>>> origin
    totalPayments?: number;
    outstandingBalance?: number;
  };
  filterOptions?: {
    courses: string[];
    schoolYears: string[];
    semesters: string[];
    statuses: string[];
  };
}

export default function Index({ records, filters, stats, filterOptions }: IndexProps) {
  const rows: LawLedgerRecord[] = records?.data ?? [];
  const [searchQuery, setSearchQuery] = useState(filters?.search ?? '');
  const [schoolYear, setSchoolYear] = useState(filters?.school_year ?? '');
  const [semester, setSemester] = useState(filters?.semester_or_summer ?? '');
  const [course, setCourse] = useState(filters?.course ?? '');
  const [status, setStatus] = useState(filters?.status ?? '');
<<<<<<< HEAD
  const importForm = useForm<{ file: File | null }>({ file: null });
=======
>>>>>>> origin

  const applyFilters = (overrides: Record<string, string> = {}) => {
    const params: Record<string, string> = {};

    const current = {
      search: searchQuery,
      school_year: schoolYear,
      semester_or_summer: semester,
      course: course,
      status: status,
      ...overrides,
    };

    Object.entries(current).forEach(([key, value]) => {
      if (value && value.trim()) {
params[key] = value.trim();
}
    });

    router.get('/law-ledger', params, {
      preserveState: true,
      replace: true,
    });
  };

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    applyFilters();
  };

  // ---- Server-backed Metric Summary ----
  const totalStudents = stats?.totalStudents ?? 0;
<<<<<<< HEAD
  const totalAssessments = stats?.totalAssessments ?? 0;
=======
  const totalUnits = stats?.totalUnits ?? 0;
  const totalCharges = stats?.totalCharges ?? 0;
>>>>>>> origin
  const totalPayments = stats?.totalPayments ?? 0;
  const outstandingBalance = stats?.outstandingBalance ?? 0;

  const currentPage = records?.meta?.current_page ?? records?.current_page ?? 1;
  const lastPage = records?.meta?.last_page ?? records?.last_page ?? 1;
  const totalRecordCount = records?.meta?.total ?? records?.total ?? rows.length;

<<<<<<< HEAD
=======
  const [goToPage, setGoToPage] = React.useState<string>('');

>>>>>>> origin
  const paginationLinks = records?.links ?? [];

  return (
    <div className="min-h-screen bg-[#FAFAF5] p-4 md:p-8">
      <Head title="Law School Ledger" />

      <div className="max-w-7xl mx-auto space-y-6">

        {/* Top Header / Action Bar */}
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-[#CFE3FF] pb-5">
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-2xl font-bold tracking-tight text-[#0B3D91]">Law School Ledger</h1>
              <Badge variant="outline" className="bg-[#EAF2FF] text-[#0B62E0] border-[#B9D8FF] font-semibold">
                <Scale className="h-3 w-3 mr-1" />
                Law School
              </Badge>
            </div>
            <p className="text-sm text-[#5C7A9E] mt-0.5">Tuition, fees, and payment transactions for law school students.</p>
          </div>

          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-2 w-full md:w-auto">
            <form onSubmit={handleSearchSubmit} className="flex flex-wrap items-center gap-2 w-full sm:w-auto">
              <div className="relative w-full sm:w-64">
                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-[#7FA6D6]" />
                <Input
                  type="search"
                  placeholder="Search name, ID, or ref #..."
                  className="pl-8 h-9 bg-white border-[#CFE3FF] focus-visible:ring-[#0F6FFF]"
                  value={searchQuery}
                  onChange={(e) => {
                    const nextValue = e.target.value;
                    setSearchQuery(nextValue);
                    applyFilters({ search: nextValue });
                  }}
                />
              </div>

              <select
                value={schoolYear}
                onChange={(e) => {
                  const v = e.target.value;
                  setSchoolYear(v);
                  applyFilters({ school_year: v });
                }}
                className="h-9 rounded-md border border-[#CFE3FF] bg-white px-3 text-sm text-[#0B3D91]"
              >
                <option value="">All School Years</option>
                {(filterOptions?.schoolYears ?? []).map((sy) => (
                  <option key={sy} value={sy}>{sy}</option>
                ))}
              </select>

              <select
                value={semester}
                onChange={(e) => {
                  const v = e.target.value;
                  setSemester(v);
                  applyFilters({ semester_or_summer: v });
                }}
                className="h-9 rounded-md border border-[#CFE3FF] bg-white px-3 text-sm text-[#0B3D91]"
              >
                <option value="">All Semesters</option>
                {(filterOptions?.semesters ?? []).map((s) => (
                  <option key={s} value={s}>{s}</option>
                ))}
              </select>

              <select
                value={course}
                onChange={(e) => {
                  const v = e.target.value;
                  setCourse(v);
                  applyFilters({ course: v });
                }}
                className="h-9 rounded-md border border-[#CFE3FF] bg-white px-3 text-sm text-[#0B3D91]"
              >
                <option value="">All Courses</option>
                {(filterOptions?.courses ?? []).map((c) => (
                  <option key={c} value={c}>{c}</option>
                ))}
              </select>

              <select
                value={status}
                onChange={(e) => {
                  const v = e.target.value;
                  setStatus(v);
                  applyFilters({ status: v });
                }}
                className="h-9 rounded-md border border-[#CFE3FF] bg-white px-3 text-sm text-[#0B3D91]"
              >
                <option value="">All Statuses</option>
                {(filterOptions?.statuses ?? []).map((st) => (
                  <option key={st} value={st}>{st}</option>
                ))}
              </select>

            </form>

            <div className="flex flex-wrap items-center justify-end gap-2 ml-auto">
              <label className="inline-flex cursor-pointer items-center rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm font-medium text-[#0B3D91] hover:bg-[#F3F8FF] transition-colors">
                <input
                  type="file"
                  accept=".csv,.xlsx,.xls"
                  className="hidden"
                  onChange={(e) => {
                    const file = e.target.files?.[0] ?? null;
<<<<<<< HEAD
                    importForm.setData('file', file);

                    if (file) {
                      importForm.post('/law-ledger/import', {
                        forceFormData: true,
                        preserveScroll: true,
                        onSuccess: () => {
                          importForm.reset('file');
=======

                    if (file) {
                      const form = new FormData();
                      form.append('file', file);

                      router.post('/law-ledger/import', form, {
                        forceFormData: true,
                        preserveScroll: true,
                        preserveState: false,
                        onSuccess: () => {
>>>>>>> origin
                          e.currentTarget.value = '';
                        },
                      });
                    }
                  }}
                />
                Import Excel/CSV
              </label>

              <Button className="bg-[#0F6FFF] hover:bg-[#0B5DDB] text-white" onClick={() => router.get('/law-ledger/add')}>
                <PlusCircle className="h-4 w-4 mr-1.5" />
                New Transaction
              </Button>
            </div>
          </div>
        </div>

        {/* Metrics Row */}
<<<<<<< HEAD
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
=======
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
>>>>>>> origin
          <Card className="shadow-xs border border-[#CFE3FF] bg-white">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium text-[#5C7A9E]">Students on Ledger</CardTitle>
              <GraduationCap className="h-4 w-4 text-[#0F6FFF]" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold tracking-tight text-[#0B3D91]">{totalStudents}</div>
              <p className="text-[10px] text-[#8AA8CC] mt-1">Unique law school students</p>
            </CardContent>
          </Card>

          <Card className="shadow-xs border border-[#CFE3FF] bg-white">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
<<<<<<< HEAD
              <CardTitle className="text-sm font-medium text-[#5C7A9E]">Total Assessments</CardTitle>
              <DollarSign className="h-4 w-4 text-[#0F6FFF]" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold tracking-tight text-[#0B3D91]">{currency(totalAssessments)}</div>
              <p className="text-[10px] text-[#8AA8CC] mt-1">Total tuition + fees billed</p>
=======
              <CardTitle className="text-sm font-medium text-[#5C7A9E]">Total Units</CardTitle>
              <Scale className="h-4 w-4 text-[#0F6FFF]" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold tracking-tight text-[#0B3D91]">{totalUnits.toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}</div>
              <p className="text-[10px] text-[#8AA8CC] mt-1">Total enrolled units</p>
            </CardContent>
          </Card>

          <Card className="shadow-xs border border-[#CFE3FF] bg-white">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium text-[#5C7A9E]">Total Charges (AR)</CardTitle>
              <DollarSign className="h-4 w-4 text-[#0F6FFF]" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold tracking-tight text-[#0B3D91]">{currency(totalCharges)}</div>
              <p className="text-[10px] text-[#8AA8CC] mt-1">Total charges billed</p>
>>>>>>> origin
            </CardContent>
          </Card>

          <Card className="shadow-xs border border-[#CFE3FF] bg-white">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium text-[#5C7A9E]">Total Payments</CardTitle>
              <Wallet className="h-4 w-4 text-[#0F6FFF]" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold tracking-tight text-[#0B3D91]">{currency(totalPayments)}</div>
              <p className="text-[10px] text-[#8AA8CC] mt-1">Total payments received</p>
            </CardContent>
          </Card>

          <Card className="shadow-xs border border-[#CFE3FF] bg-white">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium text-[#5C7A9E]">Outstanding Balance</CardTitle>
              <AlertTriangle className="h-4 w-4 text-orange-500" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold tracking-tight text-[#0B3D91]">{currency(outstandingBalance)}</div>
              <p className="text-[10px] text-[#8AA8CC] mt-1">Net pending balance</p>
            </CardContent>
          </Card>
        </div>

        {/* Summary Analytics */}
        <Card className="border border-[#CFE3FF] bg-white">
          <CardContent className="pt-6">
            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <span className="text-sm text-slate-600">Outstanding Balance</span>
                <AlertTriangle className="h-4 w-4 text-orange-500" />
              </div>
              <p className="text-xl font-bold text-slate-900">{currency(outstandingBalance)}</p>
              <div className="h-2 bg-orange-100 rounded-full overflow-hidden">
                <div 
                  className="h-full bg-orange-500 rounded-full transition-all duration-500"
                  style={{ 
<<<<<<< HEAD
                    width: `${totalAssessments > 0 ? Math.min((outstandingBalance / totalAssessments) * 100, 100) : 0}%` 
=======
                    width: `${totalCharges > 0 ? Math.min(Math.max(0, (outstandingBalance / totalCharges) * 100), 100) : 0}%` 
>>>>>>> origin
                  }}
                />
              </div>
            </div>

            <Separator className="my-6" />

            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div className="p-4 bg-white rounded-lg border border-slate-200">
                <div className="flex items-center justify-between">
                  <span className="text-sm text-slate-600">Collection Rate</span>
                  <span className="text-sm font-medium text-slate-900">
<<<<<<< HEAD
                    {totalAssessments > 0 
                      ? `${((totalPayments / totalAssessments) * 100).toFixed(1)}%` 
=======
                    {totalCharges > 0 
                      ? `${((totalPayments / totalCharges) * 100).toFixed(1)}%` 
>>>>>>> origin
                      : '0.0%'}
                  </span>
                </div>
                <div className="mt-2 h-2 bg-slate-100 rounded-full overflow-hidden">
                  <div 
                    className="h-full bg-blue-500 rounded-full transition-all duration-500"
                    style={{ 
<<<<<<< HEAD
                      width: `${totalAssessments > 0 ? Math.min((totalPayments / totalAssessments) * 100, 100) : 0}%` 
=======
                      width: `${totalCharges > 0 ? Math.min((totalPayments / totalCharges) * 100, 100) : 0}%` 
>>>>>>> origin
                    }}
                  />
                </div>
              </div>

              <div className="p-4 bg-white rounded-lg border border-slate-200">
                <div className="flex items-center justify-between">
                  <span className="text-sm text-slate-600">Average Transaction</span>
                  <span className="text-sm font-medium text-slate-900">
                    {totalRecordCount > 0 
<<<<<<< HEAD
                      ? currency((totalAssessments + totalPayments) / totalRecordCount)
=======
                      ? currency((totalCharges + totalPayments) / totalRecordCount)
>>>>>>> origin
                      : '₱0.00'}
                  </span>
                </div>
                <p className="text-xs text-slate-500 mt-1">Per record</p>
              </div>

              <div className="p-4 bg-white rounded-lg border border-slate-200">
                <div className="flex items-center justify-between">
<<<<<<< HEAD
                  <span className="text-sm text-slate-600">Records This Page</span>
                  <span className="text-sm font-medium text-slate-900">{rows.length}</span>
                </div>
                <p className="text-xs text-slate-500 mt-1">of {totalRecordCount.toLocaleString()} total</p>
=======
                  <span className="text-sm text-slate-600">Total Units Enrolled</span>
                  <span className="text-sm font-medium text-slate-900">{totalUnits.toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}</span>
                </div>
                <p className="text-xs text-slate-500 mt-1">Across all records</p>
>>>>>>> origin
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Ledger Table with Pagination */}
        <Card className="border border-[#CFE3FF] bg-white">
          <CardHeader className="pb-2">
            <CardTitle className="text-md text-[#0B3D91]">Transaction Ledger</CardTitle>
            <CardDescription className="text-[#7FA6D6]">
              Showing {rows.length} of {totalRecordCount} record{totalRecordCount === 1 ? '' : 's'}
            </CardDescription>
          </CardHeader>
          <CardContent className="overflow-x-auto">
            <table className="w-full text-sm border-collapse">
              <thead>
                <tr className="border-b border-[#CFE3FF] bg-[#F3F8FF]">
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 pl-2 whitespace-nowrap">Name</th>
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Course</th>
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">School Year</th>
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Semester/Summer</th>
                  <th className="text-right font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Units</th>
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Trans. Date</th>
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Ref. (JEV/OR #)</th>
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Particulars</th>
                  <th className="text-right font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Tuition/Unit or Reg. & Misc. Fee</th>
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">AR/Payment</th>
                  <th className="text-right font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Amount</th>
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Status</th>
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Remark</th>
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Input By</th>
                  <th className="py-2 pr-2 text-center font-medium whitespace-nowrap text-[#5C7A9E]">Actions</th>
                </tr>
              </thead>
              <tbody>
                {rows.length === 0 ? (
                  <tr>
                    <td colSpan={15} className="text-center text-sm text-[#8AA8CC] py-8">
                      No transactions found. Upload a CSV/Excel file or add one manually.
                    </td>
                  </tr>
                ) : (
                  rows.map((r) => (
                    <tr key={r.id} className="border-b border-[#EAF2FF] hover:bg-[#F3F8FF]">
                      <td className="py-2 pr-4 pl-2 font-medium whitespace-nowrap text-[#0B3D91]">{r.name}</td>
                      <td className="py-2 pr-4 text-[#334E68]">{r.course}</td>
                      <td className="py-2 pr-4 text-[#334E68]">{r.schoolYear}</td>
                      <td className="py-2 pr-4 text-[#334E68]">{r.semesterOrSummer}</td>
                      <td className="py-2 pr-4 text-right text-[#334E68]">{r.units}</td>
                      <td className="py-2 pr-4 whitespace-nowrap text-[#334E68]">{formatTransactionDate(r.transactionDate)}</td>
                      <td className="py-2 pr-4 whitespace-nowrap text-[#334E68]">{r.referenceNo}</td>
                      <td className="py-2 pr-4 text-[#334E68]">{r.particulars}</td>
                      <td className="py-2 pr-4 text-right text-[#334E68]">{currency(r.tuitionPerUnitOrFeePerSemester)}</td>
                      <td className="py-2 pr-4">
                        <Badge variant="outline" className="border-[#B9D8FF] text-[#0B62E0] bg-[#EAF2FF]">
                          {r.arOrPayment}
                        </Badge>
                      </td>
                      <td className="py-2 pr-4 text-right font-medium text-[#0B3D91]">{currency(r.amount)}</td>
                      <td className="py-2 pr-4">
                        <Badge variant="outline" className={statusBadgeVariant(r.status)}>
                          {r.status}
                        </Badge>
                      </td>
                      <td className="py-2 pr-4 text-[#8AA8CC]">{r.remark}</td>
                      <td className="py-2 pr-4 text-[#8AA8CC]">{r.inputBy}</td>
                      <td className="py-2 pr-2 text-center whitespace-nowrap">
                        <button
                          onClick={() =>
                            router.get(
                              `/law-ledger/${r.id}/edit`,
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

          {/* ---- Pagination Footer ---- */}
          {paginationLinks.length > 3 && (
            <CardFooter className="flex flex-col sm:flex-row items-center justify-between border-t border-[#CFE3FF] pt-4 pb-4 gap-4">
              <div className="text-xs text-[#5C7A9E]">
                Page <span className="font-semibold text-[#0B3D91]">{currentPage}</span> of{' '}
                <span className="font-semibold text-[#0B3D91]">{lastPage}</span>
              </div>

<<<<<<< HEAD
              <Pagination className="justify-end w-auto mx-0">
                <PaginationContent className="gap-1">
=======
               <Pagination className="justify-end w-auto mx-0">
                 <PaginationContent className="gap-1">
                   <PaginationItem>
                     <div className="flex items-center gap-1">
                       <Input
                         type="number"
                         min="1"
                         max={lastPage}
                         value={goToPage}
                         onChange={(e) => {
                           setGoToPage(e.target.value);
                         }}
                          onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                              const page = parseInt(goToPage);

                              if (!isNaN(page)) {
                                const validatedPage = Math.min(Math.max(page, 1), lastPage);
                                router.get(`/law-ledger?page=${validatedPage}`, {}, {
                                  preserveState: true,
                                  preserveScroll: true,
                                });
                                setGoToPage('');
                              }
                            }
                          }}
                         placeholder={currentPage.toString()}
                         className="w-16 h-8 text-center border border-[#CFE3FF] rounded-md focus:ring-[#0F6FFF] focus:border-[#0F6FFF]"
                       />
                       <Button
                         variant="outline"
                         size="sm"
                          onClick={() => {
                            const page = parseInt(goToPage);

                            if (!isNaN(page)) {
                              const validatedPage = Math.min(Math.max(page, 1), lastPage);
                              router.get(`/law-ledger?page=${validatedPage}`, {}, {
                                preserveState: true,
                                preserveScroll: true,
                              });
                              setGoToPage('');
                            }
                          }}
                         className="h-8 px-2 text-xs bg-[#0F6FFF] text-white hover:bg-[#0B5DDB]"
                       >
                         Go
                       </Button>
                     </div>
                   </PaginationItem>
>>>>>>> origin
                  {paginationLinks.map((link, index) => {
                    const isPrev = index === 0;
                    const isNext = index === paginationLinks.length - 1;
                    const isEllipsis = link.label === '...';

                    if (isPrev) {
                      return (
                        <PaginationItem key={index}>
                          <PaginationPrevious
                            href={link.url ?? '#'}
                            onClick={(e) => {
                              e.preventDefault();

                              if (link.url) {
router.get(link.url, {}, { preserveState: true, preserveScroll: true });
}
                            }}
                            className={!link.url ? 'pointer-events-none opacity-50' : 'cursor-pointer'}
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
router.get(link.url, {}, { preserveState: true, preserveScroll: true });
}
                            }}
                            className={!link.url ? 'pointer-events-none opacity-50' : 'cursor-pointer'}
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
router.get(link.url, {}, { preserveState: true, preserveScroll: true });
}
                          }}
                          className={`cursor-pointer ${
                            link.active ? 'bg-[#0F6FFF] text-white hover:bg-[#0B5DDB]' : 'text-[#0B3D91]'
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

function handleDelete(id: string | number, name: string) {
  if (confirm(`Delete transaction for ${name}?`)) {
    router.delete(`/law-ledger/${id}`);
  }
}
