import { Head, router, useForm } from '@inertiajs/react';
import {
    ArrowDownUp,
    ArrowLeft,
    Check,
    ChevronsUpDown,
    Layers,
    Plus,
    ReceiptText,
    Search,
    Sparkles,
    Trash2,
    User,
    Wallet,
    X,
} from 'lucide-react';
import React, { useEffect, useMemo, useRef, useState } from 'react';
import {
    index as lawLedgerIndex,
    store as storeLawLedger,
    studentBalance,
} from '@/actions/App/Http/Controllers/LawSchoolLedgerController';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { flashToast } from '@/utils/flashToast';

// ─── Constants ────────────────────────────────────────────────────────────────

const semesterOptions = [
    { label: '1st Sem.', value: 'First Semester' },
    { label: '2nd Sem.', value: 'Second Semester' },
    { label: 'Summer', value: 'Summer' },
];

const particularPresets: Record<EntryType, string[]> = {
    ar: ['Tuition', 'Registration', 'Miscellaneous', 'Laboratory Fee', 'Other Fees'],
    payment: ['Payment', 'Downpayment', 'Midterm Payment', 'Final Payment', 'Tuition Payment'],
    adjustment: ['Adjustment', 'Discount', 'Correction', 'Refund', 'Latin Honor Scholarship'],
};

const entryTypeOptions = [
    {
        value: 'ar',
        label: 'Accounts Receivable (AR)',
        icon: ReceiptText,
        color: 'border-blue-500 bg-blue-50 text-blue-700',
    },
    {
        value: 'payment',
        label: 'Payment (OR)',
        icon: Wallet,
        color: 'border-emerald-500 bg-emerald-50 text-emerald-700',
    },
    {
        value: 'adjustment',
        label: 'Adjustment',
        icon: ArrowDownUp,
        color: 'border-purple-500 bg-purple-50 text-purple-700',
    },
] as const;

const latinHonorOptions = [
    { value: 'SUMMA', label: 'Summa', fullLabel: 'Summa Cum Laude', discountRate: 1 },
    { value: 'MAGNA', label: 'Magna', fullLabel: 'Magna Cum Laude', discountRate: 1 },
    { value: 'CUM_LAUDE', label: 'Cum Laude', fullLabel: 'Cum Laude', discountRate: 0.5 },
] as const;

// ─── Types ────────────────────────────────────────────────────────────────────

type EntryType = 'ar' | 'payment' | 'adjustment';
type LatinHonor = '' | 'SUMMA' | 'MAGNA' | 'CUM_LAUDE';

interface StudentOption {
    id: number | string;
    student_number?: string | null;
    last_name: string;
    first_name: string;
    middle_name?: string | null;
    last_course_id?: number | string | null;
}

interface CourseOption {
    id: number | string;
    code: string;
}

interface AcademicTermOption {
    id: number;
    school_year: string;
    semester: string;
}

interface Props {
    students: StudentOption[];
    courses: CourseOption[];
    academicTerms: AcademicTermOption[];
    statuses?: string[];
    authUserName: string;
    users?: Array<{ id: number | string; name: string }>;
    selectedStudentId?: number | string | null;
    defaultEntryType?: EntryType;
}

interface BatchItem {
    id: string;
    entry_type: EntryType;
    particulars: string;
    units: string;
    tuition_per_unit_or_misc: string;
    amount: string;
    reference_or_jev_number: string;
    latin_honor: LatinHonor;
    remarks: string;
}

