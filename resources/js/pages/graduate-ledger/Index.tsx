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
  ChevronLeft,
  ChevronRight,
  Columns3,
  ChevronDown,
  Filter,
  Mail,
} from 'lucide-react';
import React, { useState } from 'react';
import StudentBalanceDrawer from './StudentBalanceDrawer';
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
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover';
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
  studentId: number;
  studentNumber?: string | null;
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

function getEntryTypeBadge(type?: string): string {
  const normalized = (type ?? '').trim().toUpperCase();

  if (normalized === 'PAYMENT' || normalized === 'P') {
    return 'bg-emerald-50 text-emerald-700 border-emerald-200 font-semibold';
  }

  if (normalized === 'ADJUSTMENT' || normalized === 'ADJ' || normalized.includes('ADJUST')) {
    return 'bg-purple-50 text-purple-700 border-purple-200 font-semibold';
  }

  return 'bg-[#EAF2FF] text-[#0B62E0] border-[#B9D8FF] font-semibold';
}

function getRemarkBadge(remark?: string): string {
  const normalized = (remark ?? '').trim().toLowerCase();

  if (normalized === 'outstanding') {
    return 'bg-amber-50 text-amber-800 border-amber-300 font-semibold';
  }

  if (normalized === 'settled') {
    return 'bg-emerald-50 text-emerald-800 border-emerald-300 font-semibold';
  }

  return 'bg-gray-50 text-gray-700 border-gray-200';
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
    balance_status?: string;
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
  courses?: { id: number; code: string }[];
  academicTerms?: { id: number; school_year: string; semester: string }[];
}

const defaultVisibleColumns = {
  course: true,
  schoolYear: true,
  semester: true,
  units: true,
  transactionDate: true,
  referenceNo: true,
  particulars: true,
  feeRate: false,
  entryType: true,
  remark: true,
  inputBy: true,
};

type OptionalColumn = keyof typeof defaultVisibleColumns;

const optionalColumnLabels: Record<OptionalColumn, string> = {
  course: 'Course',
  schoolYear: 'School Year',
  semester: 'Semester',
  units: 'Units',
  transactionDate: 'Transaction Date',
  referenceNo: 'Reference Number',
  particulars: 'Particulars',
  feeRate: 'Tuition/Unit or Reg. & Misc. Fee',
  entryType: 'AR/Payment',
  remark: 'Remark',
  inputBy: 'Input By',
};

