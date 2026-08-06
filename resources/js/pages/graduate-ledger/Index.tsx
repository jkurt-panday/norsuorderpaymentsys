import { Head, router, useForm } from '@inertiajs/react';
import {
  Search,
  DollarSign,
  GraduationCap,
  Wallet,
  AlertTriangle,
  PlusCircle,
  Pencil,
  Trash2,
  XCircle,
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

export interface LedgerRecord {
  id: string | number;
  name: string;
  course: string;
  schoolYear: string;
  semester: string;
  units: number;
  transactionDate: string;
  referenceNo: string;
  particulars: string;
  tuitionPerUnitOrFeePerSemester: number;
  arPayment: string;
  amount: number;
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

interface IndexProps {
  records?: LedgerPaginator;
  filters?: {
    search?: string;
    school_year?: string;
    semester?: string;
    course?: string;
    date_from?: string;
    date_to?: string;
  };
  stats?: {
    totalStudents?: number;
    totalAssessments?: number;
    totalPayments?: number;
    outstandingBalance?: number;
  };
  filterOptions?: {
    courses: string[];
    schoolYears: string[];
    semesters: string[];
  };
}

export default function Index({ records, filters, stats, filterOptions }: IndexProps) {
  const rows: LedgerRecord[] = records?.data ?? [];
  const [searchQuery, setSearchQuery] = useState(filters?.search ?? '');
  const [schoolYear, setSchoolYear] = useState(filters?.school_year ?? '');
  const [semester, setSemester] = useState(filters?.semester ?? '');
  const [course, setCourse] = useState(filters?.course ?? '');
  const [dateFrom, setDateFrom] = useState(filters?.date_from ?? '');
  const [dateTo, setDateTo] = useState(filters?.date_to ?? '');
  const importForm = useForm<{ file: File | null }>({ file: null });

  const applyFilters = (overrides: Record<string, string> = {}) => {
    const params: Record<string, string> = {};

    const current = {
      search: searchQuery,
      school_year: schoolYear,
      semester: semester,
      course: course,
      date_from: dateFrom,
      date_to: dateTo,
      ...overrides,
    };

    Object.entries(current).forEach(([key, value]) => {
      if (value && value.trim()) {
        params[key] = value.trim();
      }
    });

    router.get('/graduate-ledger', params, {
      preserveState: true,
      replace: true,
    });
  };

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    applyFilters();
  };

  const totalStudents = stats?.totalStudents ?? 0;
  const totalAssessments = stats?.totalAssessments ?? 0;
  const totalPayments = stats?.totalPayments ?? 0;
  const outstandingBalance = stats?.outstandingBalance ?? 0;

  const currentPage = records?.meta?.current_page ?? records?.current_page ?? 1;
  const lastPage = records?.meta?.last_page ?? records?.last_page ?? 1;
  const totalRecordCount = records?.meta?.total ?? records?.total ?? rows.length;
  const paginationLinks = records?.links ?? [];

  return (
    <div className="min-h-screen bg-[#FAFAF5] p-4 md:p-8">
      <Head title="Graduate School Ledger" />

      <div className="max-w-7xl mx-auto space-y-6">

        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-[#CFE3FF] pb-5">
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-2xl font-bold tracking-tight text-[#0B3D91]">Graduate School Ledger</h1>
              <Badge variant="outline" className="bg-[#EAF2FF] text-[#0B62E0] border-[#B9D8FF] font-semibold">
                Graduate School
              </Badge>
            </div>
            <p className="text-sm text-[#5C7A9E] mt-0.5">Tuition, fees, and payment transactions for graduate students.</p>
          </div>

          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-2 w-full md:w-auto">
            <form onSubmit={handleSearchSubmit} className="flex flex-wrap items-center gap-2 w-full sm:w-auto">
              <div className="relative w-full sm:w-64">
                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-[#7FA6D6]" />
                <Input
                  type="search"
                  placeholder="Search name, course, or ref #..."
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
                  applyFilters({ semester: v });
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

              <input
                type="date"
                value={dateFrom}
                onChange={(e) => {
                  setDateFrom(e.target.value);
                  applyFilters({ date_from: e.target.value });
                }}
                className="h-9 rounded-md border border-[#CFE3FF] bg-white px-3 text-sm text-[#0B3D91]"
                placeholder="Date from"
              />

              <input
                type="date"
                value={dateTo}
                onChange={(e) => {
                  setDateTo(e.target.value);
                  applyFilters({ date_to: e.target.value });
                }}
                className="h-9 rounded-md border border-[#CFE3FF] bg-white px-3 text-sm text-[#0B3D91]"
                placeholder="Date to"
              />

              <Button
                type="button"
                variant="outline"
                className="h-9 border-[#CFE3FF] text-[#0B3D91] hover:bg-[#F3F8FF]"
                onClick={() => {
                  setSearchQuery('');
                  setSchoolYear('');
                  setSemester('');
                  setCourse('');
                  setDateFrom('');
                  setDateTo('');
                  router.get('/graduate-ledger');
                }}
              >
                <XCircle className="h-4 w-4 mr-1.5" />
                Clear Filters
              </Button>
            </form>

            <div className="flex flex-wrap items-center justify-end gap-2 ml-auto">
              <label className="inline-flex cursor-pointer items-center rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm font-medium text-[#0B3D91] hover:bg-[#F3F8FF] transition-colors">
                <input
                  type="file"
                  accept=".csv,.xlsx,.xls"
                  className="hidden"
                  onChange={(e) => {
                    const file = e.target.files?.[0] ?? null;
                    importForm.setData('file', file);

                    if (file) {
                      importForm.post('/graduate-ledger/import', {
                        forceFormData: true,
                        preserveScroll: true,
                        onSuccess: () => {
                          importForm.reset('file');
                          e.currentTarget.value = '';
                        },
                      });
                    }
                  }}
                />
                Import Excel/CSV
              </label>

              <Button className="bg-[#0F6FFF] hover:bg-[#0B5DDB] text-white" onClick={() => router.get('/graduate-ledger/add')}>
                <PlusCircle className="h-4 w-4 mr-1.5" />
                New Transaction
              </Button>
            </div>
          </div>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <Card className="shadow-xs border border-[#CFE3FF] bg-white">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium text-[#5C7A9E]">Students on Ledger</CardTitle>
              <GraduationCap className="h-4 w-4 text-[#0F6FFF]" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold tracking-tight text-[#0B3D91]">{totalStudents}</div>
              <p className="text-[10px] text-[#8AA8CC] mt-1">Unique graduate students</p>
            </CardContent>
          </Card>

          <Card className="shadow-xs border border-[#CFE3FF] bg-white">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium text-[#5C7A9E]">Total Assessments</CardTitle>
              <DollarSign className="h-4 w-4 text-[#0F6FFF]" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold tracking-tight text-[#0B3D91]">{currency(totalAssessments)}</div>
              <p className="text-[10px] text-[#8AA8CC] mt-1">Total tuition + fees billed</p>
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
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Semester</th>
                  <th className="text-right font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Units</th>
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Trans. Date</th>
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Ref. (JEV/OR #)</th>
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Particulars</th>
                  <th className="text-right font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Tuition/Unit or Reg. & Misc. Fee</th>
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">AR/Payment</th>
                  <th className="text-right font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Amount</th>
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Remark</th>
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Input By</th>
                  <th className="py-2 pr-2 text-center font-medium whitespace-nowrap text-[#5C7A9E]">Actions</th>
                </tr>
              </thead>
              <tbody>
                {rows.length === 0 ? (
                  <tr>
                    <td colSpan={14} className="text-center text-sm text-[#8AA8CC] py-8">
                      No transactions found. Upload a CSV/Excel file or add one manually.
                    </td>
                  </tr>
                ) : (
                  rows.map((r) => (
                    <tr key={r.id} className="border-b border-[#EAF2FF] hover:bg-[#F3F8FF]">
                      <td className="py-2 pr-4 pl-2 font-medium whitespace-nowrap text-[#0B3D91]">{r.name}</td>
                      <td className="py-2 pr-4 text-[#334E68]">{r.course}</td>
                      <td className="py-2 pr-4 text-[#334E68]">{r.schoolYear}</td>
                      <td className="py-2 pr-4 text-[#334E68]">{r.semester}</td>
                      <td className="py-2 pr-4 text-right text-[#334E68]">{r.units}</td>
                      <td className="py-2 pr-4 whitespace-nowrap text-[#334E68]">{formatTransactionDate(r.transactionDate)}</td>
                      <td className="py-2 pr-4 whitespace-nowrap text-[#334E68]">{r.referenceNo}</td>
                      <td className="py-2 pr-4 text-[#334E68]">{r.particulars}</td>
                      <td className="py-2 pr-4 text-right text-[#334E68]">{currency(r.tuitionPerUnitOrFeePerSemester)}</td>
                      <td className="py-2 pr-4">
                        <Badge variant="outline" className="border-[#B9D8FF] text-[#0B62E0] bg-[#EAF2FF]">
                          {r.arPayment}
                        </Badge>
                      </td>
                      <td className="py-2 pr-4 text-right font-medium text-[#0B3D91]">{currency(r.amount)}</td>
                      <td className="py-2 pr-4 text-[#8AA8CC]">{r.remark}</td>
                      <td className="py-2 pr-4 text-[#8AA8CC]">{r.inputBy}</td>
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

          {paginationLinks.length > 3 && (
            <CardFooter className="flex flex-col sm:flex-row items-center justify-between gap-4 border-t border-[#CFE3FF] pt-4 pb-4">
              <div className="text-xs text-[#5C7A9E]">
                Page <span className="font-semibold text-[#0B3D91]">{currentPage}</span> of{' '}
                <span className="font-semibold text-[#0B3D91]">{lastPage}</span>
              </div>

              <Pagination className="justify-end w-auto mx-0">
                <PaginationContent className="gap-1">
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
    router.delete(`/graduate-ledger/${id}`);
  }
}