interface StudentBalancePreview {
    totalCharges: number;
    totalPayments: number;
    totalAdjustments?: number;
    outstandingBalance: number;
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function formatStudentLabel(student: StudentOption): string {
    const studentName = !student.first_name
        ? String(student.last_name)
        : `${student.last_name}, ${student.first_name}${student.middle_name ? ` ${student.middle_name.charAt(0).toUpperCase()}.` : ''}`;

    if (student.student_number) {
        return `${student.student_number} — ${studentName}`;
    }

    return studentName;
}

function currency(value: number): string {
    return `₱${Number(value ?? 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

function discountForHonor(amount: number, latinHonor: LatinHonor): number {
    const option = latinHonorOptions.find((item) => item.value === latinHonor);

    return option ? amount * option.discountRate : 0;
}

function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return <p className="mt-1 text-xs text-red-500">{message}</p>;
}

// ─── Searchable Student Select Component ──────────────────────────────────────

function SearchableStudentSelect({
    students,
    value,
    onChange,
}: {
    students: StudentOption[];
    value: string | number;
    onChange: (id: string | number) => void;
}) {
    const [isOpen, setIsOpen] = useState(false);
    const [search, setSearch] = useState('');
    const containerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!isOpen) {
            return;
        }

        const handleOutsideClick = (event: MouseEvent | TouchEvent) => {
            if (
                containerRef.current &&
                !containerRef.current.contains(event.target as Node)
            ) {
                setIsOpen(false);
            }
        };

        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setIsOpen(false);
            }
        };

        document.addEventListener('mousedown', handleOutsideClick);
        document.addEventListener('touchstart', handleOutsideClick);
        document.addEventListener('keydown', handleKeyDown);

        return () => {
            document.removeEventListener('mousedown', handleOutsideClick);
            document.removeEventListener('touchstart', handleOutsideClick);
            document.removeEventListener('keydown', handleKeyDown);
        };
    }, [isOpen]);

    const selectedStudent = students.find(
        (student) => String(student.id) === String(value),
    );

    const matchLimit = 80;

    const filteredStudents = useMemo(() => {
        const query = search.toLowerCase().trim();

        if (!query) {
            return students;
        }

        return students.filter((student) =>
            formatStudentLabel(student).toLowerCase().includes(query),
        );
    }, [students, search]);

    const visibleStudents = filteredStudents.slice(0, matchLimit);
    const hasMoreStudents = filteredStudents.length > matchLimit;

    return (
        <div ref={containerRef} className="relative w-full">
            <button
                type="button"
                onClick={() => setIsOpen(!isOpen)}
                className="flex w-full items-center justify-between rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm text-[#334E68] focus:ring-2 focus:ring-[#0F6FFF] focus:outline-none"
            >
                <span
                    className={
                        selectedStudent
                            ? 'font-medium text-[#0B3D91]'
                            : 'text-[#7FA6D6]'
                    }
                >
                    {selectedStudent
                        ? formatStudentLabel(selectedStudent)
                        : '-- Select / Search Student --'}
                </span>
                <ChevronsUpDown className="h-4 w-4 text-[#7FA6D6]" />
            </button>

            {isOpen && (
                <div className="absolute z-50 mt-1 w-full space-y-2 rounded-md border border-[#CFE3FF] bg-white p-2 shadow-lg">
                    <div className="relative">
                        <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-[#8AA8CC]" />
                        <Input
                            type="text"
                            placeholder="Type Student ID or name to filter..."
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            className="h-9 border-[#CFE3FF] pl-8 text-xs"
                            autoFocus
                        />
                        {search && (
                            <button
                                type="button"
                                onClick={() => setSearch('')}
                                className="absolute top-2.5 right-2.5 text-xs text-[#8AA8CC] hover:text-[#0B3D91]"
                                aria-label="Clear student search"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        )}
                    </div>

                    <div className="max-h-60 divide-y divide-[#EAF2FF] overflow-y-auto rounded-md border border-[#EAF2FF]">
                        {filteredStudents.length === 0 ? (
                            <p className="p-3 text-center text-xs text-[#8AA8CC]">
                                No students found.
                            </p>
                        ) : (
                            <>
                                {visibleStudents.map((student) => {
                                    const isSelected =
                                        String(student.id) === String(value);

                                    return (
                                        <button
                                            key={student.id}
                                            type="button"
                                            onClick={() => {
                                                onChange(student.id);
                                                setIsOpen(false);
                                            }}
                                            className={`flex w-full items-center justify-between px-3 py-2 text-left text-xs transition-colors hover:bg-[#F3F8FF] ${
                                                isSelected
                                                    ? 'bg-[#EAF2FF] font-semibold text-[#0B3D91]'
                                                    : 'text-[#334E68]'
                                            }`}
                                        >
                                            <span>{formatStudentLabel(student)}</span>
                                            {isSelected && (
                                                <Check className="h-3.5 w-3.5 text-[#0F6FFF]" />
                                            )}
                                        </button>
                                    );
                                })}
                                {hasMoreStudents && (
                                    <div className="bg-[#F8FBFF] px-3 py-2 text-center text-[11px] text-[#5C7A9E]">
                                        Showing first {matchLimit} of{' '}
                                        {filteredStudents.length} students. Type
                                        to narrow search.
                                    </div>
                                )}
                            </>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

// ─── Main Add Transaction Component ──────────────────────────────────────────

export default function AddTransaction({
    students,
    courses,
    academicTerms,
    authUserName,
    selectedStudentId = null,
    defaultEntryType = 'ar',
}: Props) {
    const [isBatchMode, setIsBatchMode] = useState(false);
    const [showNewStudent, setShowNewStudent] = useState(false);
    const [balancePreview, setBalancePreview] =
        useState<StudentBalancePreview | null>(null);
    const [loadingBalance, setLoadingBalance] = useState(false);

    const todayStr = new Date().toISOString().slice(0, 10);
    const currentYear = new Date().getFullYear();
    const defaultSy = `${currentYear}-${currentYear + 1}`;

    const { data, setData, transform, post, processing, errors, setError } =
        useForm<{
            student_id: string | number;
            new_student: {
                student_number: string;
                email: string;
                last_name: string;
                first_name: string;
                middle_name: string;
            } | null;
            course_id: string | number;
            academic_term_id: string | number;
            school_year: string;
            semester: string;
            entry_type: EntryType;
            units: string;
            transaction_date: string;
            reference_or_jev_number: string;
            particulars: string;
            tuition_per_unit_or_misc: string;
            amount: string;
            remarks: string;
            input_by: string;
            latin_honor: LatinHonor;
            discount_amount: string;
            items?: BatchItem[];
        }>({
            student_id: selectedStudentId ?? '',
            new_student: null,
            course_id: '',
            academic_term_id: '',
            school_year: defaultSy,
            semester: 'First Semester',
            entry_type: defaultEntryType,
            units: '',
            transaction_date: todayStr,
            reference_or_jev_number: '',
            particulars:
                defaultEntryType === 'payment'
                    ? 'Payment'
                    : defaultEntryType === 'adjustment'
                      ? 'Adjustment'
                      : 'Tuition',
            tuition_per_unit_or_misc: '',
            amount: '',
            remarks: '',
            input_by: authUserName,
            latin_honor: '',
            discount_amount: '',
        });

    const [batchItems, setBatchItems] = useState<BatchItem[]>([
        {
            id: '1',
            entry_type: 'ar',
            particulars: 'Tuition',
            units: '',
            tuition_per_unit_or_misc: '',
            amount: '',
            reference_or_jev_number: '',
            latin_honor: '',
            remarks: '',
        },
    ]);

    useEffect(() => {
        if (!data.student_id || showNewStudent) {
            setBalancePreview(null);
            return;
        }

        const controller = new AbortController();
        setLoadingBalance(true);

        fetch(studentBalance.url(Number(data.student_id)), {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((response) => (response.ok ? response.json() : null))
            .then((responseData) => {
                if (responseData?.summary) {
                    setBalancePreview(responseData.summary);
                }
            })
            .catch(() => {})
            .finally(() => setLoadingBalance(false));

        return () => controller.abort();
    }, [data.student_id, showNewStudent]);

    const parsedUnits = Number(data.units || 0);
    const parsedRate = Number(data.tuition_per_unit_or_misc || 0);
    const computedAmt = parsedUnits * parsedRate;
    const isAr = data.entry_type === 'ar';
    const autoAmount = isAr && data.amount === '';
    const displayedAmt = autoAmount ? computedAmt.toFixed(2) : data.amount;
    const activeAmount = autoAmount ? computedAmt : Number(data.amount || 0);
    const activeDiscount = discountForHonor(activeAmount, data.latin_honor);

    function syncTerm(schoolYear: string, semester: string) {
        if (academicTerms.length > 0) {
            const match = academicTerms.find(
                (term) =>
                    term.school_year === schoolYear && term.semester === semester,
            );

            setData((currentData) => ({
                ...currentData,
                academic_term_id: match?.id ?? '',
                school_year: schoolYear,
                semester,
            }));
            return;
        }

        setData((currentData) => ({
            ...currentData,
            school_year: schoolYear,
            semester,
        }));
    }

    function handleTypeChange(nextType: EntryType) {
        const defaultParticular =
            nextType === 'payment'
                ? 'Payment'
                : nextType === 'adjustment'
                  ? 'Adjustment'
                  : 'Tuition';

        setData((previousData) => ({
            ...previousData,
            entry_type: nextType,
            particulars: defaultParticular,
            latin_honor: nextType === 'ar' ? previousData.latin_honor : '',
            discount_amount: nextType === 'ar' ? previousData.discount_amount : '',
        }));
    }

    function addBatchRow() {
        setBatchItems((previousItems) => [
            ...previousItems,
            {
                id: String(Date.now()),
                entry_type: 'ar',
                particulars: 'Miscellaneous',
                units: '',
                tuition_per_unit_or_misc: '',
                amount: '',
                reference_or_jev_number: '',
                latin_honor: '',
                remarks: '',
            },
        ]);
    }

    function removeBatchRow(id: string) {
        if (batchItems.length <= 1) {
            return;
        }

        setBatchItems((previousItems) =>
            previousItems.filter((item) => item.id !== id),
        );
    }

    function updateBatchRow(id: string, patch: Partial<BatchItem>) {
        setBatchItems((previousItems) =>
            previousItems.map((item) =>
                item.id === id ? { ...item, ...patch } : item,
            ),
        );
    }

    const batchTotals = useMemo(() => {
        let arTotal = 0;
        let creditTotal = 0;

        batchItems.forEach((item) => {
            const units = Number(item.units || 0);
            const rate = Number(item.tuition_per_unit_or_misc || 0);
            const amount =
                item.amount !== '' ? Number(item.amount || 0) : units * rate;

            if (item.entry_type === 'ar') {
                arTotal += amount;
                creditTotal += discountForHonor(amount, item.latin_honor);
            } else {
                creditTotal += amount;
            }
        });

        return {
            arTotal,
            creditTotal,
            netChange: arTotal - creditTotal,
        };
    }, [batchItems]);

    const handleSubmit = (event: React.SyntheticEvent) => {
        event.preventDefault();

        if (showNewStudent) {
            const newStudent = data.new_student;
            if (!newStudent?.last_name?.trim()) {
                setError(
                    'new_student.last_name' as keyof typeof data,
                    'Last name is required.',
                );
                flashToast('error', "Please enter the student's last name.");
                return;
            }
            if (!newStudent?.first_name?.trim()) {
                setError(
                    'new_student.first_name' as keyof typeof data,
                    'First name is required.',
                );
                flashToast('error', "Please enter the student's first name.");
                return;
            }
        } else if (!data.student_id) {
            setError('student_id', 'Please select a student.');
            flashToast('error', 'Please select a student.');
            return;
        }

        if (!data.course_id) {
            setError('course_id', 'Please select a course.');
            flashToast('error', 'Please select a course.');
            return;
        }

        const finalDate =
            data.transaction_date || new Date().toISOString().slice(0, 10);

        if (isBatchMode) {
            if (batchItems.length === 0) {
                flashToast('error', 'Please add at least one line item.');
                return;
            }

            const formattedItems = batchItems.map((item) => {
                const units = Number(item.units || 0);
                const rate = Number(item.tuition_per_unit_or_misc || 0);
                const finalAmount =
                    item.amount !== '' ? item.amount : (units * rate).toFixed(2);
                const discountAmount = discountForHonor(
                    Number(finalAmount || 0),
                    item.latin_honor,
                );

                return {
                    course_id: data.course_id,
                    entry_type: item.entry_type,
                    units: item.units || '0',
                    transaction_date: finalDate,
                    reference_or_jev_number: item.reference_or_jev_number || '',
                    particulars: item.particulars || 'Tuition',
                    tuition_per_unit_or_misc:
                        item.tuition_per_unit_or_misc || '0.00',
                    tuition_per_unit_or_fee_per_semester:
                        item.tuition_per_unit_or_misc || '0.00',
                    rate: item.tuition_per_unit_or_misc || '0.00',
                    amount: finalAmount,
                    remarks: item.remarks || '',
                    latin_honor:
                        item.entry_type === 'ar' && item.latin_honor
                            ? item.latin_honor
                            : null,
                    discount_amount:
                        item.entry_type === 'ar' && item.latin_honor
                            ? discountAmount.toFixed(2)
                            : null,
                };
            });

            transform((formData) => ({
                ...formData,
                transaction_date: finalDate,
                items: formattedItems as unknown as BatchItem[],
            }));

            post(storeLawLedger.url(), {
                preserveScroll: true,
            });
            return;
        }

        const finalAmount = autoAmount ? computedAmt.toFixed(2) : data.amount;
        const discountAmount = data.latin_honor
            ? discountForHonor(Number(finalAmount || 0), data.latin_honor).toFixed(
                  2,
              )
            : '';

        transform((formData) => ({
            ...formData,
            amount: finalAmount,
            discount_amount: discountAmount,
            transaction_date: finalDate,
            tuition_per_unit_or_misc:
                formData.tuition_per_unit_or_misc || '0.00',
            tuition_per_unit_or_fee_per_semester:
                formData.tuition_per_unit_or_misc || '0.00',
            rate: formData.tuition_per_unit_or_misc || '0.00',
        }));

        post(storeLawLedger.url(), {
            preserveScroll: true,
        });
    };

    const selectClass =
        'w-full rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm text-[#334E68] focus:ring-2 focus:ring-[#0F6FFF] focus:outline-none';

    return (
        <div className="mx-auto max-w-5xl space-y-6 pb-12">
            <Head title="Add Transaction" />

            <div className="flex flex-col gap-4 border-b border-[#CFE3FF] pb-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-3">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => router.get(lawLedgerIndex.url())}
                        className="border-[#CFE3FF] text-[#0B3D91]"
                    >
                        <ArrowLeft className="mr-1 h-4 w-4" /> Back
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold text-[#0B3D91]">
                            Add New Transaction
                        </h1>
                        <p className="text-xs text-[#5C7A9E]">
                            Post Accounts Receivable, Payments, Adjustments, or
                            multi-line law ledger transactions.
                        </p>
                    </div>
                </div>

                <div className="flex items-center gap-1 rounded-lg border border-[#CFE3FF] bg-white p-1 shadow-xs">
                    <button
                        type="button"
                        onClick={() => setIsBatchMode(false)}
                        className={`flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-semibold transition-colors ${
                            !isBatchMode
                                ? 'bg-[#0F6FFF] text-white shadow-xs'
                                : 'text-[#5C7A9E] hover:text-[#0B3D91]'
                        }`}
                    >
                        <User className="h-3.5 w-3.5" /> Single Entry
                    </button>
                    <button
                        type="button"
                        onClick={() => setIsBatchMode(true)}
                        className={`flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-semibold transition-colors ${
                            isBatchMode
                                ? 'bg-[#0F6FFF] text-white shadow-xs'
                                : 'text-[#5C7A9E] hover:text-[#0B3D91]'
                        }`}
                    >
                        <Layers className="h-3.5 w-3.5" /> Batch / Multi-Line
                    </button>
                </div>
            </div>

            <form onSubmit={handleSubmit} className="space-y-6">
                <Card className="border-[#CFE3FF] bg-white">
                    <CardHeader className="border-b border-[#EAF2FF] bg-[#F8FBFF] pb-3">
                        <div className="flex items-center justify-between">
                            <CardTitle className="text-sm font-bold text-[#0B3D91]">
                                1. Student Information
                            </CardTitle>
                            <button
                                type="button"
                                onClick={() => {
                                    setShowNewStudent(!showNewStudent);
                                    if (!showNewStudent) {
                                        setData('student_id', '');
                                        setData('new_student', {
                                            student_number: '',
                                            email: '',
                                            last_name: '',
                                            first_name: '',
                                            middle_name: '',
                                        });
                                    } else {
                                        setData('new_student', null);
                                    }
                                }}
                                className="flex items-center gap-1 text-xs font-semibold text-[#0F6FFF] hover:underline"
                            >
                                {showNewStudent ? (
                                    <>
                                        <X className="h-3.5 w-3.5" /> Select
                                        existing student
                                    </>
                                ) : (
                                    <>
                                        <Plus className="h-3.5 w-3.5" /> Register
                                        new student
                                    </>
                                )}
                            </button>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4 pt-4">
                        {showNewStudent ? (
                            <div className="grid grid-cols-1 gap-3 rounded-lg border border-blue-100 bg-blue-50/60 p-4 sm:grid-cols-2 lg:grid-cols-3">
                                <div>
                                    <label className="text-xs font-medium text-[#334E68]">
                                        Student ID
                                    </label>
                                    <Input
                                        value={
                                            data.new_student?.student_number ?? ''
                                        }
                                        onChange={(event) =>
                                            setData('new_student', {
                                                ...data.new_student!,
                                                student_number:
                                                    event.target.value,
                                            })
                                        }
                                        placeholder="e.g. 2026-00123"
                                        className={
                                            (errors as any)[
                                                'new_student.student_number'
                                            ]
                                                ? 'border-red-400'
                                                : ''
                                        }
                                    />
                                    <FieldError
                                        message={
                                            (errors as any)[
                                                'new_student.student_number'
                                            ]
                                        }
                                    />
                                </div>
                                <div>
                                    <label className="text-xs font-medium text-[#334E68]">
                                        Email
                                    </label>
                                    <Input
                                        type="email"
                                        value={data.new_student?.email ?? ''}
                                        onChange={(event) =>
                                            setData('new_student', {
                                                ...data.new_student!,
                                                email: event.target.value,
                                            })
                                        }
                                        placeholder="student@example.com"
                                        className={
                                            (errors as any)['new_student.email']
                                                ? 'border-red-400'
                                                : ''
                                        }
                                    />
                                    <FieldError
                                        message={
                                            (errors as any)['new_student.email']
                                        }
                                    />
                                </div>
                                <div>
                                    <label className="text-xs font-medium text-[#334E68]">
                                        Last Name *
                                    </label>
                                    <Input
                                        value={data.new_student?.last_name ?? ''}
                                        onChange={(event) =>
                                            setData('new_student', {
                                                ...data.new_student!,
                                                last_name: event.target.value,
                                            })
                                        }
                                        placeholder="DELA CRUZ"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="text-xs font-medium text-[#334E68]">
                                        First Name *
                                    </label>
                                    <Input
                                        value={data.new_student?.first_name ?? ''}
                                        onChange={(event) =>
                                            setData('new_student', {
                                                ...data.new_student!,
                                                first_name: event.target.value,
                                            })
                                        }
                                        placeholder="JUAN"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="text-xs font-medium text-[#334E68]">
                                        Middle Name
                                    </label>
                                    <Input
                                        value={
                                            data.new_student?.middle_name ?? ''
                                        }
                                        onChange={(event) =>
                                            setData('new_student', {
                                                ...data.new_student!,
                                                middle_name: event.target.value,
                                            })
                                        }
                                        placeholder="P."
                                    />
                                </div>
                            </div>
                        ) : (
                            <div className="space-y-3">
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-[#334E68]">
                                        Select Student *
                                    </label>
                                    <SearchableStudentSelect
                                        students={students}
                                        value={data.student_id}
                                        onChange={(id) => {
                                            const selected = students.find(
                                                (student) =>
                                                    String(student.id) ===
                                                    String(id),
                                            );

                                            if (selected?.last_course_id) {
                                                setData((previousData) => ({
                                                    ...previousData,
                                                    student_id: id,
                                                    course_id: String(
                                                        selected.last_course_id,
                                                    ),
                                                }));
                                            } else {
                                                setData('student_id', id);
                                            }
                                        }}
                                    />
                                    <FieldError
                                        message={
                                            (errors as any).student_id ||
                                            (errors as any).student_name
                                        }
                                    />
                                </div>

                                {loadingBalance && (
                                    <div className="flex items-center gap-2 rounded-lg border border-[#CFE3FF] bg-[#F7FAFF] p-3 text-xs text-[#5C7A9E]">
                                        <Spinner className="h-3.5 w-3.5" />
                                        Loading student balance summary...
                                    </div>
                                )}
                                {!loadingBalance && balancePreview && (
                                    <div className="grid grid-cols-2 gap-2 rounded-lg border border-[#CFE3FF] bg-[#F7FAFF] p-3 text-xs sm:grid-cols-4">
                                        <div>
                                            <p className="text-[10px] font-semibold text-blue-700 uppercase">
                                                Total AR
                                            </p>
                                            <p className="font-bold text-blue-900">
                                                {currency(
                                                    balancePreview.totalCharges,
                                                )}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-[10px] font-semibold text-emerald-700 uppercase">
                                                Payments
                                            </p>
                                            <p className="font-bold text-emerald-900">
                                                {currency(
                                                    balancePreview.totalPayments,
                                                )}
                                            </p>
                                        </div>
                                        {(balancePreview.totalAdjustments ?? 0) >
                                            0 && (
                                            <div>
                                                <p className="text-[10px] font-semibold text-purple-700 uppercase">
                                                    Adjustments
                                                </p>
                                                <p className="font-bold text-purple-900">
                                                    {currency(
                                                        balancePreview.totalAdjustments ??
                                                            0,
                                                    )}
                                                </p>
                                            </div>
                                        )}
                                        <div>
                                            <p className="text-[10px] font-semibold text-amber-700 uppercase">
                                                Current Balance
                                            </p>
                                            <p
                                                className={`font-bold ${
                                                    balancePreview.outstandingBalance >
                                                    0
                                                        ? 'text-amber-900'
                                                        : 'text-emerald-900'
                                                }`}
                                            >
                                                {currency(
                                                    balancePreview.outstandingBalance,
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}

                        <div className="grid grid-cols-1 gap-4 pt-2 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <label className="mb-1 block text-xs font-medium text-[#334E68]">
                                    Course *
                                </label>
                                <select
                                    value={String(data.course_id)}
                                    onChange={(event) =>
                                        setData('course_id', event.target.value)
                                    }
                                    className={selectClass}
                                    required
                                >
                                    <option value="">-- Select Course --</option>
                                    {courses.map((course) => (
                                        <option
                                            key={course.id}
                                            value={String(course.id)}
                                        >
                                            {course.code}
                                        </option>
                                    ))}
                                </select>
                                <FieldError
                                    message={
                                        (errors as any).course_id ||
                                        (errors as any).course
                                    }
                                />
                            </div>

                            <div>
                                <label className="mb-1 block text-xs font-medium text-[#334E68]">
                                    School Year
                                </label>
                                <Input
                                    value={data.school_year}
                                    placeholder="e.g. 2025-2026"
                                    pattern="\d{4}-\d{4}"
                                    onChange={(event) =>
                                        syncTerm(event.target.value, data.semester)
                                    }
                                    className={
                                        (errors as any).school_year
                                            ? 'border-red-400'
                                            : ''
                                    }
                                />
                                <FieldError
                                    message={(errors as any).school_year}
                                />
                            </div>

                            <div>
                                <label className="mb-1 block text-xs font-medium text-[#334E68]">
                                    Semester
                                </label>
                                <select
                                    value={data.semester}
                                    onChange={(event) =>
                                        syncTerm(
                                            data.school_year,
                                            event.target.value,
                                        )
                                    }
                                    className={selectClass}
                                >
                                    {semesterOptions.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <div className="mb-1 flex items-center justify-between">
                                    <label className="text-xs font-medium text-[#334E68]">
                                        Transaction Date
                                    </label>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setData('transaction_date', todayStr)
                                        }
                                        className="text-[11px] font-semibold text-[#0F6FFF] hover:underline"
                                    >
                                        Today
                                    </button>
                                </div>
                                <Input
                                    type="date"
                                    value={data.transaction_date}
                                    onChange={(event) =>
                                        setData(
                                            'transaction_date',
                                            event.target.value,
                                        )
                                    }
                                    className={
                                        (errors as any).transaction_date
                                            ? 'border-red-400'
                                            : ''
                                    }
                                />
                                <FieldError
                                    message={(errors as any).transaction_date}
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {!isBatchMode ? (
                    <Card className="border-[#CFE3FF] bg-white">
                        <CardHeader className="border-b border-[#EAF2FF] bg-[#F8FBFF] pb-3">
                            <CardTitle className="text-sm font-bold text-[#0B3D91]">
                                2. Transaction Details
                            </CardTitle>
                            <CardDescription className="text-xs text-[#7FA6D6]">
                                Choose the transaction type to reveal the
                                appropriate financial fields.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-5 pt-4">
                            <div>
                                <label className="mb-2 block text-xs font-medium text-[#334E68]">
                                    Transaction Type
                                </label>
                                <div className="grid grid-cols-1 gap-2 sm:grid-cols-3">
                                    {entryTypeOptions.map((typeOption) => {
                                        const Icon = typeOption.icon;
                                        const isSelected =
                                            data.entry_type === typeOption.value;

                                        return (
                                            <button
                                                key={typeOption.value}
                                                type="button"
                                                onClick={() =>
                                                    handleTypeChange(
                                                        typeOption.value,
                                                    )
                                                }
                                                className={`flex items-center gap-2 rounded-lg border p-3 text-left transition-all ${
                                                    isSelected
                                                        ? `${typeOption.color} ring-2 ring-[#0F6FFF]`
                                                        : 'border-[#CFE3FF] bg-white text-[#334E68] hover:bg-[#F8FBFF]'
                                                }`}
                                            >
                                                <Icon className="h-4 w-4 shrink-0" />
                                                <span className="text-xs font-semibold">
                                                    {typeOption.label}
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>

                            <div>
                                <label className="mb-1 block text-xs font-medium text-[#334E68]">
                                    Particulars
                                </label>
                                <Input
                                    value={data.particulars}
                                    onChange={(event) =>
                                        setData('particulars', event.target.value)
                                    }
                                    placeholder="e.g. Tuition, Registration, Miscellaneous"
                                    className="mb-2"
                                />
                                <div className="flex flex-wrap items-center gap-1.5 text-xs">
                                    <span className="text-[11px] text-[#7FA6D6]">
                                        Presets:
                                    </span>
                                    {particularPresets[data.entry_type].map(
                                        (preset) => (
                                            <button
                                                key={preset}
                                                type="button"
                                                onClick={() =>
                                                    setData(
                                                        'particulars',
                                                        preset,
                                                    )
                                                }
                                                className="rounded-md border border-[#CFE3FF] bg-[#F7FAFF] px-2 py-0.5 text-[11px] font-medium text-[#0B3D91] transition-colors hover:bg-[#EAF2FF]"
                                            >
                                                {preset}
                                            </button>
                                        ),
                                    )}
                                </div>
                            </div>

                            <div>
                                <label className="mb-1 block text-xs font-medium text-[#334E68]">
                                    {data.entry_type === 'payment'
                                        ? 'Official Receipt (OR) Number *'
                                        : data.entry_type === 'adjustment'
                                          ? 'JEV / Reference Number'
                                          : 'Reference / Assessment Form No.'}
                                </label>
                                <Input
                                    value={data.reference_or_jev_number}
                                    onChange={(event) =>
                                        setData(
                                            'reference_or_jev_number',
                                            event.target.value,
                                        )
                                    }
                                    placeholder={
                                        data.entry_type === 'payment'
                                            ? 'e.g. OR #1234567'
                                            : 'e.g. JEV #2026-001'
                                    }
                                />
                            </div>

                            {data.entry_type === 'ar' ? (
                                <div className="grid grid-cols-1 gap-4 rounded-lg border border-blue-100 bg-blue-50/40 p-4 sm:grid-cols-3">
                                    <div>
                                        <label className="mb-1 block text-xs font-medium text-[#334E68]">
                                            Units
                                        </label>
                                        <Input
                                            type="number"
                                            min="0"
                                            value={data.units}
                                            onChange={(event) =>
                                                setData(
                                                    'units',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="e.g. 9"
                                        />
                                        <FieldError
                                            message={(errors as any).units}
                                        />
                                    </div>

                                    <div>
                                        <label className="mb-1 block text-xs font-medium text-[#334E68]">
                                            Tuition / Unit (Rate)
                                        </label>
                                        <Input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={
                                                data.tuition_per_unit_or_misc
                                            }
                                            onChange={(event) =>
                                                setData(
                                                    'tuition_per_unit_or_misc',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="e.g. 500.00"
                                        />
                                        <FieldError
                                            message={
                                                (errors as any)
                                                    .tuition_per_unit_or_misc
                                            }
                                        />
                                    </div>

                                    <div>
                                        <label className="mb-1 block text-xs font-medium text-[#334E68]">
                                            Total AR Amount
                                        </label>
                                        <Input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={displayedAmt}
                                            onChange={(event) =>
                                                setData(
                                                    'amount',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Auto-computed"
                                            className="font-bold text-blue-900"
                                        />
                                        <p className="mt-1 text-[11px] text-[#5C7A9E]">
                                            {autoAmount
                                                ? 'Auto: Units × Rate'
                                                : 'Manual amount override'}
                                        </p>
                                        <FieldError
                                            message={(errors as any).amount}
                                        />
                                    </div>

                                    <div className="rounded-md border border-[#CFE3FF] bg-white p-3 sm:col-span-3">
                                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                            <div className="flex items-center gap-2">
                                                <Sparkles className="h-4 w-4 text-[#0F6FFF]" />
                                                <div>
                                                    <p className="text-xs font-bold text-[#0B3D91]">
                                                        Apply Latin Honor
                                                        Scholarship
                                                    </p>
                                                    <p className="text-[11px] text-[#5C7A9E]">
                                                        Tags the AR and creates
                                                        an automatic matching
                                                        Adjustment entry.
                                                    </p>
                                                </div>
                                            </div>

                                            <div className="flex flex-wrap items-center gap-1">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setData(
                                                            'latin_honor',
                                                            '',
                                                        )
                                                    }
                                                    className={`rounded-md px-2.5 py-1 text-xs font-semibold ${
                                                        !data.latin_honor
                                                            ? 'bg-slate-200 text-slate-800'
                                                            : 'text-slate-500 hover:bg-slate-100'
                                                    }`}
                                                >
                                                    None
                                                </button>
                                                {latinHonorOptions.map(
                                                    (option) => (
                                                        <button
                                                            key={option.value}
                                                            type="button"
                                                            onClick={() =>
                                                                setData(
                                                                    'latin_honor',
                                                                    option.value,
                                                                )
                                                            }
                                                            className={`rounded-md px-2.5 py-1 text-xs font-semibold ${
                                                                data.latin_honor ===
                                                                option.value
                                                                    ? 'bg-purple-600 text-white'
                                                                    : 'border border-purple-200 text-purple-700 hover:bg-purple-50'
                                                            }`}
                                                        >
                                                            {option.label}
                                                        </button>
                                                    ),
                                                )}
                                            </div>
                                        </div>

                                        {data.latin_honor && (
                                            <div className="mt-3 flex flex-col gap-1 rounded-md bg-purple-50 p-2.5 text-xs text-purple-900 sm:flex-row sm:items-center sm:justify-between">
                                                <span>
                                                    Will create:{' '}
                                                    <strong>
                                                        AR(
                                                        {
                                                            latinHonorOptions.find(
                                                                (option) =>
                                                                    option.value ===
                                                                    data.latin_honor,
                                                            )?.label
                                                        }
                                                        )
                                                    </strong>{' '}
                                                    ({currency(activeAmount)}) +{' '}
                                                    <strong>
                                                        Scholarship Adjustment
                                                    </strong>{' '}
                                                    (−{currency(activeDiscount)})
                                                </span>
                                                <span className="font-bold text-purple-700">
                                                    Net:{' '}
                                                    {currency(
                                                        activeAmount -
                                                            activeDiscount,
                                                    )}
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            ) : (
                                <div className="grid grid-cols-1 gap-4 rounded-lg border border-emerald-100 bg-emerald-50/40 p-4 sm:grid-cols-2">
                                    <div>
                                        <label className="mb-1 block text-xs font-medium text-[#334E68]">
                                            {data.entry_type === 'payment'
                                                ? 'Payment Amount (₱) *'
                                                : 'Adjustment Amount (₱) *'}
                                        </label>
                                        <Input
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            value={data.amount}
                                            onChange={(event) =>
                                                setData(
                                                    'amount',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="0.00"
                                            className="font-bold text-emerald-900"
                                            required
                                        />
                                        <FieldError
                                            message={(errors as any).amount}
                                        />
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-xs font-medium text-[#334E68]">
                                            Remarks
                                        </label>
                                        <Input
                                            value={data.remarks}
                                            onChange={(event) =>
                                                setData(
                                                    'remarks',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Optional transaction note"
                                        />
                                    </div>
                                </div>
                            )}

                            {data.entry_type === 'ar' && (
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-[#334E68]">
                                        Remarks
                                    </label>
                                    <Input
                                        value={data.remarks}
                                        onChange={(event) =>
                                            setData('remarks', event.target.value)
                                        }
                                        placeholder="Optional transaction note"
                                    />
                                </div>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <Card className="border-[#CFE3FF] bg-white">
                        <CardHeader className="border-b border-[#EAF2FF] bg-[#F8FBFF] pb-3">
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="text-sm font-bold text-[#0B3D91]">
                                        2. Multi-Line Transaction Items
                                    </CardTitle>
                                    <CardDescription className="text-xs text-[#7FA6D6]">
                                        Add multiple fees, payments, or
                                        scholarship lines in one submission.
                                    </CardDescription>
                                </div>
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={addBatchRow}
                                    className="bg-[#0F6FFF] text-white hover:bg-[#0B5DDB]"
                                >
                                    <Plus className="mr-1 h-3.5 w-3.5" /> Add
                                    Line Item
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-4">
                            <div className="space-y-3">
                                {batchItems.map((item, index) => {
                                    const units = Number(item.units || 0);
                                    const rate = Number(
                                        item.tuition_per_unit_or_misc || 0,
                                    );
                                    const lineTotal =
                                        item.amount !== ''
                                            ? Number(item.amount || 0)
                                            : units * rate;
                                    const lineDiscount = discountForHonor(
                                        lineTotal,
                                        item.latin_honor,
                                    );

                                    return (
                                        <div
                                            key={item.id}
                                            className="relative rounded-lg border border-[#CFE3FF] bg-[#FAFCFF] p-3 shadow-2xs"
                                        >
                                            <div className="mb-2 flex items-center justify-between">
                                                <span className="text-xs font-bold text-[#0B3D91]">
                                                    Line #{index + 1}
                                                </span>
                                                {batchItems.length > 1 && (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            removeBatchRow(
                                                                item.id,
                                                            )
                                                        }
                                                        className="text-xs text-red-500 hover:text-red-700"
                                                        aria-label="Remove line item"
                                                    >
                                                        <Trash2 className="h-3.5 w-3.5" />
                                                    </button>
                                                )}
                                            </div>

                                            <div className="grid grid-cols-1 gap-2 sm:grid-cols-6">
                                                <div className="sm:col-span-1">
                                                    <label className="text-[11px] font-medium text-[#334E68]">
                                                        Type
                                                    </label>
                                                    <select
                                                        value={item.entry_type}
                                                        onChange={(event) => {
                                                            const nextType =
                                                                event.target
                                                                    .value as EntryType;
                                                            updateBatchRow(
                                                                item.id,
                                                                {
                                                                    entry_type:
                                                                        nextType,
                                                                    particulars:
                                                                        nextType ===
                                                                        'payment'
                                                                            ? 'Payment'
                                                                            : nextType ===
                                                                                'adjustment'
                                                                              ? 'Adjustment'
                                                                              : 'Tuition',
                                                                    latin_honor:
                                                                        nextType ===
                                                                        'ar'
                                                                            ? item.latin_honor
                                                                            : '',
                                                                },
                                                            );
                                                        }}
                                                        className="w-full rounded-md border border-[#CFE3FF] bg-white px-2 py-1.5 text-xs"
                                                    >
                                                        <option value="ar">
                                                            AR
                                                        </option>
                                                        <option value="payment">
                                                            Payment
                                                        </option>
                                                        <option value="adjustment">
                                                            Adjustment
                                                        </option>
                                                    </select>
                                                </div>

                                                <div className="sm:col-span-2">
                                                    <label className="text-[11px] font-medium text-[#334E68]">
                                                        Particulars
                                                    </label>
                                                    <Input
                                                        value={item.particulars}
                                                        onChange={(event) =>
                                                            updateBatchRow(
                                                                item.id,
                                                                {
                                                                    particulars:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                },
                                                            )
                                                        }
                                                        placeholder="e.g. Tuition, Misc"
                                                        className="h-8 text-xs"
                                                    />
                                                </div>

                                                <div className="sm:col-span-1">
                                                    <label className="text-[11px] font-medium text-[#334E68]">
                                                        Ref / OR #
                                                    </label>
                                                    <Input
                                                        value={
                                                            item.reference_or_jev_number
                                                        }
                                                        onChange={(event) =>
                                                            updateBatchRow(
                                                                item.id,
                                                                {
                                                                    reference_or_jev_number:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                },
                                                            )
                                                        }
                                                        placeholder="OR/JEV"
                                                        className="h-8 text-xs"
                                                    />
                                                </div>

                                                {item.entry_type === 'ar' ? (
                                                    <>
                                                        <div className="sm:col-span-1">
                                                            <label className="text-[11px] font-medium text-[#334E68]">
                                                                Units × Rate
                                                            </label>
                                                            <div className="flex gap-1">
                                                                <Input
                                                                    type="number"
                                                                    min="0"
                                                                    value={
                                                                        item.units
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        updateBatchRow(
                                                                            item.id,
                                                                            {
                                                                                units: event
                                                                                    .target
                                                                                    .value,
                                                                            },
                                                                        )
                                                                    }
                                                                    placeholder="U"
                                                                    className="h-8 w-12 text-xs"
                                                                />
                                                                <Input
                                                                    type="number"
                                                                    step="0.01"
                                                                    min="0"
                                                                    value={
                                                                        item.tuition_per_unit_or_misc
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        updateBatchRow(
                                                                            item.id,
                                                                            {
                                                                                tuition_per_unit_or_misc:
                                                                                    event
                                                                                        .target
                                                                                        .value,
                                                                            },
                                                                        )
                                                                    }
                                                                    placeholder="Rate"
                                                                    className="h-8 flex-1 text-xs"
                                                                />
                                                            </div>
                                                        </div>

                                                        <div className="sm:col-span-1">
                                                            <label className="text-[11px] font-medium text-[#334E68]">
                                                                Amount
                                                            </label>
                                                            <Input
                                                                type="number"
                                                                step="0.01"
                                                                min="0"
                                                                value={
                                                                    item.amount !==
                                                                    ''
                                                                        ? item.amount
                                                                        : lineTotal >
                                                                            0
                                                                          ? lineTotal.toFixed(
                                                                                2,
                                                                            )
                                                                          : ''
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    updateBatchRow(
                                                                        item.id,
                                                                        {
                                                                            amount: event
                                                                                .target
                                                                                .value,
                                                                        },
                                                                    )
                                                                }
                                                                placeholder={
                                                                    lineTotal > 0
                                                                        ? lineTotal.toFixed(
                                                                              2,
                                                                          )
                                                                        : '0.00'
                                                                }
                                                                className="h-8 text-xs font-bold text-blue-900"
                                                            />
                                                        </div>
                                                    </>
                                                ) : (
                                                    <div className="sm:col-span-2">
                                                        <label className="text-[11px] font-medium text-[#334E68]">
                                                            Amount (₱)
                                                        </label>
                                                        <Input
                                                            type="number"
                                                            step="0.01"
                                                            min="0.01"
                                                            value={item.amount}
                                                            onChange={(event) =>
                                                                updateBatchRow(
                                                                    item.id,
                                                                    {
                                                                        amount: event
                                                                            .target
                                                                            .value,
                                                                    },
                                                                )
                                                            }
                                                            placeholder="0.00"
                                                            className="h-8 text-xs font-bold text-emerald-900"
                                                        />
                                                    </div>
                                                )}
                                            </div>

                                            {item.entry_type === 'ar' && (
                                                <div className="mt-2 border-t border-[#EAF2FF] pt-2">
                                                    <div className="flex items-center justify-between text-[11px]">
                                                        <span className="text-[#5C7A9E]">
                                                            Latin Honor
                                                            Scholarship:
                                                        </span>
                                                        <div className="flex flex-wrap gap-1">
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    updateBatchRow(
                                                                        item.id,
                                                                        {
                                                                            latin_honor:
                                                                                '',
                                                                        },
                                                                    )
                                                                }
                                                                className={`rounded px-2 py-0.5 text-[10px] ${
                                                                    !item.latin_honor
                                                                        ? 'bg-slate-200 font-bold'
                                                                        : 'text-slate-500'
                                                                }`}
                                                            >
                                                                None
                                                            </button>
                                                            {latinHonorOptions.map(
                                                                (option) => (
                                                                    <button
                                                                        key={
                                                                            option.value
                                                                        }
                                                                        type="button"
                                                                        onClick={() =>
                                                                            updateBatchRow(
                                                                                item.id,
                                                                                {
                                                                                    latin_honor:
                                                                                        option.value,
                                                                                },
                                                                            )
                                                                        }
                                                                        className={`rounded px-2 py-0.5 text-[10px] ${
                                                                            item.latin_honor ===
                                                                            option.value
                                                                                ? 'bg-purple-600 font-bold text-white'
                                                                                : 'border border-purple-200 text-purple-700'
                                                                        }`}
                                                                    >
                                                                        {
                                                                            option.label
                                                                        }
                                                                    </button>
                                                                ),
                                                            )}
                                                        </div>
                                                    </div>

                                                    {item.latin_honor && (
                                                        <div className="mt-2 flex items-center gap-2 rounded-md border border-dashed border-purple-300 bg-purple-50/60 px-3 py-2 text-[11px] text-purple-800">
                                                            <Sparkles className="h-3.5 w-3.5 shrink-0 text-purple-500" />
                                                            <div className="flex-1">
                                                                <span className="font-semibold">
                                                                    Auto-generated
                                                                    on save:{' '}
                                                                </span>
                                                                <span>
                                                                    Scholarship
                                                                    Adjustment —
                                                                    credit of{' '}
                                                                    <strong>
                                                                        −
                                                                        {currency(
                                                                            lineDiscount,
                                                                        )}
                                                                    </strong>{' '}
                                                                    will be posted
                                                                    automatically.
                                                                    Net line impact:{' '}
                                                                    <strong>
                                                                        {currency(
                                                                            lineTotal -
                                                                                lineDiscount,
                                                                        )}
                                                                    </strong>
                                                                    .
                                                                </span>
                                                            </div>
                                                        </div>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>

                            <div className="grid grid-cols-3 gap-2 rounded-lg border border-[#CFE3FF] bg-[#F7FAFF] p-3 text-xs">
                                <div>
                                    <p className="text-[10px] font-semibold text-blue-700 uppercase">
                                        Total Charges (AR)
                                    </p>
                                    <p className="font-bold text-blue-900">
                                        {currency(batchTotals.arTotal)}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-[10px] font-semibold text-emerald-700 uppercase">
                                        Total Credits
                                    </p>
                                    <p className="font-bold text-emerald-900">
                                        {currency(batchTotals.creditTotal)}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-[10px] font-semibold text-purple-700 uppercase">
                                        Net Student Impact
                                    </p>
                                    <p className="font-bold text-purple-900">
                                        {currency(batchTotals.netChange)}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <div className="flex items-center justify-between rounded-lg border border-[#CFE3FF] bg-white p-4 shadow-xs">
                    <div className="text-xs text-[#5C7A9E]">
                        Recorded by{' '}
                        <strong className="text-[#0B3D91]">
                            {authUserName || 'Logged-in Staff'}
                        </strong>
                    </div>

                    <Button
                        type="submit"
                        disabled={processing}
                        className="bg-[#0F6FFF] px-6 text-white hover:bg-[#0B5DDB] disabled:opacity-60"
                    >
                        {processing ? (
                            <span className="flex items-center gap-2">
                                <Spinner className="h-4 w-4" />
                                Saving...
                            </span>
                        ) : isBatchMode ? (
                            `Save ${batchItems.length} Transactions`
                        ) : (
                            'Save Transaction'
                        )}
                    </Button>
                </div>
            </form>
        </div>
    );
}
