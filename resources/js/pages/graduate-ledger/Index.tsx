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

export interface PendingOpItem {
  id: number;
  reference_number: string;
  full_name: string;
  email?: string;
  college?: string;
  particulars: string;
  amount: number;
  ref_document_or_number?: string;
  status: string;
  created_at: string;
  matched_student_id?: number | null;
}

export interface StudentOption {
  id: number;
  name: string;
  raw_name_from_csv?: string;
}

export interface CourseOption {
  id: number;
  code: string;
}

export interface TermOption {
  id: number;
  school_year: string;
  semester: string;
}

export interface IndexProps {
  records: LedgerPaginator;
  filters: {
    search?: string;
    school_year?: string;
    semester?: string;
    course?: string;
    date_from?: string;
    date_to?: string;
  };
  stats: {
    totalStudents: number;
    totalAssessments: number;
    totalPayments: number;
    totalAdjustments: number;
    outstandingBalance: number;
  };
  filterOptions: {
    courses: string[];
    schoolYears: string[];
    semesters: string[];
  };
  pendingOpItems?: PendingOpItem[];
  studentOptions?: StudentOption[];
  courseList?: CourseOption[];
  academicTermList?: TermOption[];
}

function PendingOpRow({
  item,
  studentOptions,
  courseList,
  academicTermList,
}: {
  item: PendingOpItem;
  studentOptions: StudentOption[];
  courseList: CourseOption[];
  academicTermList: TermOption[];
}) {
  const [selectedStudentId, setSelectedStudentId] = useState<string>(
    item.matched_student_id ? String(item.matched_student_id) : 'new'
  );
  const [newStudentName, setNewStudentName] = useState<string>(
    selectedStudentId === 'new' ? item.full_name : ''
  );
  const [selectedCourseId, setSelectedCourseId] = useState<string>(
    courseList[0]?.id ? String(courseList[0].id) : ''
  );
  const [selectedTermId, setSelectedTermId] = useState<string>(
    academicTermList[0]?.id ? String(academicTermList[0].id) : ''
  );
  const [particulars, setParticulars] = useState<string>(item.particulars || 'Tuition');
  const [entryType, setEntryType] = useState<'ar' | 'payment' | 'adjustment'>('payment');
  const [isPosting, setIsPosting] = useState(false);

  const handlePost = () => {
    if (isPosting) return;
    setIsPosting(true);

    router.post(
      '/graduate-ledger/post-op-item',
      {
        student_id: selectedStudentId === 'new' ? null : selectedStudentId,
        new_student: selectedStudentId === 'new' ? newStudentName : null,
        course_id: selectedCourseId,
        academic_term_id: selectedTermId,
        entry_type: entryType,
        particulars: particulars,
        amount: item.amount,
        reference_or_jev_number: item.ref_document_or_number,
      },
      {
        preserveScroll: true,
        onFinish: () => setIsPosting(false),
      }
    );
  };

  return (
    <tr className="hover:bg-[#F8FAFC] border-b border-[#E2E8F0] text-xs text-[#1E293B]">
      <td className="p-3 font-semibold text-[#0B3D91]">
        <div>{item.full_name}</div>
        <div className="text-[10px] font-normal text-gray-500">{item.email}</div>
      </td>

      <td className="p-3">
        <select
          value={selectedStudentId}
          onChange={(e) => {
            setSelectedStudentId(e.target.value);
            if (e.target.value === 'new') {
              setNewStudentName(item.full_name);
            }
          }}
          className="w-full text-xs rounded border border-[#CFE3FF] p-1.5 bg-white text-[#0B3D91] font-medium"
        >
          <option value="new">+ Create New Student ({item.full_name})</option>
          {studentOptions.map((st) => {
            const displayName = (st.raw_name_from_csv || st.name || `${st.last_name || ''}, ${st.first_name || ''}`).trim();
            return (
              <option key={st.id} value={st.id}>
                {displayName && displayName !== ',' ? displayName : `Student #${st.id}`}
              </option>
            );
          })}
        </select>
        {selectedStudentId === 'new' && (
          <Input
            value={newStudentName}
            onChange={(e) => setNewStudentName(e.target.value)}
            className="mt-1 h-7 text-xs border-[#CFE3FF]"
            placeholder="Student Name"
          />
        )}
      </td>

      <td className="p-3">
        <select
          value={selectedCourseId}
          onChange={(e) => setSelectedCourseId(e.target.value)}
          className="text-xs rounded border border-[#CFE3FF] p-1.5 bg-white"
        >
          {courseList.map((c) => (
            <option key={c.id} value={c.id}>
              {c.code}
            </option>
          ))}
        </select>
      </td>

      <td className="p-3">
        <select
          value={selectedTermId}
          onChange={(e) => setSelectedTermId(e.target.value)}
          className="text-xs rounded border border-[#CFE3FF] p-1.5 bg-white"
        >
          {academicTermList.map((t) => (
            <option key={t.id} value={t.id}>
              {t.school_year} - {t.semester}
            </option>
          ))}
        </select>
      </td>

      <td className="p-3">
        <select
          value={entryType}
          onChange={(e) => setEntryType(e.target.value as any)}
          className="text-xs font-semibold rounded border border-[#CFE3FF] p-1.5 bg-white"
        >
          <option value="payment">Payment</option>
          <option value="ar">AR (Assessment)</option>
          <option value="adjustment">Adjustment</option>
        </select>
      </td>

      <td className="p-3 font-semibold text-[#0B3D91]">
        <select
          value={particulars}
          onChange={(e) => setParticulars(e.target.value)}
          className="text-xs rounded border border-[#CFE3FF] p-1.5 bg-white font-semibold text-[#0B3D91] w-full"
        >
          <option value="Tuition">Tuition</option>
          <option value="Miscellaneous">Miscellaneous</option>
          <option value="Registration">Registration</option>
          <option value="Comprehensive Exam">Comprehensive Exam</option>
          <option value="Laboratory">Laboratory</option>
        </select>
        <div className="text-[10px] text-gray-500 font-mono mt-0.5">{item.ref_document_or_number}</div>
      </td>

      <td className="p-3 font-bold text-emerald-700">
        ₱{item.amount.toLocaleString('en-PH', { minimumFractionDigits: 2 })}
      </td>

      <td className="p-3 text-right">
        <Button
          size="sm"
          disabled={isPosting}
          onClick={handlePost}
          className="bg-emerald-600 hover:bg-emerald-700 text-white h-7 text-xs px-3"
        >
          {isPosting ? <Loader2 className="h-3 w-3 animate-spin mr-1" /> : <CheckCircle2 className="h-3.5 w-3.5 mr-1" />}
          Verify & Post
        </Button>
      </td>
    </tr>
  );
}

