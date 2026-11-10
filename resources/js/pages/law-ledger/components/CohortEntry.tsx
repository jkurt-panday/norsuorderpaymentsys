import { router } from '@inertiajs/react';
import {
    ReceiptText,
    Wallet,
    ArrowDownUp,
    Users,
    Search,
    X,
    Trash2,
    Sparkles,
    CheckSquare,
    Square,
} from 'lucide-react';
import React, { useState, useMemo } from 'react';
import { cohortStore } from '@/actions/App/Http/Controllers/LawSchoolLedgerController';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import type {
    CohortStudentOption,
    CohortCourseOption,
    CohortAcademicTermOption,
    CohortEntryType,
    CohortRowOverride,
} from '@/types/cohort';
import { flashToast } from '@/utils/flashToast';

const semesterOptions = [
    { label: '1st Sem.', value: 'First Semester' },
    { label: '2nd Sem.', value: 'Second Semester' },
    { label: 'Summer', value: 'Summer' },
];

const particularPresets: Record<CohortEntryType, string[]> = {
    ar: ['Tuition', 'Registration', 'Miscellaneous', 'Bar Review Fee', 'Library Fee'],
    payment: ['Payment', 'Downpayment', 'Midterm Payment', 'Final Payment', 'Graduation Fee Payment'],
    adjustment: ['Adjustment', 'Discount', 'Correction', 'Refund'],
};

const latinHonorOptions = [
    { value: 'SUMMA', label: 'Summa Cum Laude (100%)', discountRate: 1 },
    { value: 'MAGNA', label: 'Magna Cum Laude (75%)', discountRate: 0.75 },
    { value: 'CUM_LAUDE', label: 'Cum Laude (50%)', discountRate: 0.5 },
] as const;

function discountForHonor(amount: number, latinHonor?: string | null): number {
    const option = latinHonorOptions.find((item) => item.value === latinHonor);
    return option ? amount * option.discountRate : 0;
}

const entryTypeOptions = [
    {
        value: 'ar' as const,
        label: 'Accounts Receivable (AR)',
        short: 'AR',
        icon: ReceiptText,
        color: 'border-blue-500 text-blue-700 bg-blue-50 dark:border-blue-500 dark:text-blue-400 dark:bg-blue-950/40',
    },
    {
        value: 'payment' as const,
        label: 'Payment (OR)',
        short: 'Payment',
        icon: Wallet,
        color: 'border-emerald-500 text-emerald-700 bg-emerald-50 dark:border-emerald-500 dark:text-emerald-400 dark:bg-emerald-950/40',
    },
    {
        value: 'adjustment' as const,
        label: 'Adjustment',
        short: 'Adjustment',
        icon: ArrowDownUp,
        color: 'border-purple-500 text-purple-700 bg-purple-50 dark:border-purple-500 dark:text-purple-400 dark:bg-purple-950/40',
    },
];

interface Props {
    students: CohortStudentOption[];
    courses: CohortCourseOption[];
    academicTerms: CohortAcademicTermOption[];
    authUserName: string;
}

function formatStudentLabel(s: CohortStudentOption): string {
    const studentName = !s.first_name
        ? String(s.last_name)
        : `${s.last_name}, ${s.first_name}${s.middle_name ? ` ${s.middle_name.charAt(0).toUpperCase()}.` : ''}`;

    if (s.student_number) {
        return `${s.student_number} — ${studentName}`;
    }

    return studentName;
}

