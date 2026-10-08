import { Head, router, useForm } from '@inertiajs/react';
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
  XCircle,
  Filter,
  Loader2,
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
  ChevronDown,
  Columns3,
  Mail,
  CheckSquare,
  Square,
  Calendar as CalendarIcon,
} from 'lucide-react';
import React, { useState, useEffect, useMemo, useRef } from 'react';
import StudentBalanceDrawer from './StudentBalanceDrawer';
import { emailRecipients, sendBulkEmail } from '@/actions/App/Http/Controllers/LawSchoolLedgerController';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogMedia,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  CardDescription,
  CardFooter,
} from '@/components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
  Pagination,
  PaginationContent,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
} from '@/components/ui/pagination';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { flashToast } from '@/utils/flashToast';

export interface LawLedgerRecord {
  id: string | number;
  studentId: number | string | null;
  studentNumber: string;
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
  entryType: string;
  amount: number;
  status: string;
  remark: string;
  inputBy: string;
  latinHonor?: string | null;
  discountAmount?: number;
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

function formatDateInput(value?: string | null) {
  if (!value) {
    return '';
  }

  const normalized = String(value).trim();

  if (!normalized) {
    return '';
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

function typeBadgeVariant(type: string | null | undefined) {
  const t = (type ?? '').toLowerCase();

  if (t === 'ar' || t === 'assessment') {
    return 'bg-blue-50 text-blue-700 border-blue-200';
  }

  if (t === 'payment' || t === 'p') {
    return 'bg-emerald-50 text-emerald-700 border-emerald-200';
  }

  if (t === 'adjustment' || t === 'adj') {
    return 'bg-amber-50 text-amber-700 border-amber-200';
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
    ar_or_payment?: string;
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
    statuses: string[];
    types?: string[];
  };
}

const defaultVisibleColumns = {
  studentNumber: true,
  course: true,
  schoolYear: true,
  semester: true,
  units: true,
  transactionDate: true,
  referenceNo: true,
  particulars: true,
  feeRate: true,
  entryType: true,
  status: true,
  remark: true,
  inputBy: true,
};

type OptionalColumn = keyof typeof defaultVisibleColumns;

const optionalColumnLabels: Record<OptionalColumn, string> = {
  studentNumber: 'Student ID',
  course: 'Course',
  schoolYear: 'School Year',
  semester: 'Semester/Summer',
  units: 'Units',
  transactionDate: 'Transaction Date',
  referenceNo: 'Reference Number',
  particulars: 'Particulars',
  feeRate: 'Tuition/Unit or Reg. & Misc. Fee',
  entryType: 'AR/Payment',
  status: 'Status',
  remark: 'Remark',
  inputBy: 'Input By',
};

export default function Index({ records, filters, stats, filterOptions }: IndexProps) {
  const rows: LawLedgerRecord[] = records?.data ?? [];
  const importForm = useForm<{ file: File | null }>({ file: null });

  const [isImporting, setIsImporting] = useState(false);
  const [importProgress, setImportProgress] = useState(0);
  const [importSuccess, setImportSuccess] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState<LawLedgerRecord | null>(null);
  const [isDeleting, setIsDeleting] = useState(false);
  const [honorTarget, setHonorTarget] = useState<LawLedgerRecord | null>(null);
  const [selectedHonor, setSelectedHonor] = useState<'SUMMA' | 'MAGNA' | 'CUM_LAUDE' | ''>('');
  const [isApplyingHonor, setIsApplyingHonor] = useState(false);
  const [drawerSelection, setDrawerSelection] = useState<{
    studentId: number;
    transactionId: string | number;
  } | null>(null);
  const [showFilters, setShowFilters] = useState(() =>
    Boolean(
      filters?.school_year ||
      filters?.semester_or_summer ||
      filters?.course ||
      filters?.status ||
      filters?.ar_or_payment ||
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
        window.localStorage.getItem('law-ledger.visible-columns') ?? '{}',
      ) as Partial<typeof defaultVisibleColumns>;

      return { ...defaultVisibleColumns, ...saved };
    } catch {
      return defaultVisibleColumns;
    }
  });

  const toggleColumn = (column: OptionalColumn) => {
    setVisibleColumns((current) => {
      const next = { ...current, [column]: !current[column] };
      window.localStorage.setItem('law-ledger.visible-columns', JSON.stringify(next));

      return next;
    });
  };

  const visibleColumnCount = 3 + Object.values(visibleColumns).filter(Boolean).length;

  const [isEmailModalOpen, setIsEmailModalOpen] = useState(false);
  const [emailTarget, setEmailTarget] = useState<'all_matching' | 'specific' | 'all_outstanding'>('all_matching');
  const [emailSearch, setEmailSearch] = useState('');
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
  const [allRecipients, setAllRecipients] = useState<Array<{
    id: number;
    student_number: string | null;
    full_name: string;
    email: string;
    balance: number;
    balance_status: string;
  }>>([]);
  const [isLoadingRecipients, setIsLoadingRecipients] = useState(false);
  const [emailSubject, setEmailSubject] = useState('');
  const [emailNote, setEmailNote] = useState('');
  const [examPeriod, setExamPeriod] = useState<'Midterm' | 'Final' | ''>('');
  const [examDeadline, setExamDeadline] = useState('');
  const [isSendingEmails, setIsSendingEmails] = useState(false);
  const [showEmailProcessingModal, setShowEmailProcessingModal] = useState(false);
  const [isEmailProcessingDone, setIsEmailProcessingDone] = useState(false);
  const [emailProcessingSeconds, setEmailProcessingSeconds] = useState(0);
  const [emailProcessingMessage, setEmailProcessingMessage] = useState('');

  useEffect(() => {
    if (!showEmailProcessingModal || isEmailProcessingDone) {
      return;
    }

    const timer = setInterval(() => {
      setEmailProcessingSeconds((seconds) => seconds + 1);
    }, 1000);

    return () => clearInterval(timer);
  }, [showEmailProcessingModal, isEmailProcessingDone]);

  useEffect(() => {
    if (!isEmailModalOpen) {
      return;
    }

    const fetchRecipients = async () => {
      setIsLoadingRecipients(true);

      try {
        const params = new URLSearchParams();
        Object.entries(filterState).forEach(([key, value]) => {
          if (value && value.trim()) {
            params.append(key, value.trim());
          }
        });

        const res = await fetch(emailRecipients.url({ query: Object.fromEntries(params) }), {
          headers: { Accept: 'application/json' },
        });

        if (!res.ok) {
          throw new Error(`HTTP ${res.status}`);
        }

        const data = (await res.json()) as typeof allRecipients;
        setAllRecipients(data);
        setSelectedIds(new Set(data.map((r) => r.id)));
      } catch (e) {
        flashToast('error', 'Failed to load recipients.');
      } finally {
        setIsLoadingRecipients(false);
      }
    };

    fetchRecipients();
  }, [isEmailModalOpen]);

  const handleOpenEmailModal = () => {
    setEmailSubject('');
    setEmailNote('');
    setExamPeriod('');
    setExamDeadline('');
    setEmailSearch('');
    setEmailTarget('all_matching');
    setSelectedIds(new Set());
    setIsEmailModalOpen(true);
  };

  const escapeHtml = (value: string) =>
    value
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');

  const buildEmailPreviewHtml = (note: string, period: string, deadline: string) => {
    const noteHtml = note.trim()
      ? `<p>${escapeHtml(note).replace(/\n/g, '<br>')}</p>`
      : '';
    const examHtml = (period || deadline) ?
      `<p class="text-sm text-slate-500">Exam period: ${escapeHtml(period)}.${deadline ? ' Payment deadline: ' + escapeHtml(formatDateInput(deadline || '')) + '.' : ''}</p>`
      : '';

    return `<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1e293b; margin:0; padding:16px;">
    <p>Dear Student,</p>
    <p>
        Please find attached your Statement of Account issued by the
        NORSU Accounting Office. This statement reflects your assessed
        charges, payments, and adjustments recorded in the Law School ledger.
    </p>
    ${noteHtml}
    ${examHtml}
    <p>
        Should you have any questions regarding your balance, please visit the
        Accounting Office with your valid ID.
    </p>
    <p>Regards,<br>NORSU Accounting Office</p>
</body>
</html>`;
  };

  const toggleSelectId = (id: number) => {
    setSelectedIds((prev) => {
      const next = new Set(prev);

      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }

      return next;
    });
  };