export default function Index({ records, filters, stats, filterOptions, courses = [], academicTerms = [] }: IndexProps) {
  const rows: LedgerRecord[] = records?.data ?? [];
  const importForm = useForm<{ file: File | null; preset_course_id: string; preset_academic_term_id: string }>({
    file: null,
    preset_course_id: '',
    preset_academic_term_id: '',
  });
  const [isImportDialogOpen, setIsImportDialogOpen] = useState(false);
  const [drawerSelection, setDrawerSelection] = useState<{
    studentId: number;
    transactionId: string | number;
  } | null>(null);
  const [showFilters, setShowFilters] = useState(() =>
    Boolean(
      filters?.school_year ||
      filters?.semester ||
      filters?.course ||
      filters?.date_from ||
      filters?.date_to ||
      filters?.balance_status,
    ),
  );
  const [visibleColumns, setVisibleColumns] = useState(() => {
    if (typeof window === 'undefined') {
      return defaultVisibleColumns;
    }

    try {
      const saved = JSON.parse(
        window.localStorage.getItem('graduate-ledger.visible-columns') ?? '{}',
      ) as Partial<typeof defaultVisibleColumns>;

      return { ...defaultVisibleColumns, ...saved };
    } catch {
      return defaultVisibleColumns;
    }
  });

  const toggleColumn = (column: OptionalColumn) => {
    setVisibleColumns((current) => {
      const next = { ...current, [column]: !current[column] };
      window.localStorage.setItem('graduate-ledger.visible-columns', JSON.stringify(next));

      return next;
    });
  };

  const visibleColumnCount = 3 + Object.values(visibleColumns).filter(Boolean).length;

  // ── Single filter state object to avoid stale-closure bugs ────────────────
  const [filterState, setFilterState] = useState({
    search:         filters?.search         ?? '',
    school_year:    filters?.school_year    ?? '',
    semester:       filters?.semester       ?? '',
    course:         filters?.course         ?? '',
    date_from:      filters?.date_from      ?? '',
    date_to:        filters?.date_to        ?? '',
    balance_status: filters?.balance_status ?? '',
  });

  const [goToPage, setGoToPage] = useState('');
  const [isFiltering, setIsFiltering] = useState(false);

  const [isExporting, setIsExporting] = useState(false);
  const [exportProgress, setExportProgress] = useState(0);
  const [exportSuccess, setExportSuccess] = useState(false);

  const [isImporting, setIsImporting] = useState(false);
  const [importProgress, setImportProgress] = useState(0);
  const [importSuccess, setImportSuccess] = useState(false);

  const handleImport = () => {
    if (!importForm.data.file || isImporting) {
return;
}

    setIsImporting(true);
    setImportProgress(10);
    setImportSuccess(false);

    const interval = setInterval(() => {
      setImportProgress((prev) => {
        if (prev >= 90) {
return 90;
}

        return prev + Math.floor(Math.random() * 8) + 5;
      });
    }, 250);

    importForm.post('/graduate-ledger/import', {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => {
        clearInterval(interval);
        setImportProgress(100);
        setTimeout(() => {
          setIsImporting(false);
          setImportSuccess(true);
          setIsImportDialogOpen(false);
          importForm.reset();
          setTimeout(() => setImportSuccess(false), 4000);
        }, 300);
      },
      onError: () => {
        clearInterval(interval);
        setIsImporting(false);
        setImportProgress(0);
      },
    });
  };

  const handleExport = async () => {
    if (isExporting) {
return;
}

    setIsExporting(true);
    setExportProgress(10);
    setExportSuccess(false);

    // Smoothly increment progress while waiting for the server
    const interval = setInterval(() => {
      setExportProgress((prev) => {
        if (prev >= 90) {
return 90;
}

        return prev + Math.floor(Math.random() * 8) + 5;
      });
    }, 250);

    try {
      const params = new URLSearchParams();

      if (filterState.search)      {
params.set('search',      filterState.search);
}

      if (filterState.school_year) {
params.set('school_year', filterState.school_year);
}

      if (filterState.semester)    {
params.set('semester',    filterState.semester);
}

      if (filterState.course)      {
params.set('course',      filterState.course);
}

      if (filterState.date_from)   {
params.set('date_from',   filterState.date_from);
}

      if (filterState.date_to)     {
params.set('date_to',     filterState.date_to);
}

      if (filterState.balance_status) {
params.set('balance_status', filterState.balance_status);
}

      const qs = params.toString();
      const url = '/graduate-ledger/export' + (qs ? '?' + qs : '');

      const response = await fetch(url, { method: 'GET' });

      if (!response.ok) {
throw new Error('Export failed');
}

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
  const searchQuery   = filterState.search;
  const schoolYear    = filterState.school_year;
  const semester      = filterState.semester;
  const course        = filterState.course;
  const dateFrom      = filterState.date_from;
  const dateTo        = filterState.date_to;
  const balanceStatus = filterState.balance_status;

  const activeAdvancedFilters = [
    schoolYear && { key: 'school_year', label: `School year: ${schoolYear}` },
    semester && { key: 'semester', label: semester },
    course && { key: 'course', label: `Course: ${course}` },
    balanceStatus && {
      key: 'balance_status',
      label: balanceStatus === 'with_balance' ? 'Outstanding balance' : 'Fully paid',
    },
    dateFrom && { key: 'date_from', label: `From: ${formatTransactionDate(dateFrom)}` },
    dateTo && { key: 'date_to', label: `To: ${formatTransactionDate(dateTo)}` },
  ].filter(Boolean) as { key: keyof typeof filterState; label: string }[];

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
      onStart: () => setIsFiltering(true),
      onFinish: () => setIsFiltering(false),
    });
  };

  const applyDatePreset = (preset: 'today' | 'month' | 'clear') => {
    if (preset === 'clear') {
      applyFilters({ date_from: '', date_to: '' });

      return;
    }

    const now = new Date();
    const toDateInput = (date: Date) => {
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');

      return `${year}-${month}-${day}`;
    };
    const today = toDateInput(now);

    if (preset === 'today') {
      applyFilters({ date_from: today, date_to: today });

      return;
    }

    applyFilters({
      date_from: toDateInput(new Date(now.getFullYear(), now.getMonth(), 1)),
      date_to: today,
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

        {/* Top Header / Action Bar */}
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

          <div className="flex flex-wrap items-center justify-end gap-2">
                        <Button variant="outline" className="h-9 border-[#CFE3FF] text-[#0B3D91] hover:bg-[#F3F8FF]">
              <Mail className="h-4 w-4 mr-1.5" />
              Send Email
            </Button>
            <Button
              variant="outline"
              disabled={isImporting}
              className={`border-[#CFE3FF] text-[#0B3D91] hover:bg-[#F3F8FF] ${isImporting ? 'opacity-60 cursor-not-allowed' : ''}`}
              onClick={() => setIsImportDialogOpen(true)}
            >
              {isImporting ? (
                <>
                  <Loader2 className="h-4 w-4 mr-1.5 animate-spin text-[#0F6FFF]" />
                  Importing...
                </>
              ) : (
                'Import Excel/CSV'
              )}
            </Button>

            <Button
              variant="outline"
              disabled={isExporting}
              className="h-9 border-[#CFE3FF] text-[#0B3D91] hover:bg-[#F3F8FF]"
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

        {/* Metrics Row */}
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

          <Card
            onClick={() => applyFilters({ balance_status: balanceStatus === 'with_balance' ? '' : 'with_balance' })}
            className={`shadow-xs border transition-all cursor-pointer select-none ${
              balanceStatus === 'with_balance'
                ? 'border-orange-400 bg-orange-50/70 ring-2 ring-orange-300'
                : 'border-[#CFE3FF] bg-white hover:border-orange-300 hover:bg-orange-50/30'
            }`}
          >
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium text-[#5C7A9E] flex items-center gap-1.5">
                Outstanding Balance
                {balanceStatus === 'with_balance' && (
                  <Badge variant="outline" className="text-[10px] bg-orange-100 text-orange-800 border-orange-300 px-1.5 py-0 h-4">
                    Active Filter
                  </Badge>
                )}
              </CardTitle>
              <AlertTriangle className={`h-4 w-4 ${balanceStatus === 'with_balance' ? 'text-orange-600 animate-pulse' : 'text-orange-500'}`} />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold tracking-tight text-[#0B3D91]">{currency(outstandingBalance)}</div>
              <p className="text-[10px] text-[#8AA8CC] mt-1">
                {balanceStatus === 'with_balance' ? 'Click to show all records' : 'Click to filter students with balance'}
              </p>
            </CardContent>
          </Card>
        </div>

        {/* Transaction Ledger Table with Filter Bar */}
        <Card className="border border-[#CFE3FF] bg-white">
          <CardHeader className="border-b border-[#CFE3FF] pb-4">
            <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
              <div>
                <CardTitle className="text-md text-[#0B3D91]">Transaction Ledger</CardTitle>
                <CardDescription className="text-[#7FA6D6] mt-0.5">
                  <span className="inline-flex items-center gap-1.5">
                    {isFiltering && <Loader2 className="h-3.5 w-3.5 animate-spin text-[#0F6FFF]" />}
                    {isFiltering
                      ? 'Updating results...'
                      : `Showing ${rows.length} of ${totalRecordCount} record${totalRecordCount === 1 ? '' : 's'}`}
                  </span>
                </CardDescription>
              </div>

              <form onSubmit={handleSearchSubmit} className="flex flex-1 flex-wrap items-center gap-2 md:justify-end">
                <Popover>
                  <PopoverTrigger>
                    <Button
                      type="button"
                      size="sm"
                      variant="outline"
                      className="h-9 border-[#CFE3FF] text-[#0B3D91] hover:bg-[#F3F8FF]"
                    >
                      <Columns3 className="h-4 w-4" /> Columns
                    </Button>
                  </PopoverTrigger>
                  <PopoverContent align="end" className="w-72 p-3">
                    <div className="mb-2">
                      <p className="text-sm font-semibold text-[#0B3D91]">Visible columns</p>
                      <p className="text-xs text-[#7FA6D6]">Name, Amount, and Actions always remain visible.</p>
                    </div>
                    <div className="grid gap-1">
                      {(Object.keys(optionalColumnLabels) as OptionalColumn[]).map((column) => (
                        <label key={column} className="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm text-[#334E68] hover:bg-[#F3F8FF]">
                          <input
                            type="checkbox"
                            checked={visibleColumns[column]}
                            onChange={() => toggleColumn(column)}
                            className="h-4 w-4 rounded border-[#B9D8FF] accent-[#0F6FFF]"
                          />
                          <span>{optionalColumnLabels[column]}</span>
                        </label>
                      ))}
                    </div>
                  </PopoverContent>
                </Popover>
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

                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  aria-expanded={showFilters}
                  onClick={() => setShowFilters((current) => !current)}
                  className={`h-9 border-[#CFE3FF] text-[#0B3D91] hover:bg-[#F3F8FF] ${
                    activeAdvancedFilters.length > 0 ? 'bg-[#EAF2FF]' : ''
                  }`}
                >
                  <Filter className="h-4 w-4" />
                  Filters
                  {activeAdvancedFilters.length > 0 && (
                    <span className="rounded-full bg-[#0F6FFF] px-1.5 py-0.5 text-[10px] font-bold text-white">
                      {activeAdvancedFilters.length}
                    </span>
                  )}
                  <ChevronDown className={`h-3.5 w-3.5 transition-transform ${showFilters ? 'rotate-180' : ''}`} />
                </Button>

                {showFilters && (
                <div className="flex basis-full flex-wrap items-end gap-2 rounded-lg border border-[#DCEAFF] bg-[#F8FBFF] p-3">
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

                <select
                  value={balanceStatus}
                  onChange={(e) => applyFilters({ balance_status: e.target.value })}
                  className={`h-9 rounded-md border px-3 text-sm font-medium transition-colors ${
                    balanceStatus === 'with_balance'
                      ? 'border-orange-400 bg-orange-50 text-orange-900 font-semibold'
                      : balanceStatus === 'cleared'
                      ? 'border-emerald-400 bg-emerald-50 text-emerald-900 font-semibold'
                      : 'border-[#CFE3FF] bg-white text-[#0B3D91]'
                  }`}
                >
                  <option value="">All Balances</option>
                  <option value="with_balance">With Outstanding Balance</option>
                  <option value="cleared">Cleared / Fully Paid</option>
                </select>

                <label className="grid gap-1 text-xs font-medium text-[#5C7A9E]">
                  From
                  <input
                    type="date"
                    value={dateFrom}
                    max={dateTo || undefined}
                    onChange={(e) => applyFilters({ date_from: e.target.value })}
                    className="h-9 rounded-md border border-[#CFE3FF] bg-white px-3 text-sm font-normal text-[#0B3D91]"
                  />
                </label>

                <label className="grid gap-1 text-xs font-medium text-[#5C7A9E]">
                  To
                  <input
                    type="date"
                    value={dateTo}
                    min={dateFrom || undefined}
                    onChange={(e) => applyFilters({ date_to: e.target.value })}
                    className="h-9 rounded-md border border-[#CFE3FF] bg-white px-3 text-sm font-normal text-[#0B3D91]"
                  />
                </label>

                <div className="flex flex-wrap items-center gap-1 self-end" aria-label="Date filter shortcuts">
                  <Button type="button" size="sm" variant="outline" onClick={() => applyDatePreset('today')} className="h-9 border-[#CFE3FF] text-[#0B3D91]">
                    Today
                  </Button>
                  <Button type="button" size="sm" variant="outline" onClick={() => applyDatePreset('month')} className="h-9 border-[#CFE3FF] text-[#0B3D91]">
                    This month
                  </Button>
                  {(dateFrom || dateTo) && (
                    <Button type="button" size="sm" variant="ghost" onClick={() => applyDatePreset('clear')} className="h-9 text-[#5C7A9E]">
                      Clear dates
                    </Button>
                  )}
                </div>

                <Button
                  type="button"
                  variant="outline"
                  className="h-9 border-[#CFE3FF] text-[#0B3D91] hover:bg-[#F3F8FF]"
                  onClick={() => {
                    applyFilters({
                      search: '',
                      school_year: '',
                      semester: '',
                      course: '',
                      date_from: '',
                      date_to: '',
                      balance_status: '',
                    });
                  }}
                >
                  <XCircle className="h-4 w-4 mr-1.5" />
                  Clear Filters
                </Button>
                </div>
                )}

                {activeAdvancedFilters.length > 0 && (
                  <div className="flex basis-full flex-wrap items-center gap-1.5">
                    <span className="mr-1 text-xs font-medium text-[#5C7A9E]">Active:</span>
                    {activeAdvancedFilters.map((filter) => (
                      <button
                        key={filter.key}
                        type="button"
                        onClick={() => applyFilters({ [filter.key]: '' })}
                        className="inline-flex items-center gap-1 rounded-full border border-[#B9D8FF] bg-[#EAF2FF] px-2.5 py-1 text-xs font-medium text-[#0B62E0] transition-colors hover:border-[#0F6FFF] hover:bg-[#DCEAFF]"
                        title={`Remove ${filter.label} filter`}
                      >
                        {filter.label}
                        <XCircle className="h-3.5 w-3.5" />
                      </button>
                    ))}
                    <button
                      type="button"
                      onClick={() => {
                        applyFilters({
                          school_year: '',
                          semester: '',
                          course: '',
                          date_from: '',
                          date_to: '',
                          balance_status: '',
                        });
                      }}
                      className="px-2 py-1 text-xs font-semibold text-[#5C7A9E] hover:text-[#0B3D91] hover:underline"
                    >
                      Clear all
                    </button>
                  </div>
                )}
              </form>
            </div>
                  </CardHeader>
        {isFiltering && (
          <div className="h-1 w-full overflow-hidden bg-[#EAF2FF]" role="progressbar" aria-label="Filtering ledger records">
            <div className="h-full w-1/3 animate-[pulse_1s_ease-in-out_infinite] rounded-full bg-[#0F6FFF]" />
          </div>
        )}
        <div
          aria-busy={isFiltering}
          className={`rotate-180 overflow-x-auto custom-scrollbar border-collapse transition-opacity ${
            isFiltering ? 'pointer-events-none opacity-45' : 'opacity-100'
          }`}
        >
            <div className="rotate-180 min-w-max">
          <CardContent className="overflow-x-auto">
            <table className="w-full text-sm border-collapse">
              <thead>
                <tr className="border-b border-[#CFE3FF] bg-[#F3F8FF]">
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 pl-2 whitespace-nowrap">Name</th>
                  {visibleColumns.course && <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Course</th>}
                  {visibleColumns.schoolYear && <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">School Year</th>}
                  {visibleColumns.semester && <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Semester</th>}
                  {visibleColumns.units && <th className="text-right font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Units</th>}
                  {visibleColumns.transactionDate && <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Trans. Date</th>}
                  {visibleColumns.referenceNo && <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Ref. (JEV/OR #)</th>}
                  {visibleColumns.particulars && <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Particulars</th>}
                  {visibleColumns.feeRate && (
                    <th className="text-right font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Tuition/Unit or Reg. & Misc. Fee</th>
                  )}
                  {visibleColumns.entryType && <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">AR/Payment</th>}
                  <th className="text-right font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Amount</th>
                  {visibleColumns.remark && <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Remark</th>}
                  {visibleColumns.inputBy && <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Input By</th>}
                  <th className="py-2 pr-2 text-center font-medium whitespace-nowrap text-[#5C7A9E]">Actions</th>
                </tr>
              </thead>
              <tbody>
                {rows.length === 0 ? (
                  <tr>
                    <td colSpan={visibleColumnCount} className="text-center text-sm text-[#8AA8CC] py-8">
                      No transactions found. Upload a CSV/Excel file or add one manually.
                    </td>
                  </tr>
                ) : (
                  rows.map((r) => (
                    <tr
                      key={r.id}
                      role="button"
                      tabIndex={0}
                      aria-label={`View ${r.name}'s balance and transaction history`}
                      onClick={() => setDrawerSelection({ studentId: r.studentId, transactionId: r.id })}
                      onKeyDown={(event) => {
                        if (event.key === 'Enter' || event.key === ' ') {
                          event.preventDefault();
                          setDrawerSelection({ studentId: r.studentId, transactionId: r.id });
                        }
                      }}
                      className="cursor-pointer border-b border-[#EAF2FF] transition-colors hover:bg-[#F3F8FF] focus-visible:bg-[#F3F8FF] focus-visible:outline-2 focus-visible:outline-[#0F6FFF]"
                    >
                      <td className="py-2 pr-4 pl-2 font-medium whitespace-nowrap text-[#0B3D91]">{r.name}</td>
                      {visibleColumns.course && <td className="py-2 pr-4 text-[#334E68]">{r.course}</td>}
                      {visibleColumns.schoolYear && <td className="py-2 pr-4 text-[#334E68]">{r.schoolYear}</td>}
                      {visibleColumns.semester && <td className="py-2 pr-4 text-[#334E68]">{r.semester}</td>}
                      {visibleColumns.units && <td className="py-2 pr-4 text-right text-[#334E68]">{r.units}</td>}
                      {visibleColumns.transactionDate && <td className="py-2 pr-4 whitespace-nowrap text-[#334E68]">{formatTransactionDate(r.transactionDate)}</td>}
                      {visibleColumns.referenceNo && <td className="py-2 pr-4 whitespace-nowrap text-[#334E68]">{r.referenceNo}</td>}
                      {visibleColumns.particulars && <td className="py-2 pr-4 text-[#334E68]">{r.particulars}</td>}
                      {visibleColumns.feeRate && (
                        <td className="py-2 pr-4 text-right text-[#334E68]">{currency(r.tuitionPerUnitOrFeePerSemester)}</td>
                      )}
                      {visibleColumns.entryType && <td className="py-2 pr-4">
                        <Badge variant="outline" className={getEntryTypeBadge(r.arPayment)}>
                          {r.arPayment}
                        </Badge>
                      </td>}
                      <td className="py-2 pr-4 text-right font-medium text-[#0B3D91]">{currency(r.amount)}</td>
                      {visibleColumns.remark && <td className="py-2 pr-4">
                        <Badge variant="outline" className={`text-xs ${getRemarkBadge(r.remark)}`}>
                          {r.remark || '—'}
                        </Badge>
                      </td>}
                      {visibleColumns.inputBy && <td className="py-2 pr-4 text-[#8AA8CC]">{r.inputBy}</td>}
                      <td className="py-2 pr-2 text-center whitespace-nowrap">
                        <button
                          onClick={(event) => {
                            event.stopPropagation();
                            router.get(`/graduate-ledger/${r.id}/edit`);
                          }}
                          className="mr-1 inline-flex items-center justify-center rounded p-1.5 text-[#0B62E0] transition-colors hover:bg-[#EAF2FF]"
                          title="Edit"
                        >
                          <Pencil className="h-3.5 w-3.5" />
                        </button>
                        <button
                          onClick={(event) => {
                            event.stopPropagation();
                            handleDelete(r.id, r.name);
                          }}
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
                      </div>
                  </div>

          {paginationLinks.length > 3 && (
            <CardFooter className="flex flex-col sm:flex-row items-center justify-between border-t border-[#CFE3FF] pt-4 pb-4 gap-4">
              <div className="flex items-center gap-4 text-xs text-[#5C7A9E]">
                <div>
                  Showing {currentPage} of {lastPage}
                </div>
                <span className="text-[#8AA8CC]">|</span>
                <div>
                  <span className="font-semibold text-[#0B3D91]">{totalRecordCount}</span> total records
                </div>
              </div>

              {lastPage > 5 ? (
                <div className="flex shrink-0 items-center gap-1.5">
                  <Button
                    type="button"
                    size="icon"
                    variant="outline"
                    disabled={currentPage <= 1}
                    onClick={() => {
                      const url = new URL(window.location.href);
                      url.searchParams.set('page', String(currentPage - 1));
                      router.get(url.pathname + url.search, {}, { preserveState: true, preserveScroll: true });
                    }}
                    aria-label="Previous page"
                    className="h-8 w-8 shrink-0 rounded-md border-[#CFE3FF] text-[#0B3D91] text-sm hover:bg-[#F3F8FF]"
                  >
                    <ChevronLeft className="h-4 w-4" />
                  </Button>

                  <Popover>
                    <PopoverTrigger>
                      <span
                        role="button"
                        tabIndex={0}
                        className="inline-flex h-8 cursor-pointer items-center justify-center gap-1 rounded-md border border-[#CFE3FF] bg-white px-3 text-sm font-medium text-[#0B3D91] transition-colors hover:bg-[#F3F8FF]"
                      >
                        Page {currentPage} of {lastPage}
                      </span>
                    </PopoverTrigger>
                    <PopoverContent
                      align="center"
                      className="w-48 space-y-2 p-2"
                    >
                      <form onSubmit={handleGoToPage} className="flex items-center gap-1.5">
                        <input
                          type="number"
                          min={1}
                          max={lastPage}
                          value={goToPage}
                          onChange={(e) => setGoToPage(e.target.value)}
                          placeholder={String(currentPage)}
                          className="h-8 w-full min-w-0 rounded-md border border-[#CFE3FF] bg-white px-2 text-sm text-[#0B3D91] outline-none focus:ring-2 focus:ring-[#0B62E0]"
                          autoFocus
                        />
                        <button
                          type="submit"
                          className="h-8 shrink-0 rounded-md bg-[#0F6FFF] px-2.5 text-xs font-semibold text-white transition-colors hover:bg-[#0B5DDB]"
                        >
                          Go
                        </button>
                      </form>

                      <div className="max-h-56 space-y-0.5 overflow-y-auto border-t border-[#EAF2FF] pt-1.5">
                        {Array.from({ length: lastPage }, (_, i) => i + 1).map((page) => (
                          <button
                            key={page}
                            type="button"
                            onClick={() => {
                              const url = new URL(window.location.href);
                              url.searchParams.set('page', String(page));
                              router.get(url.pathname + url.search, {}, { preserveState: true, preserveScroll: true });
                            }}
                            className={`flex w-full items-center rounded-md px-2 py-1.5 text-left text-sm transition-colors ${
                              page === currentPage
                                ? 'bg-[#EAF2FF] font-medium text-[#0B62E0]'
                                : 'text-[#334E68] hover:bg-[#F3F8FF]'
                            }`}
                          >
                            Page {page}
                          </button>
                        ))}
                      </div>
                    </PopoverContent>
                  </Popover>

                  <Button
                    type="button"
                    size="icon"
                    variant="outline"
                    disabled={currentPage >= lastPage}
                    onClick={() => {
                      const url = new URL(window.location.href);
                      url.searchParams.set('page', String(currentPage + 1));
                      router.get(url.pathname + url.search, {}, { preserveState: true, preserveScroll: true });
                    }}
                    aria-label="Next page"
                    className="h-8 w-8 shrink-0 rounded-md border-[#CFE3FF] text-[#0B3D91] text-sm hover:bg-[#F3F8FF]"
                  >
                    <ChevronRight className="h-4 w-4" />
                  </Button>
                </div>
              ) : (
                <Pagination className="justify-end w-auto mx-0">
                  <PaginationContent className="gap-1">
                    {paginationLinks.map((link, index) => {
                      const isPrev = index === 0;
                      const isNext = index === paginationLinks.length - 1;

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
                            className={`cursor-pointer font-medium ${
                              link.active
                                ? '!bg-[#0F6FFF] !text-white font-bold hover:!bg-[#0B3D91] hover:!text-white shadow-sm'
                                : 'text-[#334E68] hover:bg-[#EAF2FF] hover:text-[#0B62E0]'
                            }`}
                          >
                            {link.label}
                          </PaginationLink>
                        </PaginationItem>
                      );
                    })}
                  </PaginationContent>
                </Pagination>
              )}
            </CardFooter>
          )}
        </Card>
      </div>

      {/* Import preset dialog */}
      {isImportDialogOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/45 p-4" role="dialog" aria-modal="true" aria-labelledby="import-dialog-title">
          <div className="w-full max-w-lg rounded-xl border border-[#CFE3FF] bg-white p-6 shadow-2xl">
            <div className="mb-5 flex items-start justify-between gap-4">
              <div>
                <h2 id="import-dialog-title" className="text-lg font-bold text-[#0B3D91]">Import Graduate Ledger</h2>
                <p className="mt-1 text-sm text-[#5C7A9E]">
                  The importer uses each row&apos;s course and academic term first. Optional presets are used only when a row is blank or cannot be recognized.
                </p>
              </div>
              <button
                type="button"
                className="rounded-md p-1 text-[#5C7A9E] hover:bg-[#F3F8FF] hover:text-[#0B3D91]"
                onClick={() => {
                  if (!isImporting) {
                    setIsImportDialogOpen(false);
                    importForm.clearErrors();
                  }
                }}
                aria-label="Close import dialog"
              >
                <XCircle className="h-5 w-5" />
              </button>
            </div>

            <div className="space-y-4">
              <div>
                <label htmlFor="ledger-import-file" className="mb-1.5 block text-sm font-semibold text-[#0B3D91]">
                  Spreadsheet file <span className="text-red-500">*</span>
                </label>
                <Input
                  id="ledger-import-file"
                  type="file"
                  accept=".csv,.xlsx,.xls"
                  disabled={isImporting}
                  className="border-[#CFE3FF]"
                  onChange={(event) => importForm.setData('file', event.target.files?.[0] ?? null)}
                />
                {importForm.errors.file && <p className="mt-1 text-xs text-red-600">{importForm.errors.file}</p>}
              </div>

              <div>
                <label htmlFor="preset-course" className="mb-1.5 block text-sm font-semibold text-[#0B3D91]">
                  Fallback course <span className="font-normal text-[#7FA6D6]">(optional)</span>
                </label>
                <select
                  id="preset-course"
                  value={importForm.data.preset_course_id}
                  disabled={isImporting}
                  onChange={(event) => importForm.setData('preset_course_id', event.target.value)}
                  className="h-10 w-full rounded-md border border-[#CFE3FF] bg-white px-3 text-sm text-[#0B3D91]"
                >
                  <option value="">No fallback — skip unmatched courses</option>
                  {courses.map((item) => (
                    <option key={item.id} value={item.id}>{item.code}</option>
                  ))}
                </select>
                {importForm.errors.preset_course_id && <p className="mt-1 text-xs text-red-600">{importForm.errors.preset_course_id}</p>}
              </div>

              <div>
                <label htmlFor="preset-term" className="mb-1.5 block text-sm font-semibold text-[#0B3D91]">
                  Fallback academic term <span className="font-normal text-[#7FA6D6]">(optional)</span>
                </label>
                <select
                  id="preset-term"
                  value={importForm.data.preset_academic_term_id}
                  disabled={isImporting}
                  onChange={(event) => importForm.setData('preset_academic_term_id', event.target.value)}
                  className="h-10 w-full rounded-md border border-[#CFE3FF] bg-white px-3 text-sm text-[#0B3D91]"
                >
                  <option value="">No fallback — skip unmatched terms</option>
                  {academicTerms.map((term) => (
                    <option key={term.id} value={term.id}>{term.school_year} — {term.semester}</option>
                  ))}
                </select>
                {importForm.errors.preset_academic_term_id && <p className="mt-1 text-xs text-red-600">{importForm.errors.preset_academic_term_id}</p>}
              </div>

              <div className="rounded-lg border border-[#B9D8FF] bg-[#F3F8FF] p-3 text-xs leading-relaxed text-[#334E68]">
                Course spellings such as <strong>MS-MATH</strong>, <strong>MS Math</strong>, and <strong>M.S. Math</strong> are normalized before matching. Importing will not create new course or academic-term master records.
              </div>
            </div>

            <div className="mt-6 flex justify-end gap-2">
              <Button
                type="button"
                variant="outline"
                disabled={isImporting}
                className="border-[#CFE3FF] text-[#0B3D91]"
                onClick={() => {
                  setIsImportDialogOpen(false);
                  importForm.reset();
                  importForm.clearErrors();
                }}
              >
                Cancel
              </Button>
              <Button
                type="button"
                disabled={!importForm.data.file || isImporting}
                className="bg-[#0F6FFF] text-white hover:bg-[#0B5DDB]"
                onClick={handleImport}
              >
                {isImporting && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                {isImporting ? 'Importing...' : 'Start Import'}
              </Button>
            </div>
          </div>
        </div>
      )}

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

      <StudentBalanceDrawer
        open={drawerSelection !== null}
        studentId={drawerSelection?.studentId ?? null}
        selectedTransactionId={drawerSelection?.transactionId ?? null}
        onOpenChange={(open) => {
          if (!open) {
            setDrawerSelection(null);
          }
        }}
      />
    </>
  );
}

function handleDelete(id: string | number, name: string) {
  if (confirm(`Delete transaction for ${name}?`)) {
    router.delete(`/graduate-ledger/${id}`);
  }
}
