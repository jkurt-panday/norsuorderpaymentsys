import { Head, router, useForm } from '@inertiajs/react';
import {
  Search,
  DollarSign,
  GraduationCap,
  Layers,
  Wallet,
  PlusCircle,
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
  if (!value) return '-';

  const normalized = String(value).trim();
  if (!normalized) return '-';

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

interface IndexProps {
  records?: LedgerPaginator;
  filters?: { search?: string; year?: string; month?: string };
  stats?: {
    totalStudents?: number;
    totalUnits?: number;
    totalCharges?: number;
    totalPayments?: number;
    outstandingBalance?: number;
  };
}

export default function Index({ records, filters, stats }: IndexProps) {
  const rows: LedgerRecord[] = records?.data ?? [];
  const [searchQuery, setSearchQuery] = useState(filters?.search ?? '');
  const [selectedYear, setSelectedYear] = useState(filters?.year ?? '');
  const [selectedMonth, setSelectedMonth] = useState(filters?.month ?? '');
  const importForm = useForm<{ file: File | null }>({ file: null });

  const applyFilters = (nextSearch = searchQuery, nextYear = selectedYear, nextMonth = selectedMonth) => {
    const params: Record<string, string> = {};

    if (nextSearch.trim()) params.search = nextSearch.trim();
    if (nextYear) params.year = nextYear;
    if (nextMonth) params.month = nextMonth;

    router.get('/graduate-ledger', params, {
      preserveState: true,
      replace: true,
    });
  };

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    applyFilters();
  };

  const handleFilterChange = () => {
    const params: Record<string, string> = {};

    if (searchQuery.trim()) params.search = searchQuery.trim();
    if (selectedYear) params.year = selectedYear;
    if (selectedMonth) params.month = selectedMonth;

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

  const currentPage = records?.meta?.current_page ?? records?.current_page ?? 1;
  const lastPage = records?.meta?.last_page ?? records?.last_page ?? 1;
  const totalRecordCount = records?.meta?.total ?? records?.total ?? rows.length;

  const paginationLinks = records?.links ?? [];

  return (
    <div className="min-h-screen bg-[#FAFAF5] p-4 md:p-8">
      <Head title="Graduate School Ledger" />

      <div className="max-w-7xl mx-auto space-y-6">

        {/* Top Header / Action Bar */}
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-[#CFE3FF] pb-5">
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-2xl font-bold tracking-tight text-[#0B3D91]">Graduate School Ledger</h1>
              <Badge variant="outline" className="bg-[#EAF2FF] text-[#0B62E0] border-[#B9D8FF] font-semibold">
                Postgraduate Registry
              </Badge>
            </div>
            <p className="text-sm text-[#5C7A9E] mt-0.5">Tuition, fees, and payment transactions by student.</p>
          </div>

          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-2 w-full md:w-auto">
            <form onSubmit={handleSearchSubmit} className="flex flex-wrap items-center gap-2 w-full sm:w-auto">
              <div className="relative w-full sm:w-64">
                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-[#7FA6D6]" />
                <Input
                  type="search"
                  placeholder="Search name, course, or OR/JEV #..."
                  className="pl-8 h-9 bg-white border-[#CFE3FF] focus-visible:ring-[#0F6FFF]"
                  value={searchQuery}
                  onChange={(e) => {
                  const nextValue = e.target.value;
                  setSearchQuery(nextValue);
                  applyFilters(nextValue, selectedYear, selectedMonth);
                }}
                />
              </div>

              <select
                value={selectedYear}
                onChange={(e) => {
                  const nextYear = e.target.value;
                  setSelectedYear(nextYear);
                  applyFilters(searchQuery, nextYear, selectedMonth);
                }}
                className="h-9 rounded-md border border-[#CFE3FF] bg-white px-3 text-sm text-[#0B3D91]"
              >
                <option value="">All Years</option>
                <option value="2024">2024</option>
                <option value="2025">2025</option>
                <option value="2026">2026</option>
              </select>

              <select
                value={selectedMonth}
                onChange={(e) => {
                  const nextMonth = e.target.value;
                  setSelectedMonth(nextMonth);
                  applyFilters(searchQuery, selectedYear, nextMonth);
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

        {/* Metrics Row */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <Card className="shadow-xs border border-[#CFE3FF] bg-white">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium text-[#5C7A9E]">Students on Ledger</CardTitle>
              <GraduationCap className="h-4 w-4 text-[#0F6FFF]" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold tracking-tight text-[#0B3D91]">{totalStudents}</div>
              <p className="text-[10px] text-[#8AA8CC] mt-1">Unique active students</p>
            </CardContent>
          </Card>

          <Card className="shadow-xs border border-[#CFE3FF] bg-white">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium text-[#5C7A9E]">Total Units</CardTitle>
              <Layers className="h-4 w-4 text-[#0F6FFF]" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold tracking-tight text-[#0B3D91]">{totalUnits}</div>
              <p className="text-[10px] text-[#8AA8CC] mt-1">Total units enrolled</p>
            </CardContent>
          </Card>

          <Card className="shadow-xs border border-[#CFE3FF] bg-white">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium text-[#5C7A9E]">Total Charges (AR)</CardTitle>
              <DollarSign className="h-4 w-4 text-[#0F6FFF]" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold tracking-tight text-[#0B3D91]">{currency(totalCharges)}</div>
              <p className="text-[10px] text-[#8AA8CC] mt-1">Total tuition + fees billed</p>
            </CardContent>
          </Card>

          <Card className="shadow-xs border border-[#CFE3FF] bg-white">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium text-[#5C7A9E]">Outstanding Balance</CardTitle>
              <Wallet className="h-4 w-4 text-[#0F6FFF]" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold tracking-tight text-[#0B3D91]">{currency(outstandingBalance)}</div>
              <p className="text-[10px] text-[#8AA8CC] mt-1">Net pending balance</p>
            </CardContent>
          </Card>
        </div>

        {/* Ledger Table with shadcn Pagination */}
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
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">S.Y.</th>
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Term</th>
                  <th className="text-right font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Units</th>
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Trans. Date</th>
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Ref. (JEV/OR #)</th>
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Particulars</th>
                  <th className="text-right font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Rate/Unit</th>
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">AR/Payment</th>
                  <th className="text-right font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Amount</th>
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Remark</th>
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Input By</th>
                </tr>
              </thead>
              <tbody>
                {rows.length === 0 ? (
                  <tr>
                    <td colSpan={13} className="text-center text-sm text-[#8AA8CC] py-8">
                      No transactions found. Upload a CSV/Excel file or add one manually.
                    </td>
                  </tr>
                ) : (
                  rows.map((r) => (
                    <tr key={r.id} className="border-b border-[#EAF2FF] hover:bg-[#F3F8FF]">
                      <td className="py-2 pr-4 pl-2 font-medium whitespace-nowrap text-[#0B3D91]">{r.name}</td>
                      <td className="py-2 pr-4 text-[#334E68]">{r.course}</td>
                      <td className="py-2 pr-4 text-[#334E68]">{r.schoolYear}</td>
                      <td className="py-2 pr-4 text-[#334E68]">{r.term}</td>
                      <td className="py-2 pr-4 text-right text-[#334E68]">{r.units}</td>
                      <td className="py-2 pr-4 whitespace-nowrap text-[#334E68]">{formatTransactionDate(r.transactionDate)}</td>
                      <td className="py-2 pr-4 whitespace-nowrap text-[#334E68]">{r.referenceNo}</td>
                      <td className="py-2 pr-4 text-[#334E68]">{r.particulars}</td>
                      <td className="py-2 pr-4 text-right text-[#334E68]">{currency(r.ratePerUnit)}</td>
                      <td className="py-2 pr-4">
                        <Badge variant="outline" className="border-[#B9D8FF] text-[#0B62E0] bg-[#EAF2FF]">
                          {r.arPayment}
                        </Badge>
                      </td>
                      <td className="py-2 pr-4 text-right font-medium text-[#0B3D91]">{currency(r.amount)}</td>
                      <td className="py-2 pr-4 text-[#8AA8CC]">{r.remark}</td>
                      <td className="py-2 pr-4 text-[#8AA8CC]">{r.inputBy}</td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </CardContent>

          {/* ---- shadcn Pagination Footer ---- */}
          {paginationLinks.length > 3 && (
            <CardFooter className="flex flex-col sm:flex-row items-center justify-between border-t border-[#CFE3FF] pt-4 pb-4 gap-4">
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
                              if (link.url) router.get(link.url, {}, { preserveState: true, preserveScroll: true });
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
                              if (link.url) router.get(link.url, {}, { preserveState: true, preserveScroll: true });
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
                            if (link.url) router.get(link.url, {}, { preserveState: true, preserveScroll: true });
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