  const getEffectiveRecipientIds = (): number[] => {
    if (emailTarget === 'specific') {
      return Array.from(selectedIds);
    }

    return allRecipients
      .filter((r) => {
        if (emailTarget === 'all_outstanding' && r.balance <= 0) {
          return false;
        }

        return selectedIds.has(r.id);
      })
      .map((r) => r.id);
  };

  const visibleRecipients = allRecipients
    .filter((r) => {
      if (emailSearch.trim()) {
        const q = emailSearch.toLowerCase();

        return r.full_name.toLowerCase().includes(q)
          || r.email.toLowerCase().includes(q)
          || (r.student_number ?? '').toLowerCase().includes(q);
      }

      return true;
    });

  const bulkRecipients = useMemo(() =>
    allRecipients.filter((r) => {
      if (emailSearch.trim()) {
        const q = emailSearch.toLowerCase();

        return r.full_name.toLowerCase().includes(q)
          || r.email.toLowerCase().includes(q)
          || (r.student_number ?? '').toLowerCase().includes(q);
      }

      return true;
    }),
    [allRecipients, emailSearch]
  );

  const prevEmailTargetRef = useRef<string | null>(null);
  useEffect(() => {
    const isBulkMode = emailTarget !== 'specific';

    if (isBulkMode && prevEmailTargetRef.current !== emailTarget) {
      setSelectedIds(new Set(bulkRecipients.map((r) => r.id)));
    }

    prevEmailTargetRef.current = emailTarget;
  }, [emailTarget, bulkRecipients]);