function currency(val: number): string {
    return `₱${Number(val || 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

export default function LawCohortEntry({
    students,
    courses,
    academicTerms,
    authUserName,
}: Props) {
    // ── Template State ──
    const [courseId, setCourseId] = useState<string>(
        courses.length > 0 ? String(courses[0].id) : '',
    );
    const [academicTermId, setAcademicTermId] = useState<string>(
        academicTerms.length > 0 ? String(academicTerms[0].id) : '',
    );
    const [schoolYear, setSchoolYear] = useState<string>(
        academicTerms.length > 0 ? academicTerms[0].school_year : '',
    );
    const [semester, setSemester] = useState<string>(
        academicTerms.length > 0 ? academicTerms[0].semester : 'First Semester',
    );
    const [entryType, setEntryType] = useState<CohortEntryType>('ar');
    const [transactionDate, setTransactionDate] = useState<string>(
        new Date().toISOString().slice(0, 10),
    );
    const [particulars, setParticulars] = useState<string>('Tuition');
    const [referenceNumber, setReferenceNumber] = useState<string>('');
    const [units, setUnits] = useState<string>('');
    const [rate, setRate] = useState<string>('');
    const [amount, setAmount] = useState<string>('');
    const [latinHonor, setLatinHonor] = useState<'SUMMA' | 'MAGNA' | 'CUM_LAUDE' | ''>('');
    const [remarks, setRemarks] = useState<string>('');

    // ── Student Multi-Selection State ──
    const [filterCourseOnly, setFilterCourseOnly] = useState<boolean>(true);
    const [studentSearch, setStudentSearch] = useState<string>('');
    const [selectedStudentIds, setSelectedStudentIds] = useState<Set<number>>(new Set());

    // ── Row Overrides: map studentId -> override values ──
    const [rowOverrides, setRowOverrides] = useState<Map<number, Partial<CohortRowOverride>>>(
        new Map(),
    );

    // ── Submission State ──
    const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const isAR = entryType === 'ar';

    function handleTermChange(termId: string) {
        setAcademicTermId(termId);
        const match = academicTerms.find((t) => String(t.id) === String(termId));
        if (match) {
            setSchoolYear(match.school_year);
            setSemester(match.semester);
        }
    }

    function handleTypeChange(nextType: CohortEntryType) {
        setEntryType(nextType);
        setParticulars(particularPresets[nextType][0] ?? 'Tuition');
        if (nextType !== 'ar') {
            setLatinHonor('');
        }
    }

    // Filter students eligible for multi-selection
    const filteredAvailableStudents = useMemo(() => {
        let pool = students;

        if (filterCourseOnly && courseId) {
            pool = pool.filter((s) => {
                const sCourse = s.last_course_id ?? s.course_id;
                return sCourse ? String(sCourse) === String(courseId) : true;
            });
        }

        const query = studentSearch.toLowerCase().trim();
        if (query) {
            pool = pool.filter((s) => {
                const label = formatStudentLabel(s).toLowerCase();
                return label.includes(query);
            });
        }

        return pool;
    }, [students, courseId, filterCourseOnly, studentSearch]);

    // Compute template line amount
    const parsedTemplateUnits = Number(units || 0);
    const parsedTemplateRate = Number(rate || 0);
    const computedTemplateAmount = parsedTemplateUnits * parsedTemplateRate;
    const defaultTemplateAmount = isAR && amount === '' ? computedTemplateAmount : Number(amount || 0);

    // Build the list of active cohort rows
    const cohortRows: CohortRowOverride[] = useMemo(() => {
        const studentMap = new Map<number, CohortStudentOption>();
        students.forEach((s) => studentMap.set(Number(s.id), s));

        return Array.from(selectedStudentIds).map((id) => {
            const student = studentMap.get(id);
            const override = rowOverrides.get(id) || {};

            const rowUnits = override.units !== undefined ? override.units : parsedTemplateUnits;
            const rowRate = override.rate !== undefined ? override.rate : parsedTemplateRate;
            let rowAmount = override.amount;

            if (rowAmount === undefined) {
                if (isAR && amount === '') {
                    rowAmount = rowUnits * rowRate;
                } else {
                    rowAmount = defaultTemplateAmount;
                }
            }

            const rowHonor = override.latin_honor !== undefined ? override.latin_honor : latinHonor;
            const rowDiscount = isAR && rowHonor ? discountForHonor(rowAmount, rowHonor) : 0;
            const rowRemarks = override.remarks !== undefined ? override.remarks : remarks;

            const studentLabel = student ? formatStudentLabel(student) : `Student #${id}`;
            const studentNumber = student?.student_number || '';

            return {
                student_id: id,
                student_name: studentLabel,
                student_number: studentNumber,
                units: rowUnits,
                rate: rowRate,
                amount: rowAmount,
                latin_honor: rowHonor,
                discount_amount: rowDiscount,
                remarks: rowRemarks,
            };
        });
    }, [
        selectedStudentIds,
        students,
        rowOverrides,
        parsedTemplateUnits,
        parsedTemplateRate,
        isAR,
        amount,
        defaultTemplateAmount,
        latinHonor,
        remarks,
    ]);

    const totalBatchAmount = useMemo(() => {
        return cohortRows.reduce((sum, r) => sum + (Number(r.amount) || 0), 0);
    }, [cohortRows]);

    const totalDiscountAmount = useMemo(() => {
        return cohortRows.reduce((sum, r) => sum + (Number(r.discount_amount) || 0), 0);
    }, [cohortRows]);

    // Student selection handlers
    function toggleStudent(id: number) {
        setSelectedStudentIds((prev) => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    }

    function selectAllFiltered() {
        setSelectedStudentIds((prev) => {
            const next = new Set(prev);
            filteredAvailableStudents.forEach((s) => next.add(Number(s.id)));
            return next;
        });
    }

    function deselectAll() {
        setSelectedStudentIds(new Set());
        setRowOverrides(new Map());
    }

    function removeStudent(id: number) {
        setSelectedStudentIds((prev) => {
            const next = new Set(prev);
            next.delete(id);
            return next;
        });
        setRowOverrides((prev) => {
            const next = new Map(prev);
            next.delete(id);
            return next;
        });
    }

    function updateRowOverride(id: number, patch: Partial<CohortRowOverride>) {
        setRowOverrides((prev) => {
            const next = new Map(prev);
            const current = next.get(id) || {};
            next.set(id, { ...current, ...patch });
            return next;
        });
    }

    // Submit handler
    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        setErrors({});

        if (!courseId) {
            flashToast('error', 'Please select a course.');
            return;
        }

        if (cohortRows.length === 0) {
            flashToast('error', 'Please select at least one student.');
            return;
        }

        const zeroRowIndex = cohortRows.findIndex((r) => !r.amount || Number(r.amount) <= 0);
        if (zeroRowIndex !== -1) {
            const row = cohortRows[zeroRowIndex];
            flashToast('error', `${row.student_name} has an invalid amount (must be > 0).`);
            return;
        }

        setIsSubmitting(true);

        const payload = {
            course_id: courseId,
            academic_term_id: academicTermId || null,
            school_year: schoolYear,
            semester: semester,
            entry_type: entryType,
            transaction_date: transactionDate,
            particulars: particulars || 'Tuition',
            reference_number: referenceNumber || null,
            status: 'Active',
            tuition_per_unit_or_fee_per_semester: rate || '0.00',
            rate: rate || '0.00',
            cohort_rows: cohortRows.map((r) => {
                const discount = isAR && r.latin_honor ? discountForHonor(Number(r.amount), r.latin_honor) : 0;
                return {
                    student_id: r.student_id,
                    units: r.units || 0,
                    rate: r.rate || '0.00',
                    amount: Number(r.amount).toFixed(2),
                    latin_honor: isAR && r.latin_honor ? r.latin_honor : null,
                    discount_amount: isAR && r.latin_honor ? discount.toFixed(2) : null,
                    remarks: r.remarks || null,
                };
            }),
        };

        router.post(cohortStore.url(), payload, {
            preserveScroll: true,
            onError: (errs) => {
                setErrors(errs);
                setIsSubmitting(false);
                flashToast('error', 'Failed to post entries. Please review the errors.');
            },
            onFinish: () => {
                setIsSubmitting(false);
            },
        });
    }

    const selectClass =
        'w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-ring focus:outline-none';

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            {/* ── SECTION 1: TRANSACTION TEMPLATE ── */}
            <Card className="border-border bg-card">
                <CardHeader className="border-b border-border/50 bg-muted/30 pb-3">
                    <div className="flex items-center justify-between">
                        <div>
                            <CardTitle className="text-sm font-bold text-foreground">
                                1. Multi-Student Template
                            </CardTitle>
                            <CardDescription className="text-xs">
                                Configure the base transaction that will be posted for every selected student.
                            </CardDescription>
                        </div>
                        <span className="rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-semibold text-primary">
                            Multi-Student Template
                        </span>
                    </div>
                </CardHeader>
                <CardContent className="space-y-4 pt-4">
                    {/* Entry Type Selection */}
                    <div>
                        <label className="mb-2 block text-xs font-semibold text-foreground">
                            Entry Type <span className="text-destructive">*</span>
                        </label>
                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-3">
                            {entryTypeOptions.map((opt) => {
                                const Icon = opt.icon;
                                const isSelected = entryType === opt.value;
                                return (
                                    <button
                                        key={opt.value}
                                        type="button"
                                        onClick={() => handleTypeChange(opt.value)}
                                        className={`flex items-center gap-2.5 rounded-lg border-2 p-3 text-left transition-all ${
                                            isSelected
                                                ? `${opt.color} shadow-xs font-bold`
                                                : 'border-border bg-card text-muted-foreground hover:border-primary/40 hover:text-foreground'
                                        }`}
                                    >
                                        <Icon className="h-5 w-5 shrink-0" />
                                        <div>
                                            <p className="text-xs font-semibold leading-tight">{opt.short}</p>
                                            <p className="text-[10px] text-muted-foreground">{opt.label}</p>
                                        </div>
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    {/* Academic Term & Course */}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div className="sm:col-span-2">
                            <label className="mb-1 block text-xs font-semibold text-foreground">
                                Course <span className="text-destructive">*</span>
                            </label>
                            <select
                                value={courseId}
                                onChange={(e) => setCourseId(e.target.value)}
                                className={selectClass}
                                required
                            >
                                <option value="" disabled>Select course...</option>
                                {courses.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.code} {c.name ? `— ${c.name}` : ''}
                                    </option>
                                ))}
                            </select>
                            {errors.course_id && (
                                <p className="mt-1 text-xs text-destructive">{errors.course_id}</p>
                            )}
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-semibold text-foreground">
                                Academic Term <span className="text-destructive">*</span>
                            </label>
                            {academicTerms.length > 0 ? (
                                <select
                                    value={academicTermId}
                                    onChange={(e) => handleTermChange(e.target.value)}
                                    className={selectClass}
                                >
                                    {academicTerms.map((t) => (
                                        <option key={t.id} value={t.id}>
                                            {t.school_year} — {t.semester}
                                        </option>
                                    ))}
                                </select>
                            ) : (
                                <div className="grid grid-cols-2 gap-2">
                                    <Input
                                        placeholder="2025-2026"
                                        value={schoolYear}
                                        onChange={(e) => setSchoolYear(e.target.value)}
                                    />
                                    <select
                                        value={semester}
                                        onChange={(e) => setSemester(e.target.value)}
                                        className={selectClass}
                                    >
                                        {semesterOptions.map((s) => (
                                            <option key={s.value} value={s.value}>{s.label}</option>
                                        ))}
                                    </select>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Particulars, Date, Reference */}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label className="mb-1 block text-xs font-semibold text-foreground">
                                Particulars <span className="text-destructive">*</span>
                            </label>
                            <div className="space-y-1.5">
                                <Input
                                    value={particulars}
                                    onChange={(e) => setParticulars(e.target.value)}
                                    placeholder="e.g. Tuition"
                                    required
                                />
                                <div className="flex flex-wrap gap-1">
                                    {particularPresets[entryType].map((preset) => (
                                        <button
                                            key={preset}
                                            type="button"
                                            onClick={() => setParticulars(preset)}
                                            className="rounded bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground hover:bg-muted/80 hover:text-foreground"
                                        >
                                            {preset}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-semibold text-foreground">
                                Transaction Date <span className="text-destructive">*</span>
                            </label>
                            <Input
                                type="date"
                                value={transactionDate}
                                onChange={(e) => setTransactionDate(e.target.value)}
                                required
                            />
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-semibold text-foreground">
                                Reference / OR / JEV #
                            </label>
                            <Input
                                value={referenceNumber}
                                onChange={(e) => setReferenceNumber(e.target.value)}
                                placeholder="e.g. OR-2026-001"
                            />
                        </div>
                    </div>

                    {/* Units, Rate, Amount & Latin Honor */}
                    <div className="rounded-lg border border-border/60 bg-muted/20 p-3">
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-4">
                            {isAR && (
                                <>
                                    <div>
                                        <label className="mb-1 block text-xs font-semibold text-foreground">
                                            Default Units
                                        </label>
                                        <Input
                                            type="number"
                                            min="0"
                                            value={units}
                                            onChange={(e) => setUnits(e.target.value)}
                                            placeholder="e.g. 18"
                                        />
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-xs font-semibold text-foreground">
                                            Default Rate (₱)
                                        </label>
                                        <Input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={rate}
                                            onChange={(e) => setRate(e.target.value)}
                                            placeholder="e.g. 750.00"
                                        />
                                    </div>
                                </>
                            )}

                            <div>
                                <label className="mb-1 block text-xs font-semibold text-foreground">
                                    {isAR ? 'Default Amount (auto or fixed)' : 'Amount (₱) *'}
                                </label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={amount}
                                    onChange={(e) => setAmount(e.target.value)}
                                    placeholder={
                                        isAR
                                            ? computedTemplateAmount > 0
                                                ? `Auto (₱${computedTemplateAmount.toFixed(2)})`
                                                : '0.00'
                                            : 'e.g. 2000.00'
                                    }
                                    required={!isAR}
                                />
                                {isAR && amount === '' && computedTemplateAmount > 0 && (
                                    <p className="mt-1 text-[11px] text-muted-foreground">
                                        Auto: {units} × ₱{rate} = ₱{computedTemplateAmount.toFixed(2)}
                                    </p>
                                )}
                            </div>

                            {isAR && (
                                <div>
                                    <label className="mb-1 block text-xs font-semibold text-foreground">
                                        Default Latin Honor
                                    </label>
                                    <select
                                        value={latinHonor}
                                        onChange={(e) =>
                                            setLatinHonor(
                                                e.target.value as 'SUMMA' | 'MAGNA' | 'CUM_LAUDE' | '',
                                            )
                                        }
                                        className={selectClass}
                                    >
                                        <option value="">None (Standard)</option>
                                        {latinHonorOptions.map((opt) => (
                                            <option key={opt.value} value={opt.value}>
                                                {opt.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}

                            {!isAR && (
                                <div className="sm:col-span-3">
                                    <label className="mb-1 block text-xs font-semibold text-foreground">
                                        Remarks
                                    </label>
                                    <Input
                                        value={remarks}
                                        onChange={(e) => setRemarks(e.target.value)}
                                        placeholder="Optional notes or memos"
                                    />
                                </div>
                            )}
                        </div>

                        {isAR && (
                            <div className="mt-3">
                                <label className="mb-1 block text-xs font-semibold text-foreground">
                                    Remarks
                                </label>
                                <Input
                                    value={remarks}
                                    onChange={(e) => setRemarks(e.target.value)}
                                    placeholder="Optional notes or memos"
                                />
                            </div>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* ── SECTION 2: STUDENT MULTI-PICKER ── */}
            <Card className="border-border bg-card">
                <CardHeader className="border-b border-border/50 bg-muted/30 pb-3">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <CardTitle className="text-sm font-bold text-foreground">
                                2. Select Students ({selectedStudentIds.size} Selected)
                            </CardTitle>
                            <CardDescription className="text-xs">
                                Check the law students who should receive this transaction.
                            </CardDescription>
                        </div>
                        <div className="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={selectAllFiltered}
                                className="text-xs"
                                disabled={filteredAvailableStudents.length === 0}
                            >
                                <CheckSquare className="mr-1.5 h-3.5 w-3.5" />
                                Select All Filtered ({filteredAvailableStudents.length})
                            </Button>
                            {selectedStudentIds.size > 0 && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={deselectAll}
                                    className="text-xs text-muted-foreground hover:text-foreground"
                                >
                                    Clear Selection
                                </Button>
                            )}
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="space-y-3 pt-4">
                    {/* Search & Filter Controls */}
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <div className="relative flex-1">
                            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={studentSearch}
                                onChange={(e) => setStudentSearch(e.target.value)}
                                placeholder="Search by student ID, last name, or first name..."
                                className="pl-9 text-xs"
                            />
                            {studentSearch && (
                                <button
                                    type="button"
                                    onClick={() => setStudentSearch('')}
                                    className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                >
                                    <X className="h-3.5 w-3.5" />
                                </button>
                            )}
                        </div>

                        <label className="flex cursor-pointer items-center gap-2 text-xs font-medium text-foreground">
                            <input
                                type="checkbox"
                                checked={filterCourseOnly}
                                onChange={(e) => setFilterCourseOnly(e.target.checked)}
                                className="rounded border-input text-primary focus:ring-primary"
                            />
                            Filter to selected course only
                        </label>
                    </div>

                    {/* Student List Box */}
                    <div className="max-h-60 overflow-y-auto rounded-md border border-border bg-background p-2">
                        {filteredAvailableStudents.length === 0 ? (
                            <div className="py-8 text-center text-xs text-muted-foreground">
                                No students match the filter criteria.
                            </div>
                        ) : (
                            <div className="grid grid-cols-1 gap-1 sm:grid-cols-2 lg:grid-cols-3">
                                {filteredAvailableStudents.map((s) => {
                                    const sId = Number(s.id);
                                    const isSelected = selectedStudentIds.has(sId);
                                    return (
                                        <button
                                            key={s.id}
                                            type="button"
                                            onClick={() => toggleStudent(sId)}
                                            className={`flex items-center gap-2 rounded-md p-2 text-left text-xs transition-colors ${
                                                isSelected
                                                    ? 'bg-primary/10 text-primary font-medium border border-primary/30'
                                                    : 'hover:bg-muted text-foreground'
                                            }`}
                                        >
                                            {isSelected ? (
                                                <CheckSquare className="h-4 w-4 shrink-0 text-primary" />
                                            ) : (
                                                <Square className="h-4 w-4 shrink-0 text-muted-foreground" />
                                            )}
                                            <span className="truncate">{formatStudentLabel(s)}</span>
                                        </button>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* ── SECTION 3: PREVIEW & OVERRIDES GRID ── */}
            {cohortRows.length > 0 && (
                <Card className="border-border bg-card">
                    <CardHeader className="border-b border-border/50 bg-muted/30 pb-3">
                        <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <CardTitle className="text-sm font-bold text-foreground">
                                    3. Preview & Per-Student Overrides ({cohortRows.length} Students)
                                </CardTitle>
                                <CardDescription className="text-xs">
                                    Inspect line amounts or override units, amounts, or Latin Honor per student.
                                </CardDescription>
                            </div>
                            <div className="text-right">
                                <span className="text-xs text-muted-foreground">Total Posting: </span>
                                <span className="text-base font-bold text-primary">
                                    {currency(totalBatchAmount)}
                                </span>
                                {totalDiscountAmount > 0 && (
                                    <span className="ml-2 text-xs text-emerald-600 dark:text-emerald-400">
                                        (Discounts: -{currency(totalDiscountAmount)})
                                    </span>
                                )}
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="max-h-96 overflow-auto">
                            <table className="w-full text-left text-xs">
                                <thead className="sticky top-0 z-10 border-b border-border bg-muted/60 text-muted-foreground backdrop-blur-xs">
                                    <tr>
                                        <th className="px-3 py-2 font-semibold">#</th>
                                        <th className="px-3 py-2 font-semibold">Student</th>
                                        {isAR && <th className="px-3 py-2 font-semibold">Units</th>}
                                        {isAR && <th className="px-3 py-2 font-semibold">Rate</th>}
                                        <th className="px-3 py-2 font-semibold">Amount (₱)</th>
                                        {isAR && <th className="px-3 py-2 font-semibold">Latin Honor</th>}
                                        <th className="px-3 py-2 font-semibold">Remarks</th>
                                        <th className="px-3 py-2 text-center font-semibold">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border/60">
                                    {cohortRows.map((row, idx) => (
                                        <tr key={row.student_id} className="hover:bg-muted/30">
                                            <td className="px-3 py-2 text-muted-foreground">{idx + 1}</td>
                                            <td className="px-3 py-2 font-medium text-foreground">
                                                <div className="max-w-[200px] truncate" title={row.student_name}>
                                                    {row.student_name}
                                                </div>
                                            </td>
                                            {isAR && (
                                                <td className="px-3 py-2">
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        value={row.units}
                                                        onChange={(e) => {
                                                            const u = Number(e.target.value || 0);
                                                            const r = row.rate;
                                                            updateRowOverride(row.student_id, {
                                                                units: u,
                                                                amount: u * r,
                                                            });
                                                        }}
                                                        className="w-16 rounded border border-input bg-background px-2 py-1 text-xs"
                                                    />
                                                </td>
                                            )}
                                            {isAR && (
                                                <td className="px-3 py-2">
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        value={row.rate}
                                                        onChange={(e) => {
                                                            const r = Number(e.target.value || 0);
                                                            const u = row.units;
                                                            updateRowOverride(row.student_id, {
                                                                rate: r,
                                                                amount: u * r,
                                                            });
                                                        }}
                                                        className="w-20 rounded border border-input bg-background px-2 py-1 text-xs"
                                                    />
                                                </td>
                                            )}
                                            <td className="px-3 py-2 font-semibold">
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0.01"
                                                    value={row.amount}
                                                    onChange={(e) =>
                                                        updateRowOverride(row.student_id, {
                                                            amount: Number(e.target.value || 0),
                                                        })
                                                    }
                                                    className="w-24 rounded border border-input bg-background px-2 py-1 text-xs font-semibold"
                                                />
                                            </td>
                                            {isAR && (
                                                <td className="px-3 py-2">
                                                    <select
                                                        value={row.latin_honor || ''}
                                                        onChange={(e) =>
                                                            updateRowOverride(row.student_id, {
                                                                latin_honor: e.target.value as 'SUMMA' | 'MAGNA' | 'CUM_LAUDE' | '',
                                                            })
                                                        }
                                                        className="rounded border border-input bg-background px-2 py-1 text-xs"
                                                    >
                                                        <option value="">None</option>
                                                        {latinHonorOptions.map((opt) => (
                                                            <option key={opt.value} value={opt.value}>
                                                                {opt.value}
                                                            </option>
                                                        ))}
                                                    </select>
                                                </td>
                                            )}
                                            <td className="px-3 py-2">
                                                <input
                                                    type="text"
                                                    value={row.remarks || ''}
                                                    onChange={(e) =>
                                                        updateRowOverride(row.student_id, {
                                                            remarks: e.target.value,
                                                        })
                                                    }
                                                    placeholder="Remarks"
                                                    className="w-28 rounded border border-input bg-background px-2 py-1 text-xs"
                                                />
                                            </td>
                                            <td className="px-3 py-2 text-center">
                                                <button
                                                    type="button"
                                                    onClick={() => removeStudent(row.student_id)}
                                                    className="rounded p-1 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                                    title="Remove student"
                                                >
                                                    <Trash2 className="h-3.5 w-3.5" />
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* ── SUBMIT BAR ── */}
            <div className="flex flex-col gap-3 rounded-lg border border-border bg-card p-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="text-xs text-muted-foreground">
                    {cohortRows.length === 0 ? (
                        <span>Select students above to preview the posting total.</span>
                    ) : (
                        <span>
                            Ready to post <strong className="text-foreground">{cohortRows.length}</strong> {entryType.toUpperCase()} transactions totaling{' '}
                            <strong className="text-foreground">{currency(totalBatchAmount)}</strong>.
                        </span>
                    )}
                </div>

                <Button
                    type="submit"
                    disabled={isSubmitting || cohortRows.length === 0}
                    className="min-w-[180px] bg-primary text-primary-foreground font-semibold"
                >
                    {isSubmitting ? (
                        <>
                            <Spinner className="mr-2 h-4 w-4" /> Posting...
                        </>
                    ) : (
                        <>
                            <Users className="mr-2 h-4 w-4" /> Post Entries ({cohortRows.length})
                        </>
                    )}
                </Button>
            </div>
        </form>
    );
}