export default function Index({
  records,
  filters,
  stats,
  filterOptions,
  pendingOpItems = [],
  studentOptions = [],
  courseList = [],
  academicTermList = [],
}: IndexProps) {
  const rows: LedgerRecord[] = records?.data ?? [];
  const importForm = useForm<{ file: File | null }>({ file: null });

  const [activeTab, setActiveTab] = useState<'official' | 'pending'>('official');

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

        {/* Sub-Tabs Navigation */}
        <div className="flex items-center gap-2 border-b border-[#CFE3FF] pb-2">
          <button
            type="button"
            onClick={() => setActiveTab('official')}
            className={`px-4 py-2 text-sm font-semibold rounded-t-lg transition-colors flex items-center gap-2 ${
              activeTab === 'official'
                ? 'bg-[#0F6FFF] text-white shadow-xs'
                : 'bg-white text-[#5C7A9E] border border-[#CFE3FF] hover:bg-[#F3F8FF]'
            }`}
          >
            <span>📋 Official Transaction Ledger</span>
            <Badge variant="secondary" className={activeTab === 'official' ? 'bg-white/20 text-white' : 'bg-[#EAF2FF] text-[#0B62E0]'}>
              {totalRecordCount.toLocaleString()}
            </Badge>
          </button>

          <button
            type="button"
            onClick={() => setActiveTab('pending')}
            className={`px-4 py-2 text-sm font-semibold rounded-t-lg transition-colors flex items-center gap-2 ${
              activeTab === 'pending'
                ? 'bg-[#0F6FFF] text-white shadow-xs'
                : 'bg-white text-[#5C7A9E] border border-[#CFE3FF] hover:bg-[#F3F8FF]'
            }`}
          >
            <span>⏳ Pending OP Verification (Tuition / Misc)</span>
            {pendingOpItems.length > 0 ? (
              <Badge className="bg-amber-500 text-white font-bold animate-pulse">
                {pendingOpItems.length} Waiting
              </Badge>
            ) : (
              <Badge variant="outline" className="text-gray-400">
                0
              </Badge>
            )}
          </button>
        </div>

        {/* Tab 1: Official Ledger or Tab 2: Pending Verification Queue */}
        {activeTab === 'pending' ? (
          <Card className="border border-[#CFE3FF] bg-white">
            <CardHeader className="border-b border-[#CFE3FF] pb-4">
              <CardTitle className="text-md text-[#0B3D91] flex items-center gap-2">
                <span>Pending Order of Payment Verification Queue</span>
                <Badge variant="outline" className="bg-amber-50 text-amber-700 border-amber-300">
                  Tuition & Miscellaneous Fees Only
                </Badge>
              </CardTitle>
              <CardDescription className="text-[#5C7A9E]">
                Review incoming processed Order of Payment requests, match student name, and post to official ledger.
              </CardDescription>
            </CardHeader>
            <CardContent className="p-0 overflow-x-auto">
              {pendingOpItems.length === 0 ? (
                <div className="p-12 text-center text-[#5C7A9E] space-y-2">
                  <CheckCircle2 className="h-10 w-10 text-emerald-500 mx-auto" />
                  <div className="font-semibold text-md text-[#0B3D91]">All OP Payments Are Verified!</div>
                  <p className="text-xs text-gray-500">There are no pending Tuition or Miscellaneous Order of Payment requests waiting for verification.</p>
                </div>
              ) : (
                <table className="w-full text-left border-collapse">
                  <thead className="bg-[#F1F5F9] text-[11px] font-bold text-[#475569] uppercase tracking-wider border-b border-[#CBD5E1]">
                    <tr>
                      <th className="p-3">Raw Name (OP Form)</th>
                      <th className="p-3 w-64">Match Student in Ledger</th>
                      <th className="p-3">Course</th>
                      <th className="p-3">Academic Term</th>
                      <th className="p-3">Type</th>
                      <th className="p-3">Particulars & Ref #</th>
                      <th className="p-3">Amount</th>
                      <th className="p-3 text-right">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    {pendingOpItems.map((item) => (
                      <PendingOpRow
                        key={item.id}
                        item={item}
                        studentOptions={studentOptions}
                        courseList={courseList}
                        academicTermList={academicTermList}
                      />
                    ))}
                  </tbody>
                </table>
              )}
            </CardContent>
          </Card>
        ) : (
          <Card className="border border-[#CFE3FF] bg-white">
          <CardHeader className="border-b border-[#CFE3FF] pb-4">
            <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
              <div>
                <CardTitle className="text-md text-[#0B3D91]">Transaction Ledger</CardTitle>
                <CardDescription className="text-[#7FA6D6] mt-0.5">
                  Showing {rows.length} of {totalRecordCount} record{totalRecordCount === 1 ? '' : 's'}
                </CardDescription>
              </div>

              <form onSubmit={handleSearchSubmit} className="flex flex-wrap items-center gap-2">
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
            </div>
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
                        <Badge variant="outline" className={getEntryTypeBadge(r.arPayment)}>
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
                    className="w-14 h-7 rounded border border-[#CFE3FF] bg-white px-2 text-center text-xs text-[#0B3D91] font-semibold focus:bg-white focus:text-[#0B3D91] focus:border-[#0B62E0] focus:outline-none focus:ring-1 focus:ring-[#0B62E0] [color-scheme:light]"
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
            </CardFooter>
          )}
        </Card>
        )}
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