  const handleSendEmails = (e: React.FormEvent) => {
    e.preventDefault();

    if (isSendingEmails) {
      return;
    }

    const targetIds = getEffectiveRecipientIds();

    if (targetIds.length === 0) {
      flashToast('error', 'No recipients selected.');

      return;
    }

    const params: Record<string, string | number[]> = {};
    Object.entries(filterState).forEach(([key, value]) => {
      if (value && value.trim()) {
        params[key] = value.trim();
      }
    });
    params.student_ids = targetIds;

    if (emailSubject) {
      params.subject = emailSubject;
    }

    if (emailNote) {
      params.note = emailNote;
    }

    if (examPeriod) {
      params.exam_period = examPeriod;
    }

    if (examDeadline) {
      params.exam_deadline = examDeadline;
    }

    setIsSendingEmails(true);
    setShowEmailProcessingModal(true);
    setIsEmailProcessingDone(false);
    setEmailProcessingSeconds(0);
    setEmailProcessingMessage(`Sending statement of account to ${targetIds.length} student(s). Please do not close this window.`);
    setIsEmailModalOpen(false);
    router.post(sendBulkEmail.url(), params, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: (page) => {
        const flash = page.props.flash as { success?: string; error?: string } | undefined;

        if (flash?.success) {
          setEmailProcessingMessage(flash.success);
          flashToast('success', flash.success);
        } else if (flash?.error) {
          setEmailProcessingMessage(flash.error);
          flashToast('error', flash.error);
        } else {
          setEmailProcessingMessage('Emails sent successfully.');
          flashToast('success', 'Emails sent successfully.');
        }

        setIsEmailProcessingDone(true);
        setEmailSubject('');
        setEmailNote('');
        setExamPeriod('');
        setExamDeadline('');
      },
      onError: (errors) => {
        const firstError = Object.values(errors ?? {})[0];
        const message = typeof firstError === 'string' ? firstError : 'Failed to send emails. Please try again.';

        setEmailProcessingMessage(message);
        setIsEmailProcessingDone(true);
        flashToast('error', message);
      },
      onFinish: () => setIsSendingEmails(false),
    });
  };

  const handleImportFile = (file: File | null, inputEl: HTMLInputElement) => {
    if (!file || isImporting) {
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

    importForm.setData('file', file);
    importForm.post('/law-ledger/import', {
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

  const [filterState, setFilterState] = useState({
    search:      filters?.search      ?? '',
    school_year: filters?.school_year ?? '',
    semester_or_summer: filters?.semester_or_summer ?? '',
    course:      filters?.course      ?? '',
    status:      filters?.status      ?? '',
    ar_or_payment:  filters?.ar_or_payment  ?? '',
    date_from:      filters?.date_from      ?? '',
    date_to:        filters?.date_to        ?? '',
    balance_status: filters?.balance_status ?? '',
  });

  const [goToPage, setGoToPage] = useState('');
  const [isFiltering, setIsFiltering] = useState(false);

  const searchQuery      = filterState.search;
  const schoolYear       = filterState.school_year;
  const semester         = filterState.semester_or_summer;
  const course           = filterState.course;
  const status           = filterState.status;
  const type             = filterState.ar_or_payment;
  const dateFrom         = filterState.date_from;
  const dateTo           = filterState.date_to;
  const balanceStatus    = filterState.balance_status;

  const activeAdvancedFilters = [
    schoolYear && { key: 'school_year', label: `School year: ${schoolYear}` },
    semester && { key: 'semester_or_summer', label: semester },
    course && { key: 'course', label: `Course: ${course}` },
    status && { key: 'status', label: `Status: ${status}` },
    type && { key: 'ar_or_payment', label: `Type: ${type}` },
    balanceStatus && {
      key: 'balance_status',
      label: balanceStatus === 'with_balance' ? 'Outstanding balance' : 'Fully paid',
    },
    dateFrom && { key: 'date_from', label: `From: ${formatTransactionDate(dateFrom)}` },
    dateTo && { key: 'date_to', label: `To: ${formatTransactionDate(dateTo)}` },
  ].filter(Boolean) as { key: keyof typeof filterState; label: string }[];

  const applyFilters = (overrides: Record<string, string> = {}) => {
    const merged = { ...filterState, ...overrides };
    setFilterState(merged);

    const params: Record<string, string> = {};
    Object.entries(merged).forEach(([key, value]) => {
      if (value && value.trim()) {
        params[key] = value.trim();
      }
    });

    router.get('/law-ledger', params, {
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
      router.get(`/law-ledger?${currentParams.toString()}`, {}, { preserveState: true, preserveScroll: true });
    }
  };

  const confirmDelete = () => {
    if (!deleteTarget || isDeleting) {
      return;
    }

    setIsDeleting(true);
    router.delete(`/law-ledger/${deleteTarget.id}`, {
      preserveScroll: true,
      onFinish: () => {
        setIsDeleting(false);
        setDeleteTarget(null);
      },
    });
  };

  const applyHonorDiscount = () => {
    if (!honorTarget || !selectedHonor || isApplyingHonor) {
      return;
    }

    setIsApplyingHonor(true);
    router.post(`/law-ledger/${honorTarget.id}/apply-honor`, {
      latin_honor: selectedHonor,
    }, {
      preserveScroll: true,
      onFinish: () => {
        setIsApplyingHonor(false);
        setHonorTarget(null);
        setSelectedHonor('');
      },
    });
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
    <div className="max-w-7xl mx-auto space-y-6">
      <Head title="Law School Ledger" />

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
            <Button variant="outline" className="h-9 border-[#CFE3FF] text-[#0B3D91] hover:bg-[#F3F8FF]" onClick={handleOpenEmailModal}>
              <Mail className="h-4 w-4 mr-1.5" />
              Send Email
            </Button>
             <Button
               variant="outline"
               className="h-9 border-[#CFE3FF] text-[#0B3D91] hover:bg-[#F3F8FF]"
               onClick={() => {
                 const params = new URLSearchParams();
                  const current = {
                    search: searchQuery,
                    school_year: schoolYear,
                    semester_or_summer: semester,
                    course: course,
                    status: status,
                    ar_or_payment: type,
                    date_from: dateFrom,
                    date_to: dateTo,
                    balance_status: balanceStatus,
                  };

                  Object.entries(current).forEach(([key, value]) => {
                   if (value && value.trim()) {
                     params.append(key, value.trim());
                   }
                 });

                 window.open(`/law-ledger/export?${params.toString()}`, '_blank');
               }}
             >
               Export Excel/CSV
             </Button>

<Button className="bg-[#0F6FFF] hover:bg-[#0B5DDB] text-white" onClick={() => router.get('/law-ledger/new-transaction')}>
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
              <p className="text-[10px] text-[#8AA8CC] mt-1">Unique law school students</p>
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

        {/* Summary Analytics
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
                    width: `${totalAssessments > 0 ? Math.min((outstandingBalance / totalAssessments) * 100, 100) : 0}%`
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
                    {totalAssessments > 0
                      ? `${((totalPayments / totalAssessments) * 100).toFixed(1)}%`
                      : '0.0%'}
                  </span>
                </div>
                <div className="mt-2 h-2 bg-slate-100 rounded-full overflow-hidden">
                  <div
                    className="h-full bg-blue-500 rounded-full transition-all duration-500"
                    style={{
                      width: `${totalAssessments > 0 ? Math.min((totalPayments / totalAssessments) * 100, 100) : 0}%`
                    }}
                  />
                </div>
              </div>

              <div className="p-4 bg-white rounded-lg border border-slate-200">
                <div className="flex items-center justify-between">
                  <span className="text-sm text-slate-600">Average Transaction</span>
                  <span className="text-sm font-medium text-slate-900">
                    {totalRecordCount > 0
                      ? currency((totalAssessments + totalPayments) / totalRecordCount)
                      : '₱0.00'}
                  </span>
                </div>
                <p className="text-xs text-slate-500 mt-1">Per record</p>
              </div>

              <div className="p-4 bg-white rounded-lg border border-slate-200">
                <div className="flex items-center justify-between">
                  <span className="text-sm text-slate-600">Records This Page</span>
                  <span className="text-sm font-medium text-slate-900">{rows.length}</span>
                </div>
                <p className="text-xs text-slate-500 mt-1">of {totalRecordCount.toLocaleString()} total</p>
              </div>
            </div>
          </CardContent>
        </Card> */}

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
                    placeholder="Filter by name, student ID, ref #, particulars..."
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
                      onChange={(e) => applyFilters({ semester_or_summer: e.target.value })}
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
                      value={status}
                      onChange={(e) => applyFilters({ status: e.target.value })}
                      className="h-9 rounded-md border border-[#CFE3FF] bg-white px-3 text-sm text-[#0B3D91]"
                    >
                      <option value="">All Statuses</option>
                      {(filterOptions?.statuses ?? []).map((st) => (
                        <option key={st} value={st}>{st}</option>
                      ))}
                    </select>

                    <select
                      value={type}
                      onChange={(e) => applyFilters({ ar_or_payment: e.target.value })}
                      className="h-9 rounded-md border border-[#CFE3FF] bg-white px-3 text-sm text-[#0B3D91]"
                    >
                      <option value="">All Types (AR/Payment)</option>
                      {(filterOptions?.types ?? ['AR', 'Payment', 'Adjustment']).map((t) => (
                        <option key={t} value={t}>{t}</option>
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
                          semester_or_summer: '',
                          course: '',
                          status: '',
                          ar_or_payment: '',
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
                          semester_or_summer: '',
                          course: '',
                          status: '',
                          ar_or_payment: '',
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
                      {visibleColumns.studentNumber && <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 pl-2 whitespace-nowrap">Student ID</th>}
                      <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Name</th>
                      {visibleColumns.course && <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Course</th>}
                      {visibleColumns.schoolYear && <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">School Year</th>}
                      {visibleColumns.semester && <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Semester/Summer</th>}
                      {visibleColumns.units && <th className="text-right font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Units</th>}
                      {visibleColumns.transactionDate && <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Trans. Date</th>}
                      {visibleColumns.referenceNo && <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Ref. (JEV/OR #)</th>}
                      {visibleColumns.particulars && <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Particulars</th>}
                      {visibleColumns.feeRate && <th className="text-right font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Tuition/Unit or Reg. & Misc. Fee</th>}
                      {visibleColumns.entryType && <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">AR/Payment</th>}
                      <th className="text-right font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Amount</th>
                      {visibleColumns.status && <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Status</th>}
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
                          tabIndex={r.studentId ? 0 : undefined}
                          aria-label={r.studentId ? `View ${r.name}'s balance and transaction history` : undefined}
                          onClick={() => {
                            const studentId = Number(r.studentId);
                            if (!Number.isFinite(studentId) || studentId <= 0) {
                              return;
                            }
                            setDrawerSelection({ studentId, transactionId: r.id });
                          }}
                          onKeyDown={(event) => {
                            if (event.key !== 'Enter' && event.key !== ' ') {
                              return;
                            }
                            event.preventDefault();
                            const studentId = Number(r.studentId);
                            if (!Number.isFinite(studentId) || studentId <= 0) {
                              return;
                            }
                            setDrawerSelection({ studentId, transactionId: r.id });
                          }}
                          className="cursor-pointer border-b border-[#EAF2FF] transition-colors hover:bg-[#F3F8FF] focus-visible:bg-[#F3F8FF] focus-visible:outline-2 focus-visible:outline-[#0F6FFF]"
                        >
                          {visibleColumns.studentNumber && <td className="py-2 pr-4 pl-2 whitespace-nowrap text-[#334E68]">{r.studentNumber || r.studentId}</td>}
                          <td className="py-2 pr-4 font-medium whitespace-nowrap text-[#0B3D91]">{r.name}</td>
                          {visibleColumns.course && <td className="py-2 pr-4 text-[#334E68]">{r.course}</td>}
                          {visibleColumns.schoolYear && <td className="py-2 pr-4 text-[#334E68]">{r.schoolYear}</td>}
                          {visibleColumns.semester && <td className="py-2 pr-4 text-[#334E68]">{r.semesterOrSummer}</td>}
                          {visibleColumns.units && <td className="py-2 pr-4 text-right text-[#334E68]">{r.units}</td>}
                          {visibleColumns.transactionDate && <td className="py-2 pr-4 whitespace-nowrap text-[#334E68]">{formatTransactionDate(r.transactionDate)}</td>}
                          {visibleColumns.referenceNo && <td className="py-2 pr-4 whitespace-nowrap text-[#334E68]">{r.referenceNo || '—'}</td>}
                          {visibleColumns.particulars && <td className="py-2 pr-4 text-[#334E68]">{r.particulars}</td>}
                          {visibleColumns.feeRate && <td className="py-2 pr-4 text-right text-[#334E68]">{currency(r.tuitionPerUnitOrFeePerSemester)}</td>}
                          {visibleColumns.entryType && <td className="py-2 pr-4">
                            <Badge
                              variant="outline"
                              className={`${typeBadgeVariant(r.arOrPayment)} ${
                                r.entryType === 'ar' && !r.latinHonor ? 'cursor-pointer hover:ring-2 hover:ring-[#0F6FFF]' : ''
                              }`}
                              onClick={(event) => {
                                if (r.entryType === 'ar' && !r.latinHonor) {
                                  event.stopPropagation();
                                  setHonorTarget(r);
                                  setSelectedHonor('');
                                }
                              }}
                              title={r.entryType === 'ar' && !r.latinHonor ? 'Click to apply Latin Honor discount' : undefined}
                            >
                              {r.arOrPayment}
                              {r.latinHonor && (
                                <span className="ml-1 text-xs">({r.latinHonor})</span>
                              )}
                            </Badge>
                          </td>}
                          <td className="py-2 pr-4 text-right">
                            <div className="flex flex-col items-end">
                              <span className="font-medium text-[#0B3D91]">{currency(r.amount)}</span>
                              {(r.discountAmount ?? 0) > 0 ? (
                                <span className="text-xs text-emerald-600">
                                  (Discount: -{currency(r.discountAmount!)})
                                </span>
                              ) : null}
                            </div>
                          </td>
                          {visibleColumns.status && <td className="py-2 pr-4">
                            <Badge variant="outline" className={statusBadgeVariant(r.status)}>
                              {r.status || '—'}
                            </Badge>
                          </td>}
                          {visibleColumns.remark && <td className="py-2 pr-4 text-[#8AA8CC]">{r.remark}</td>}
                          {visibleColumns.inputBy && <td className="py-2 pr-4 text-[#8AA8CC]">{r.inputBy}</td>}
                          <td className="py-2 pr-2 text-center whitespace-nowrap">
                            <button
                              type="button"
                              onClick={(event) => {
                                event.stopPropagation();
                                router.get(`/law-ledger/${r.id}/edit`);
                              }}
                              className="mr-1 inline-flex items-center justify-center rounded p-1.5 text-[#0B62E0] transition-colors hover:bg-[#EAF2FF]"
                              title="Edit"
                            >
                              <Pencil className="h-3.5 w-3.5" />
                            </button>
                            <button
                              type="button"
                              onClick={(event) => {
                                event.stopPropagation();
                                setDeleteTarget(r);
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

{/* ---- Pagination Footer ---- */}
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
          )}
        </CardFooter>
      )}
        </Card>

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

      <AlertDialog
        open={deleteTarget !== null}
        onOpenChange={(open) => {
          if (!open && !isDeleting) {
            setDeleteTarget(null);
          }
        }}
      >
        <AlertDialogContent className="max-w-md gap-0 overflow-hidden border border-[#CFE3FF] bg-white p-0 shadow-xl sm:max-w-md">
          <AlertDialogHeader className="gap-3 p-5 sm:place-items-start sm:text-left">
            <AlertDialogMedia className="mb-0 size-11 rounded-full bg-red-50 text-red-600">
              <AlertTriangle className="size-5" />
            </AlertDialogMedia>
            <AlertDialogTitle className="text-lg font-semibold text-[#0B3D91]">
              Delete transaction?
            </AlertDialogTitle>
            <AlertDialogDescription className="text-sm text-[#5C7A9E]">
              This will permanently remove the selected ledger entry. This action cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>

          {deleteTarget && (
            <div className="mx-5 mb-5 rounded-lg border border-[#EAF2FF] bg-[#F8FBFF] p-3">
              <p className="text-sm font-semibold text-[#0B3D91]">{deleteTarget.name}</p>
              <div className="mt-2 grid grid-cols-2 gap-x-4 gap-y-1.5 text-xs text-[#5C7A9E]">
                <div>
                  <span className="block text-[11px] uppercase tracking-wide text-[#8AA8CC]">Type</span>
                  <span className="font-medium text-[#334E68]">{deleteTarget.arOrPayment || '—'}</span>
                </div>
                <div>
                  <span className="block text-[11px] uppercase tracking-wide text-[#8AA8CC]">Amount</span>
                  <span className="font-medium text-[#334E68]">{currency(deleteTarget.amount)}</span>
                </div>
                <div>
                  <span className="block text-[11px] uppercase tracking-wide text-[#8AA8CC]">Date</span>
                  <span className="font-medium text-[#334E68]">{formatTransactionDate(deleteTarget.transactionDate)}</span>
                </div>
                <div>
                  <span className="block text-[11px] uppercase tracking-wide text-[#8AA8CC]">Reference</span>
                  <span className="font-medium text-[#334E68]">{deleteTarget.referenceNo || '—'}</span>
                </div>
              </div>
            </div>
          )}

          <AlertDialogFooter className="mx-0 mb-0 rounded-none border-[#EAF2FF] bg-[#F8FBFF] px-5 py-4">
            <AlertDialogCancel
              disabled={isDeleting}
              className="border-[#CFE3FF] text-[#0B3D91] hover:bg-white"
            >
              Cancel
            </AlertDialogCancel>
            <AlertDialogAction
              disabled={isDeleting}
              onClick={confirmDelete}
              className="bg-red-600 text-white hover:bg-red-700"
            >
              {isDeleting ? (
                <>
                  <Loader2 className="h-4 w-4 animate-spin" />
                  Deleting...
                </>
              ) : (
                <>
                  <Trash2 className="h-4 w-4" />
                  Delete Transaction
                </>
              )}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
       </AlertDialog>

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

      {/* Email SOA Modal */}
      <Dialog open={isEmailModalOpen} onOpenChange={setIsEmailModalOpen}>
        <DialogContent className="sm:max-w-6xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <Mail className="h-5 w-5 text-emerald-600" />
              Email Statement of Account
            </DialogTitle>
            <DialogDescription>
              Choose which students should receive their SOA statement via email.
            </DialogDescription>
          </DialogHeader>

          <div className="grid gap-6 md:grid-cols-[2fr_1fr]">
            <div className="space-y-4 overflow-y-auto">
            {/* Recipient selection dropdown */}
            <div className="space-y-1.5">
              <label className="text-sm font-medium text-slate-700">
                Whom do you want to send email to?
              </label>
              <Select
                value={emailTarget}
                onValueChange={(v) => {
                  if (v === 'all_matching' || v === 'specific' || v === 'all_outstanding') {
                    setEmailTarget(v);
                  }
                }}
              >
                <SelectTrigger className="h-10 w-full rounded-lg border-slate-200 bg-white shadow-sm">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all_matching">All matching filters</SelectItem>
                  <SelectItem value="all_outstanding">All outstanding (balance &gt; 0)</SelectItem>
                  <SelectItem value="specific">Specific person</SelectItem>
                </SelectContent>
              </Select>
            </div>

            {/* Specific person search + checkbox list — only shown when 'specific' is chosen */}
            {emailTarget === 'specific' && (
              <div className="rounded-lg bg-slate-50 p-3 text-sm text-slate-600">
                <div className="flex items-center gap-3 mb-2 flex-wrap">
                  <div className="flex items-center gap-2">
                    <span className="font-semibold text-slate-800">
                      {visibleRecipients.length}
                    </span>
                    <span>recipient(s)</span>
                  </div>
                  <div className="relative flex-1 max-w-md">
                    <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <Input
                      placeholder="Search by name, email, or student number"
                      value={emailSearch}
                      onChange={(e) => setEmailSearch(e.target.value)}
                      className="h-9 pl-9 text-sm rounded-lg"
                    />
                  </div>
                  {selectedIds.size > 0 && (
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      onClick={() => setSelectedIds(new Set())}
                      className="h-9 px-3 gap-1.5 shrink-0"
                    >
                      <XCircle className="h-4 w-4" />
                      <span className="hidden sm:inline">Clear ({selectedIds.size})</span>
                    </Button>
                  )}
                </div>

                <div className="max-h-60 overflow-y-auto border border-slate-200 rounded-lg bg-white">
                  {isLoadingRecipients ? (
                    <div className="p-4 text-center text-slate-500">Loading recipients...</div>
                  ) : visibleRecipients.length === 0 ? (
                    <div className="p-4 text-center text-slate-500">No recipients found.</div>
                  ) : (
                    <div>
                      <div
                        className="flex items-center gap-3 p-2 hover:bg-slate-50 cursor-pointer border-b border-slate-100"
                        onClick={() => {
                          const visibleIds = new Set(visibleRecipients.map((r) => r.id));

                          setSelectedIds((prev) => {
                            const next = new Set(prev);

                            if (visibleRecipients.every((r) => next.has(r.id))) {
                              visibleIds.forEach((id) => next.delete(id));
                            } else {
                              visibleIds.forEach((id) => next.add(id));
                            }

                            return next;
                          });
                        }}
                      >
                        <div className="flex items-center justify-center w-4 h-4">
                          {visibleRecipients.every((r) => selectedIds.has(r.id)) ? (
                            <CheckSquare className="h-4 w-4 text-emerald-600" />
                          ) : (
                            <Square className="h-4 w-4 text-slate-400" />
                          )}
                        </div>
                        <span className="font-medium text-slate-700">Select All ({visibleRecipients.length})</span>
                      </div>
                      {visibleRecipients.map((row) => (
                        <div
                          key={row.id}
                          className="flex items-center gap-3 p-2 hover:bg-slate-50 cursor-pointer"
                          onClick={() => toggleSelectId(row.id)}
                        >
                          <div className="flex items-center justify-center w-4 h-4">
                            {selectedIds.has(row.id) ? (
                              <CheckSquare className="h-4 w-4 text-emerald-600" />
                            ) : (
                              <Square className="h-4 w-4 text-slate-400" />
                            )}
                          </div>
                          <div className="min-w-0 flex-1">
                            <div className="font-medium text-slate-800 truncate">
                              {row.full_name}
                            </div>
                            <div className="text-xs text-slate-500 truncate">
                              {row.student_number ?? '-'} · {row.email}
                            </div>
                          </div>
                          <div className="text-right">
                            <span className={row.balance > 0 ? 'text-amber-600' : 'text-emerald-600'}>
                              ₱{(row.balance ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                            </span>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              </div>
            )}

            {/* Bulk recipient list — shown when NOT 'specific' */}
            {emailTarget !== 'specific' && (
              <div className="rounded-lg bg-slate-50 p-3 text-sm text-slate-600">
                <div className="flex items-center justify-between gap-2 mb-2">
                  <div className="flex items-center gap-2">
                    <span className="font-semibold text-slate-800">
                      {bulkRecipients.length}
                    </span>
                    <span>recipient(s)</span>
                    <Input
                      placeholder="Search by name, email, or ID"
                      value={emailSearch}
                      onChange={(e) => setEmailSearch(e.target.value)}
                      className="h-9 w-64 text-sm rounded-lg"
                    />
                  </div>
                  <div className="flex items-center gap-1.5">
                    {bulkRecipients.every((r) => selectedIds.has(r.id)) && bulkRecipients.length > 0 ? (
                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => setSelectedIds(new Set())}
                        className="h-9 px-3"
                      >
                        Unselect All
                      </Button>
                    ) : (
                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => setSelectedIds(new Set(bulkRecipients.map((r) => r.id)))}
                        className="h-9 px-3"
                      >
                        Select All
                      </Button>
                    )}
                  </div>
                </div>

                <div className="max-h-60 overflow-y-auto border border-slate-200 rounded-lg bg-white">
                  {isLoadingRecipients ? (
                    <div className="p-4 text-center text-slate-500">Loading recipients...</div>
                  ) : bulkRecipients.length === 0 ? (
                    <div className="p-4 text-center text-slate-500">No recipients found.</div>
                  ) : (
                    <div>
                      {bulkRecipients.map((row) => {
                        const isSelected = selectedIds.has(row.id);

                        return (
                          <div
                            key={row.id}
                            className="flex items-center gap-3 p-2 hover:bg-slate-50 cursor-pointer border-b border-slate-100 last:border-0"
                            onClick={() => toggleSelectId(row.id)}
                          >
                            <div className="flex items-center justify-center w-4 h-4">
                              {isSelected ? (
                                <CheckSquare className="h-4 w-4 text-emerald-600" />
                              ) : (
                                <Square className="h-4 w-4 text-slate-400" />
                              )}
                            </div>
                            <div className="min-w-0 flex-1">
                              <div className="font-medium text-slate-800 truncate">
                                {row.full_name}
                              </div>
                              <div className="text-xs text-slate-500 truncate">
                                {row.student_number ?? '-'} · {row.email}
                              </div>
                            </div>
                            <div className="text-right">
                              <span className={row.balance > 0 ? 'text-amber-600' : 'text-emerald-600'}>
                                ₱{(row.balance ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                              </span>
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  )}
                </div>
              </div>
            )}

            <div className="space-y-3">
              <div>
                <label className="block text-sm font-medium text-slate-700">
                  Subject (optional)
                </label>
                <Input
                  placeholder="Leave blank for default subject"
                  value={emailSubject}
                  onChange={(e) => setEmailSubject(e.target.value)}
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-slate-700">
                  Exam Period (optional)
                </label>
                <Select
                  value={examPeriod}
                  onValueChange={(v) => {
                    if (v === 'Midterm' || v === 'Final' || v === '') {
                      setExamPeriod(v);

                      if (v === '') {
                        setExamDeadline('');
                      }
                    }
                  }}
                >
                  <SelectTrigger className="h-10 w-full rounded-lg border-slate-200 bg-white shadow-sm">
                    <SelectValue placeholder="Select exam period" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="Midterm">Midterm</SelectItem>
                    <SelectItem value="Final">Final</SelectItem>
                  </SelectContent>
                </Select>
                <p className="mt-1 text-xs text-slate-400">
                  Leave empty if not tied to an exam period.
                </p>
              </div>

              {examPeriod && (
                <div>
                  <label className="block text-sm font-medium text-slate-700">
                    Payment Deadline (optional)
                  </label>
                  <Popover>
                    <PopoverTrigger
                      render={
                        <Button
                          variant="outline"
                          className={`w-full justify-start text-left font-normal ${!examDeadline && 'text-slate-400'}`}
                        >
                          <CalendarIcon className="mr-2 h-4 w-4" />
                          {examDeadline ? formatDateInput(examDeadline) : 'Pick a deadline'}
                        </Button>
                      }
                    />
                    <PopoverContent className="w-auto p-0">
                      <Calendar
                        mode="single"
                        selected={examDeadline ? new Date(`${examDeadline}T00:00:00`) : undefined}
                        onSelect={(date) => {
                          if (!date) {
                            setExamDeadline('');

                            return;
                          }

                          const y = date.getFullYear();
                          const m = String(date.getMonth() + 1).padStart(2, '0');
                          const d = String(date.getDate()).padStart(2, '0');
                          setExamDeadline(`${y}-${m}-${d}`);
                        }}
                      />
                    </PopoverContent>
                  </Popover>
                  <p className="mt-1 text-xs text-slate-400">
                    Deadline for SOA payment related to {examPeriod}.
                  </p>
                </div>
              )}
              <div>
                <label className="block text-sm font-medium text-slate-700">
                  Additional Note (optional)
                </label>
                <Textarea
                  placeholder="Add a personal note..."
                  value={emailNote}
                  onChange={(e) => setEmailNote(e.target.value)}
                  rows={5}
                />
                <p className="mt-1 text-xs text-slate-400">
                  Plain text only — no formatting needed, it's inserted as its own paragraph.
                </p>
              </div>
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium text-slate-700 mb-2">
              Live Preview
            </label>
            <div className="overflow-hidden rounded-xl border border-slate-200">
              <iframe
                title="Email preview"
                srcDoc={buildEmailPreviewHtml(emailNote, examPeriod, examDeadline)}
                className="h-[28rem] w-[500px] bg-white"
              />
            </div>
          </div>
        </div>

        <DialogFooter className="gap-2">
            <Button
              variant="outline"
              onClick={() => setIsEmailModalOpen(false)}
              disabled={isSendingEmails}
            >
              Cancel
            </Button>
            <Button
              className="bg-[#0B3D91] text-white hover:bg-[#092D6F]"
              disabled={isSendingEmails || getEffectiveRecipientIds().length === 0}
              onClick={handleSendEmails}
            >
              {isSendingEmails ? 'Sending...' : `Send (${getEffectiveRecipientIds().length})`}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Email Processing Modal */}
      <Dialog
        open={showEmailProcessingModal}
        onOpenChange={(open) => {
          if (!open && isEmailProcessingDone) {
            setShowEmailProcessingModal(false);
          }
        }}
      >
        <DialogContent
          className="sm:max-w-md"
          showCloseButton={false}
        >
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              {isEmailProcessingDone ? (
                <CheckCircle2 className="h-5 w-5 text-emerald-600" />
              ) : (
                <Loader2 className="h-5 w-5 animate-spin text-[#0F6FFF]" />
              )}
              {isEmailProcessingDone ? 'Email Sending Complete' : 'Sending Emails...'}
            </DialogTitle>
            <DialogDescription>
              {isEmailProcessingDone
                ? 'The statement of account emails have finished processing.'
                : 'Please wait while the statement of account PDFs are generated and emailed. Do not close or refresh this page.'}
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-3">
            <div className="h-2 w-full overflow-hidden rounded-full bg-[#E8F0FE]">
              <div
                className={
                  isEmailProcessingDone
                    ? 'h-full w-full rounded-full bg-emerald-600'
                    : 'h-full w-full animate-pulse rounded-full bg-[#0F6FFF]'
                }
              />
            </div>

            <p className="text-sm text-slate-600">{emailProcessingMessage}</p>
            <p className="text-xs text-slate-400">
              Elapsed time: {Math.floor(emailProcessingSeconds / 60)}m {emailProcessingSeconds % 60}s
            </p>
          </div>

          {isEmailProcessingDone && (
            <DialogFooter>
              <Button
                type="button"
                className="w-full gap-2 bg-emerald-600 text-white hover:bg-emerald-700"
                onClick={() => {
                  setShowEmailProcessingModal(false);
                  setEmailProcessingMessage('');
                  setEmailProcessingSeconds(0);
                }}
              >
                <CheckCircle2 className="h-4 w-4" />
                Done
              </Button>
            </DialogFooter>
          )}
        </DialogContent>
      </Dialog>

      {/* Latin Honor Discount Dialog */}
      <AlertDialog
        open={honorTarget !== null}
        onOpenChange={(open) => {
          if (!open && !isApplyingHonor) {
            setHonorTarget(null);
            setSelectedHonor('');
          }
        }}
      >
        <AlertDialogContent className="max-w-md gap-0 overflow-hidden border border-[#CFE3FF] bg-white p-0 shadow-xl sm:max-w-md">
          <AlertDialogHeader className="gap-3 p-5 sm:place-items-start sm:text-left">
            <AlertDialogMedia className="mb-0 size-11 rounded-full bg-blue-50 text-[#0F6FFF]">
              <GraduationCap className="size-5" />
            </AlertDialogMedia>
            <AlertDialogTitle className="text-lg font-semibold text-[#0B3D91]">
              Apply Latin Honor Discount
            </AlertDialogTitle>
            <AlertDialogDescription className="text-sm text-[#5C7A9E]">
              Select the student's Latin Honor to automatically apply the corresponding discount to the assessment amount.
            </AlertDialogDescription>
          </AlertDialogHeader>

          {honorTarget && (
            <div className="mx-5 mb-5 space-y-4">
              <div className="rounded-lg border border-[#EAF2FF] bg-[#F8FBFF] p-3">
                <p className="text-sm font-semibold text-[#0B3D91]">{honorTarget.name}</p>
                <div className="mt-2 grid grid-cols-2 gap-x-4 gap-y-1.5 text-xs text-[#5C7A9E]">
                  <div>
                    <span className="block text-[11px] uppercase tracking-wide text-[#8AA8CC]">Original Amount</span>
                    <span className="font-medium text-[#334E68]">{currency(honorTarget.amount)}</span>
                  </div>
                  <div>
                    <span className="block text-[11px] uppercase tracking-wide text-[#8AA8CC]">Term</span>
                    <span className="font-medium text-[#334E68]">{honorTarget.schoolYear} {honorTarget.semesterOrSummer}</span>
                  </div>
                </div>
              </div>

              <div className="space-y-2">
                <label className="text-sm font-medium text-[#0B3D91]">Select Latin Honor</label>
                <div className="space-y-2">
                  <button
                    type="button"
                    onClick={() => setSelectedHonor('SUMMA')}
                    className={`w-full rounded-lg border-2 p-3 text-left transition-all ${
                      selectedHonor === 'SUMMA'
                        ? 'border-[#0F6FFF] bg-[#EAF2FF]'
                        : 'border-[#CFE3FF] bg-white hover:border-[#B9D8FF]'
                    }`}
                  >
                    <div className="flex items-center justify-between">
                      <div>
                        <p className="font-semibold text-[#0B3D91]">Summa Cum Laude</p>
                        <p className="text-xs text-[#5C7A9E]">100% discount – Full scholarship</p>
                      </div>
                      <div className="text-right">
                        <p className="text-sm font-bold text-emerald-600">-{currency(honorTarget.amount)}</p>
                        <p className="text-xs text-[#8AA8CC]">New: {currency(0)}</p>
                      </div>
                    </div>
                  </button>

                  <button
                    type="button"
                    onClick={() => setSelectedHonor('MAGNA')}
                    className={`w-full rounded-lg border-2 p-3 text-left transition-all ${
                      selectedHonor === 'MAGNA'
                        ? 'border-[#0F6FFF] bg-[#EAF2FF]'
                        : 'border-[#CFE3FF] bg-white hover:border-[#B9D8FF]'
                    }`}
                  >
                    <div className="flex items-center justify-between">
                      <div>
                        <p className="font-semibold text-[#0B3D91]">Magna Cum Laude</p>
                        <p className="text-xs text-[#5C7A9E]">75% discount – Partial scholarship</p>
                      </div>
                      <div className="text-right">
                        <p className="text-sm font-bold text-emerald-600">-{currency(honorTarget.amount * 0.75)}</p>
                        <p className="text-xs text-[#8AA8CC]">New: {currency(honorTarget.amount * 0.25)}</p>
                      </div>
                    </div>
                  </button>

                  <button
                    type="button"
                    onClick={() => setSelectedHonor('CUM_LAUDE')}
                    className={`w-full rounded-lg border-2 p-3 text-left transition-all ${
                      selectedHonor === 'CUM_LAUDE'
                        ? 'border-[#0F6FFF] bg-[#EAF2FF]'
                        : 'border-[#CFE3FF] bg-white hover:border-[#B9D8FF]'
                    }`}
                  >
                    <div className="flex items-center justify-between">
                      <div>
                        <p className="font-semibold text-[#0B3D91]">Cum Laude</p>
                        <p className="text-xs text-[#5C7A9E]">50% discount – Half scholarship</p>
                      </div>
                      <div className="text-right">
                        <p className="text-sm font-bold text-emerald-600">-{currency(honorTarget.amount * 0.5)}</p>
                        <p className="text-xs text-[#8AA8CC]">New: {currency(honorTarget.amount * 0.5)}</p>
                      </div>
                    </div>
                  </button>
                </div>
              </div>
            </div>
          )}

          <AlertDialogFooter className="mx-0 mb-0 rounded-none border-[#EAF2FF] bg-[#F8FBFF] px-5 py-4">
            <AlertDialogCancel
              disabled={isApplyingHonor}
              className="border-[#CFE3FF] text-[#0B3D91] hover:bg-white"
            >
              Cancel
            </AlertDialogCancel>
            <AlertDialogAction
              disabled={isApplyingHonor || !selectedHonor}
              onClick={applyHonorDiscount}
              className="bg-[#0F6FFF] text-white hover:bg-[#0B5DDB] disabled:opacity-50"
            >
              {isApplyingHonor ? (
                <>
                  <Loader2 className="h-4 w-4 animate-spin" />
                  Applying...
                </>
              ) : (
                <>
                  <CheckCircle2 className="h-4 w-4" />
                  Apply Discount
                </>
              )}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      </div>
  );
}
