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
  Download,
  Loader2,
  CheckCircle2,
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
  const importForm = useForm<{ file: File | null }>({ file: null });

  // ── Single filter state object to avoid stale-closure bugs ────────────────
  const [filterState, setFilterState] = useState({
    search:      filters?.search      ?? '',
    school_year: filters?.school_year ?? '',
    semester:    filters?.semester    ?? '',
    course:      filters?.course      ?? '',
    date_from:   filters?.date_from   ?? '',
    date_to:     filters?.date_to     ?? '',
  });

  const [goToPage, setGoToPage] = useState('');

  const [isExporting, setIsExporting] = useState(false);
  const [exportProgress, setExportProgress] = useState(0);
  const [exportSuccess, setExportSuccess] = useState(false);

  const [isImporting, setIsImporting] = useState(false);
  const [importProgress, setImportProgress] = useState(0);
  const [importSuccess, setImportSuccess] = useState(false);

  const handleImportFile = (file: File | null, inputEl: HTMLInputElement) => {
    if (!file || isImporting) return;

    setIsImporting(true);
    setImportProgress(10);
    setImportSuccess(false);

    const interval = setInterval(() => {
      setImportProgress((prev) => {
        if (prev >= 90) return 90;
        return prev + Math.floor(Math.random() * 8) + 5;
      });
    }, 250);

    importForm.setData('file', file);
    importForm.post('/graduate-ledger/import', {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => {
        clearInterval(interval);
        setImportProgress(100);
        setTimeout(() => {
          setIsImporting(false);
          setImportSuccess(true);
          importForm.reset('file');
          inputEl.value = '';
          setTimeout(() => setImportSuccess(false), 4000);
        }, 300);
      },
      onError: () => {
        clearInterval(interval);
        setIsImporting(false);
        setImportProgress(0);
        inputEl.value = '';
      },
    });
  };

  const handleExport = async () => {
    if (isExporting) return;
    setIsExporting(true);
    setExportProgress(10);
    setExportSuccess(false);

    // Smoothly increment progress while waiting for the server
    const interval = setInterval(() => {
      setExportProgress((prev) => {
        if (prev >= 90) return 90;
        return prev + Math.floor(Math.random() * 8) + 5;
      });
    }, 250);

    try {
      const params = new URLSearchParams();
      if (filterState.search)      params.set('search',      filterState.search);
      if (filterState.school_year) params.set('school_year', filterState.school_year);
      if (filterState.semester)    params.set('semester',    filterState.semester);
      if (filterState.course)      params.set('course',      filterState.course);
      if (filterState.date_from)   params.set('date_from',   filterState.date_from);
      if (filterState.date_to)     params.set('date_to',     filterState.date_to);

      const qs = params.toString();
      const url = '/graduate-ledger/export' + (qs ? '?' + qs : '');

      const response = await fetch(url, { method: 'GET' });
      if (!response.ok) throw new Error('Export failed');

      const blob = await response.blob();
      clearInterval(interval);
      setExportProgress(100);

      const downloadUrl = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = downloadUrl;
      const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);
      a.download = `graduate_ledger_export_${timestamp}.xlsx`;
      document.body.appendChild(a);
      a.click();
      a.remove();
      window.URL.revokeObjectURL(downloadUrl);

      setTimeout(() => {
        setIsExporting(false);
        setExportSuccess(true);
        setTimeout(() => setExportSuccess(false), 4000);
      }, 300);
    } catch (error) {
      clearInterval(interval);
      console.error('Export error:', error);
      setIsExporting(false);
      setExportProgress(0);
    }
  };

  // Convenience aliases for the template
  const searchQuery = filterState.search;
  const schoolYear  = filterState.school_year;
  const semester    = filterState.semester;
  const course      = filterState.course;
  const dateFrom    = filterState.date_from;
  const dateTo      = filterState.date_to;

  /**
   * Merge overrides into the current filter state, then immediately
   * navigate — uses the merged object directly so there is no stale closure.
   */
  const applyFilters = (overrides: Record<string, string> = {}) => {
    const merged = { ...filterState, ...overrides };
    setFilterState(merged);

    const params: Record<string, string> = {};
    Object.entries(merged).forEach(([key, value]) => {
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

  const handleGoToPage = (e: React.FormEvent) => {
    e.preventDefault();
    const pageNum = parseInt(goToPage, 10);
    const last = records?.meta?.last_page ?? records?.last_page ?? 1;
    if (!isNaN(pageNum) && pageNum >= 1 && pageNum <= last) {
      const currentParams = new URLSearchParams(window.location.search);
      currentParams.set('page', String(pageNum));
      router.get(`/graduate-ledger?${currentParams.toString()}`, {}, { preserveState: true, preserveScroll: true });
    }
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
    <>
      <div className="max-w-7xl mx-auto space-y-6">
      <Head title="Graduate School Ledger" />

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
                  onChange={(e) =>
                    setFilterState((prev) => ({ ...prev, search: e.target.value }))
                  }
                />
              </div>

              <Button
                type="submit"
                size="sm"
                className="h-9 bg-[#0F6FFF] hover:bg-[#0B5DDB] text-white"
              >
                <Search className="h-4 w-4 mr-1.5" /> Search
              </Button>

              <select
                value={schoolYear}
                onChange={(e) => applyFilters({ school_year: e.target.value })}
                className="h-9 rounded-md border border-[#CFE3FF] bg-white px-3 text-sm text-[#0B3D91]"
              >
                <option value="">All School Years</option>
                {(filterOptions?.schoolYears ?? []).map((sy) => (
                  <option key={sy} value={sy}>{sy}</option>
                ))}
              </select>

              <select
                value={semester}
                onChange={(e) => applyFilters({ semester: e.target.value })}
                className="h-9 rounded-md border border-[#CFE3FF] bg-white px-3 text-sm text-[#0B3D91]"
              >
                <option value="">All Semesters</option>
                {(filterOptions?.semesters ?? []).map((s) => (
                  <option key={s} value={s}>{s}</option>
                ))}
              </select>

              <select
                value={course}
                onChange={(e) => applyFilters({ course: e.target.value })}
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
                onChange={(e) => applyFilters({ date_from: e.target.value })}
                className="h-9 rounded-md border border-[#CFE3FF] bg-white px-3 text-sm text-[#0B3D91]"
                placeholder="Date from"
              />

              <input
                type="date"
                value={dateTo}
                onChange={(e) => applyFilters({ date_to: e.target.value })}
                className="h-9 rounded-md border border-[#CFE3FF] bg-white px-3 text-sm text-[#0B3D91]"
                placeholder="Date to"
              />

              <Button
                type="button"
                variant="outline"
                className="h-9 border-[#CFE3FF] text-[#0B3D91] hover:bg-[#F3F8FF]"
                onClick={() => {
                  setFilterState({
                    search: '',
                    school_year: '',
                    semester: '',
                    course: '',
                    date_from: '',
                    date_to: '',
                  });
                  router.get('/graduate-ledger');
                }}
              >
                <XCircle className="h-4 w-4 mr-1.5" />
                Clear Filters
              </Button>
            </form>

            <div className="flex flex-wrap items-center justify-end gap-2 ml-auto">
              <label className={`inline-flex items-center rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm font-medium text-[#0B3D91] transition-colors ${isImporting ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer hover:bg-[#F3F8FF]'}`}>
                <input
                  type="file"
                  accept=".csv,.xlsx,.xls"
                  disabled={isImporting}
                  className="hidden"
                  onChange={(e) => {
                    const file = e.target.files?.[0] ?? null;
                    handleImportFile(file, e.target);
                  }}
                />
                {isImporting ? (
                  <>
                    <Loader2 className="h-4 w-4 mr-1.5 animate-spin text-[#0F6FFF]" />
                    Importing...
                  </>
                ) : (
                  'Import Excel/CSV'
                )}
              </label>

              <Button
                variant="outline"
                disabled={isExporting}
                className="border-[#0F6FFF] text-[#0F6FFF] hover:bg-[#E8F0FE]"
                onClick={handleExport}
              >
                {isExporting ? (
                  <Loader2 className="h-4 w-4 mr-1.5 animate-spin text-[#0F6FFF]" />
                ) : (
                  <Download className="h-4 w-4 mr-1.5" />
                )}
                {isExporting ? 'Exporting...' : 'Export Excel'}
              </Button>

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
              <div className="flex items-center gap-4 text-xs text-[#5C7A9E]">
                <div>
                  Page <span className="font-semibold text-[#0B3D91]">{currentPage}</span> of{' '}
                  <span className="font-semibold text-[#0B3D91]">{lastPage}</span>
                </div>
                <form onSubmit={handleGoToPage} className="flex items-center gap-1.5">
                  <span>Go to:</span>
                  <input
                    type="number"
                    min={1}
                    max={lastPage}
                    value={goToPage}
                    onChange={(e) => setGoToPage(e.target.value)}
                    placeholder={String(currentPage)}
                    className="w-14 h-7 rounded border border-[#CFE3FF] bg-white px-2 text-center text-xs text-[#0B3D91] font-medium focus:outline-none focus:ring-1 focus:ring-[#0B62E0]"
                  />
                  <button
                    type="submit"
                    className="h-7 px-2.5 rounded bg-[#EAF2FF] text-[#0B62E0] hover:bg-[#D4E5FF] text-xs font-medium transition-colors"
                  >
                    Go
                  </button>
                </form>
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

      {/* Floating Bottom-Right Import Progress Bar & Toast */}
      {(isImporting || importSuccess) && (
        <div className="fixed bottom-6 right-6 z-50 flex min-w-[320px] flex-col gap-2.5 rounded-xl border border-[#CFE3FF] bg-white p-4 shadow-xl transition-all duration-300">
          {isImporting ? (
            <>
              <div className="flex items-center gap-3">
                <div className="rounded-lg bg-[#E8F0FE] p-2 text-[#0F6FFF]">
                  <Loader2 className="h-5 w-5 animate-spin" />
                </div>
                <div>
                  <p className="text-sm font-semibold text-[#0B3D91]">Importing Spreadsheet...</p>
                  <p className="text-xs text-[#5C7A9E]">Processing and saving dataset records ({importProgress}%)</p>
                </div>
              </div>

              {/* Animated Progress Bar */}
              <div className="mt-1 w-full">
                <div className="h-2 w-full overflow-hidden rounded-full bg-[#E8F0FE]">
                  <div
                    className="h-full rounded-full bg-[#0F6FFF] transition-all duration-300 ease-out"
                    style={{ width: `${importProgress}%` }}
                  />
                </div>
              </div>
            </>
          ) : (
            <div className="flex items-center gap-3">
              <div className="rounded-lg bg-emerald-100 p-2 text-emerald-600">
                <CheckCircle2 className="h-5 w-5" />
              </div>
              <div>
                <p className="text-sm font-semibold text-emerald-900">Import Complete!</p>
                <p className="text-xs text-emerald-700">Spreadsheet records have been imported.</p>
              </div>
            </div>
          )}
        </div>
      )}

      {/* Floating Bottom-Right Export Progress Bar & Toast */}
      {(isExporting || exportSuccess) && (
        <div className="fixed bottom-6 right-6 z-50 flex min-w-[320px] flex-col gap-2.5 rounded-xl border border-[#CFE3FF] bg-white p-4 shadow-xl transition-all duration-300">
          {isExporting ? (
            <>
              <div className="flex items-center gap-3">
                <div className="rounded-lg bg-[#E8F0FE] p-2 text-[#0F6FFF]">
                  <Loader2 className="h-5 w-5 animate-spin" />
                </div>
                <div>
                  <p className="text-sm font-semibold text-[#0B3D91]">Generating Excel Export...</p>
                  <p className="text-xs text-[#5C7A9E]">Processing dataset rows ({exportProgress}%)</p>
                </div>
              </div>

              {/* Animated Progress Bar */}
              <div className="mt-1 w-full">
                <div className="h-2 w-full overflow-hidden rounded-full bg-[#E8F0FE]">
                  <div
                    className="h-full rounded-full bg-[#0F6FFF] transition-all duration-300 ease-out"
                    style={{ width: `${exportProgress}%` }}
                  />
                </div>
              </div>
            </>
          ) : (
            <div className="flex items-center gap-3">
              <div className="rounded-lg bg-emerald-100 p-2 text-emerald-600">
                <CheckCircle2 className="h-5 w-5" />
              </div>
              <div>
                <p className="text-sm font-semibold text-emerald-900">Export Complete!</p>
                <p className="text-xs text-emerald-700">Your Excel file has been downloaded.</p>
              </div>
            </div>
          )}
        </div>
      )}
    </>
  );
}

function handleDelete(id: string | number, name: string) {
  if (confirm(`Delete transaction for ${name}?`)) {
    router.delete(`/graduate-ledger/${id}`);
  }
}