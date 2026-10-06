import { Head, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Calculator, Trash2 } from 'lucide-react';
import React, { useMemo, useState } from 'react';
import SearchableStudentSelect from './components/SearchableStudentSelect';
import type { StudentOption } from './components/SearchableStudentSelect';
import type { LawLedgerEntryType } from './constants';
import {
    entryTypeOptions,
    particularsOptions,
    semesterOptions,
} from './constants';
import {
    destroy as destroyLawLedger,
    index as lawLedgerIndex,
    update as updateLawLedger,
} from '@/actions/App/Http/Controllers/LawSchoolLedgerController';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
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

interface LawLedgerRecord {
    id: number;
    last_name: string;
    first_name: string;
    middle_initial: string | null;
    student_id: string | number | null;
    course_id: string | number | null;
    academic_term_id: string | number | null;
    course: string | null;
    school_year: string | null;
    semester: string | null;
    semester_or_summer: string | null;
    entry_type: LawLedgerEntryType | null;
    units: number | string | null;
    transaction_date: string | null;
    reference_jev_or_number: string | null;
    particulars: string | null;
    tuition_per_unit_or_fee_per_semester: number | string | null;
    ar_or_payment: string | null;
    amount: number | string | null;
    status: string | null;
    remarks: string | null;
    input_by: string | null;
}

interface CourseOption {
    id: string | number;
    code: string;
}

interface AcademicTermOption {
    id: string | number;
    school_year: string;
    semester: string;
}

interface UserOption {
    id: string | number;
    name: string;
}

interface EditTransactionProps {
    record: LawLedgerRecord;
    students: StudentOption[];
    courses: CourseOption[];
    academicTerms: AcademicTermOption[];
    users?: UserOption[];
    filterOptions?: {
        schoolYears: string[];
        statuses: string[];
    };
}

interface EditTransactionForm {
    student_id: string;
    course_id: string;
    academic_term_id: string;
    school_year: string;
    semester: string;
    entry_type: LawLedgerEntryType;
    units: string;
    transaction_date: string;
    reference_jev_or_number: string;
    particulars: string;
    tuition_per_unit_or_fee_per_semester: string;
    amount: string;
    status: string;
    remarks: string;
    input_by: string;
}

function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return <p className="mt-1 text-xs text-red-500">{message}</p>;
}

function formatCurrency(value: number | string | null | undefined): string {
    const numericValue = Number(value ?? 0);

    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(Number.isFinite(numericValue) ? numericValue : 0);
}

export default function EditTransaction({
    record,
    students,
    courses,
    academicTerms,
    users = [],
    filterOptions,
}: EditTransactionProps) {
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);
    const [deleteProcessing, setDeleteProcessing] = useState(false);

    const { data, setData, put, processing, errors, isDirty } =
        useForm<EditTransactionForm>({
            student_id: String(record.student_id ?? ''),
            course_id: String(record.course_id ?? ''),
            academic_term_id: String(record.academic_term_id ?? ''),
            school_year: record.school_year ?? '',
            semester:
                record.semester ??
                record.semester_or_summer ??
                'First Semester',
            entry_type: record.entry_type ?? 'ar',
            units: String(record.units ?? ''),
            transaction_date: record.transaction_date ?? '',
            reference_jev_or_number: record.reference_jev_or_number ?? '',
            particulars: record.particulars ?? 'Tuition',
            tuition_per_unit_or_fee_per_semester: String(
                record.tuition_per_unit_or_fee_per_semester ?? '',
            ),
            amount: String(record.amount ?? ''),
            status: record.status ?? 'Pending',
            remarks: record.remarks ?? '',
            input_by: record.input_by ?? '',
        });

    const selectedStudent = students.find(
        (student) => String(student.id) === data.student_id,
    );

    const selectedCourse = courses.find(
        (course) => String(course.id) === data.course_id,
    );

    const schoolYearOptions = useMemo(() => {
        const years = new Set<string>();

        if (data.school_year) {
            years.add(data.school_year);
        }

        academicTerms.forEach((term) => years.add(term.school_year));
        filterOptions?.schoolYears?.forEach((year) => years.add(year));

        return Array.from(years).sort().reverse();
    }, [academicTerms, data.school_year, filterOptions?.schoolYears]);

    const statusOptions = useMemo(() => {
        const statuses = new Set<string>();

        if (data.status) {
            statuses.add(data.status);
        }

        filterOptions?.statuses?.forEach((status) => statuses.add(status));

        if (statuses.size === 0) {
            statuses.add('Pending');
            statuses.add('Paid');
        }

        return Array.from(statuses);
    }, [data.status, filterOptions?.statuses]);

    const computedAmount = useMemo(() => {
        const units = Number(data.units || 0);
        const rate = Number(data.tuition_per_unit_or_fee_per_semester || 0);
        const amount = units * rate;

        return Number.isFinite(amount) ? amount : 0;
    }, [data.tuition_per_unit_or_fee_per_semester, data.units]);

    function syncTerm(schoolYear: string, semester: string) {
        const matchingTerm = academicTerms.find(
            (term) =>
                term.school_year === schoolYear && term.semester === semester,
        );

        setData((currentData) => ({
            ...currentData,
            school_year: schoolYear,
            semester,
            academic_term_id: String(matchingTerm?.id ?? ''),
        }));
    }

    const handleSubmit = (event: React.FormEvent) => {
        event.preventDefault();

        put(updateLawLedger.url(record.id), {
            preserveScroll: true,
        });
    };

    const handleNavigateBack = () => {
        if (isDirty && !window.confirm('Discard unsaved changes?')) {
            return;
        }

        router.get(lawLedgerIndex.url());
    };

    const confirmDelete = () => {
        setDeleteProcessing(true);

        router.delete(destroyLawLedger.url(record.id), {
            preserveScroll: true,
            onFinish: () => {
                setDeleteProcessing(false);
                setShowDeleteDialog(false);
            },
        });
    };

    const fullName = selectedStudent
        ? `${selectedStudent.last_name}, ${selectedStudent.first_name}${selectedStudent.middle_name ? ` ${selectedStudent.middle_name.charAt(0).toUpperCase()}.` : ''}`
        : [record.last_name, record.first_name, record.middle_initial ?? '']
              .filter(Boolean)
              .join(', ');

    const selectedEntryLabel =
        entryTypeOptions.find((option) => option.value === data.entry_type)
            ?.label ?? 'Adjustment';

    return (
        <div className="min-h-full bg-[#FAFAF5] p-4 md:p-8">
            <Head title="Edit Transaction - Law School Ledger" />
            <div className="mx-auto max-w-5xl space-y-6">
                <div className="flex items-center justify-between border-b border-[#CFE3FF] pb-4">
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleNavigateBack}
                            className="border-[#CFE3FF] text-[#0B3D91]"
                        >
                            <ArrowLeft className="mr-1 h-4 w-4" /> Back
                        </Button>
                        <div>
                            <h1 className="text-2xl font-bold text-[#0B3D91]">
                                Edit Transaction
                            </h1>
                            <p className="mt-1 text-sm text-[#5C7A9E]">
                                Update the ledger transaction details below.
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3">
                        <Badge
                            variant="outline"
                            className="border-[#B9D8FF] bg-[#EAF2FF] text-[#0B62E0]"
                        >
                            {selectedEntryLabel}
                        </Badge>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setShowDeleteDialog(true)}
                            disabled={deleteProcessing}
                            className="border-red-200 text-red-600 hover:bg-red-50 disabled:opacity-60"
                        >
                            <Trash2 className="mr-1 h-4 w-4" /> Delete
                        </Button>
                    </div>
                </div>

                {fullName && (
                    <Card className="border-[#CFE3FF] bg-white">
                        <CardContent className="pt-6">
                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p className="text-xs font-medium tracking-wide text-[#7FA6D6] uppercase">
                                        Student
                                    </p>
                                    <p className="mt-1 text-lg font-semibold text-[#0B3D91]">
                                        {fullName}
                                    </p>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    {selectedCourse && (
                                        <Badge
                                            variant="outline"
                                            className="w-fit border-[#B9D8FF] bg-[#EAF2FF] text-[#0B62E0]"
                                        >
                                            {selectedCourse.code}
                                        </Badge>
                                    )}
                                    <Badge
                                        variant="outline"
                                        className="w-fit border-amber-200 bg-amber-50 text-amber-700"
                                    >
                                        {formatCurrency(data.amount)}
                                    </Badge>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <Card className="border-[#CFE3FF] bg-white">
                    <CardHeader>
                        <CardTitle className="text-base text-[#0B3D91]">
                            Transaction Details
                        </CardTitle>
                        <CardDescription className="text-[#7FA6D6]">
                            Basic student details, academic information, and
                            financial details
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={handleSubmit}
                            className="grid grid-cols-1 gap-4 md:grid-cols-2"
                        >
                            <div className="md:col-span-2">
                                <label
                                    htmlFor="student_id"
                                    className="text-sm text-[#334E68]"
                                >
                                    Student
                                </label>
                                <SearchableStudentSelect
                                    inputId="student_id"
                                    students={students}
                                    value={data.student_id}
                                    onChange={(id) => {
                                        const selected = students.find(
                                            (student) =>
                                                String(student.id) ===
                                                String(id),
                                        );

                                        setData((currentData) => ({
                                            ...currentData,
                                            student_id: String(id),
                                            course_id: selected?.last_course_id
                                                ? String(
                                                      selected.last_course_id,
                                                  )
                                                : currentData.course_id,
                                        }));
                                    }}
                                    onClear={() => {
                                        setData((currentData) => ({
                                            ...currentData,
                                            student_id: '',
                                            course_id: '',
                                        }));
                                    }}
                                />
                                <FieldError message={errors.student_id} />
                            </div>

                            <div>
                                <label
                                    htmlFor="student_number"
                                    className="text-sm text-[#334E68]"
                                >
                                    Student ID
                                </label>
                                <Input
                                    id="student_number"
                                    value={
                                        selectedStudent?.student_number ?? ''
                                    }
                                    readOnly
                                    className={
                                        selectedStudent?.student_number
                                            ? 'bg-[#F3F8FF]'
                                            : 'bg-gray-50'
                                    }
                                />
                            </div>

                            <div>
                                <label
                                    htmlFor="course_id"
                                    className="text-sm text-[#334E68]"
                                >
                                    Course
                                </label>
                                <select
                                    id="course_id"
                                    value={data.course_id}
                                    onChange={(event) =>
                                        setData('course_id', event.target.value)
                                    }
                                    className="w-full rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm text-[#334E68] focus:ring-2 focus:ring-[#0F6FFF] focus:outline-none"
                                >
                                    <option value="">
                                        -- Select Course --
                                    </option>
                                    {courses.map((course) => (
                                        <option
                                            key={course.id}
                                            value={String(course.id)}
                                        >
                                            {course.code}
                                        </option>
                                    ))}
                                </select>
                                <FieldError message={errors.course_id} />
                            </div>

                            <div>
                                <label
                                    htmlFor="school_year"
                                    className="text-sm text-[#334E68]"
                                >
                                    School Year
                                </label>
                                <select
                                    id="school_year"
                                    value={data.school_year}
                                    onChange={(event) =>
                                        syncTerm(
                                            event.target.value,
                                            data.semester,
                                        )
                                    }
                                    className="w-full rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm text-[#334E68] focus:ring-2 focus:ring-[#0F6FFF] focus:outline-none"
                                >
                                    <option value="">
                                        -- Select School Year --
                                    </option>
                                    {schoolYearOptions.map((year) => (
                                        <option key={year} value={year}>
                                            {year}
                                        </option>
                                    ))}
                                </select>
                                <FieldError message={errors.school_year} />
                            </div>

                            <div>
                                <label
                                    htmlFor="semester"
                                    className="text-sm text-[#334E68]"
                                >
                                    Semester/Summer
                                </label>
                                <select
                                    id="semester"
                                    value={data.semester}
                                    onChange={(event) =>
                                        syncTerm(
                                            data.school_year,
                                            event.target.value,
                                        )
                                    }
                                    className="w-full rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm text-[#334E68] focus:ring-2 focus:ring-[#0F6FFF] focus:outline-none"
                                >
                                    <option value="">
                                        -- Select Semester --
                                    </option>
                                    {semesterOptions.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                                <FieldError message={errors.semester} />
                            </div>

                            <div>
                                <label
                                    htmlFor="entry_type"
                                    className="text-sm text-[#334E68]"
                                >
                                    Type
                                </label>
                                <select
                                    id="entry_type"
                                    value={data.entry_type}
                                    onChange={(event) =>
                                        setData(
                                            'entry_type',
                                            event.target
                                                .value as LawLedgerEntryType,
                                        )
                                    }
                                    className="w-full rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm text-[#334E68] focus:ring-2 focus:ring-[#0F6FFF] focus:outline-none"
                                >
                                    {entryTypeOptions.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                                <FieldError message={errors.entry_type} />
                            </div>

                            <div>
                                <label
                                    htmlFor="status"
                                    className="text-sm text-[#334E68]"
                                >
                                    Status
                                </label>
                                <select
                                    id="status"
                                    value={data.status}
                                    onChange={(event) =>
                                        setData('status', event.target.value)
                                    }
                                    className="w-full rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm text-[#334E68] focus:ring-2 focus:ring-[#0F6FFF] focus:outline-none"
                                >
                                    {statusOptions.map((status) => (
                                        <option key={status} value={status}>
                                            {status}
                                        </option>
                                    ))}
                                </select>
                                <FieldError message={errors.status} />
                            </div>

                            <div>
                                <label
                                    htmlFor="units"
                                    className="text-sm text-[#334E68]"
                                >
                                    Units
                                </label>
                                <Input
                                    id="units"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={data.units}
                                    onChange={(event) =>
                                        setData('units', event.target.value)
                                    }
                                    className={
                                        errors.units ? 'border-red-400' : ''
                                    }
                                />
                                <FieldError message={errors.units} />
                            </div>

                            <div>
                                <div className="flex items-center justify-between">
                                    <label
                                        htmlFor="transaction_date"
                                        className="text-sm text-[#334E68]"
                                    >
                                        Transaction Date
                                    </label>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setData(
                                                'transaction_date',
                                                new Date()
                                                    .toISOString()
                                                    .slice(0, 10),
                                            )
                                        }
                                        className="text-xs font-medium text-[#0F6FFF] hover:underline"
                                    >
                                        Today
                                    </button>
                                </div>
                                <Input
                                    id="transaction_date"
                                    type="date"
                                    value={data.transaction_date}
                                    onChange={(event) =>
                                        setData(
                                            'transaction_date',
                                            event.target.value,
                                        )
                                    }
                                    className={
                                        errors.transaction_date
                                            ? 'border-red-400'
                                            : ''
                                    }
                                />
                                <FieldError message={errors.transaction_date} />
                            </div>

                            <div>
                                <label
                                    htmlFor="reference_jev_or_number"
                                    className="text-sm text-[#334E68]"
                                >
                                    Reference JEV/O.R. Number
                                </label>
                                <Input
                                    id="reference_jev_or_number"
                                    value={data.reference_jev_or_number}
                                    placeholder="e.g., JEV-2024-001"
                                    onChange={(event) =>
                                        setData(
                                            'reference_jev_or_number',
                                            event.target.value,
                                        )
                                    }
                                />
                                <FieldError
                                    message={errors.reference_jev_or_number}
                                />
                            </div>

                            <div>
                                <label
                                    htmlFor="tuition_per_unit_or_fee_per_semester"
                                    className="text-sm text-[#334E68]"
                                >
                                    Tuition per Unit / Reg. & Misc. Fee per
                                    Semester
                                </label>
                                <Input
                                    id="tuition_per_unit_or_fee_per_semester"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={
                                        data.tuition_per_unit_or_fee_per_semester
                                    }
                                    onChange={(event) =>
                                        setData(
                                            'tuition_per_unit_or_fee_per_semester',
                                            event.target.value,
                                        )
                                    }
                                    className={
                                        errors.tuition_per_unit_or_fee_per_semester
                                            ? 'border-red-400'
                                            : ''
                                    }
                                />
                                <FieldError
                                    message={
                                        errors.tuition_per_unit_or_fee_per_semester
                                    }
                                />
                            </div>

                            <div>
                                <div className="flex items-center justify-between">
                                    <label
                                        htmlFor="amount"
                                        className="text-sm text-[#334E68]"
                                    >
                                        Amount
                                    </label>
                                    {data.entry_type === 'ar' && (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setData(
                                                    'amount',
                                                    computedAmount.toFixed(2),
                                                )
                                            }
                                            className="inline-flex items-center gap-1 text-xs font-medium text-[#0F6FFF] hover:underline"
                                        >
                                            <Calculator className="h-3 w-3" />
                                            Recalculate
                                        </button>
                                    )}
                                </div>
                                <Input
                                    id="amount"
                                    type="number"
                                    step="0.01"
                                    min={
                                        data.entry_type === 'ar' ? '0' : '0.01'
                                    }
                                    value={data.amount}
                                    onChange={(event) =>
                                        setData('amount', event.target.value)
                                    }
                                    className={
                                        errors.amount ? 'border-red-400' : ''
                                    }
                                />
                                {data.entry_type === 'ar' && (
                                    <p className="mt-1 text-xs text-slate-500">
                                        Computed AR amount:{' '}
                                        {formatCurrency(computedAmount)}. Leave
                                        Amount blank before saving to let the
                                        server compute it, or click Recalculate
                                        to replace it now.
                                    </p>
                                )}
                                <FieldError message={errors.amount} />
                            </div>

                            <div className="md:col-span-2">
                                <label
                                    htmlFor="particulars"
                                    className="text-sm text-[#334E68]"
                                >
                                    Particulars
                                </label>
                                <Input
                                    id="particulars"
                                    value={data.particulars}
                                    list="particulars-list"
                                    placeholder="e.g., Tuition, Registration, Payment"
                                    onChange={(event) =>
                                        setData(
                                            'particulars',
                                            event.target.value,
                                        )
                                    }
                                    className={
                                        errors.particulars
                                            ? 'border-red-400'
                                            : ''
                                    }
                                />
                                <datalist id="particulars-list">
                                    {particularsOptions.map((particular) => (
                                        <option
                                            key={particular}
                                            value={particular}
                                        />
                                    ))}
                                </datalist>
                                <FieldError message={errors.particulars} />
                            </div>

                            <div>
                                <label
                                    htmlFor="input_by"
                                    className="text-sm text-[#334E68]"
                                >
                                    Input By
                                </label>
                                <Input
                                    id="input_by"
                                    value={data.input_by}
                                    list="users-list-edit"
                                    placeholder="Encoder ID / Initials"
                                    onChange={(event) =>
                                        setData('input_by', event.target.value)
                                    }
                                    className={
                                        errors.input_by ? 'border-red-400' : ''
                                    }
                                />
                                {users.length > 0 && (
                                    <datalist id="users-list-edit">
                                        {users.map((user) => (
                                            <option
                                                key={user.id}
                                                value={user.name}
                                            />
                                        ))}
                                    </datalist>
                                )}
                                <FieldError message={errors.input_by} />
                            </div>

                            <div className="md:col-span-2">
                                <label
                                    htmlFor="remarks"
                                    className="text-sm text-[#334E68]"
                                >
                                    Remarks
                                </label>
                                <textarea
                                    id="remarks"
                                    value={data.remarks}
                                    onChange={(event) =>
                                        setData('remarks', event.target.value)
                                    }
                                    placeholder="Additional notes or comments"
                                    className="min-h-[80px] w-full rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm text-[#334E68] focus:ring-2 focus:ring-[#0F6FFF] focus:outline-none"
                                />
                                <FieldError message={errors.remarks} />
                            </div>

                            <div className="flex items-center justify-end gap-3 md:col-span-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={handleNavigateBack}
                                    className="border-[#CFE3FF] text-[#0B3D91] hover:bg-[#F3F8FF]"
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="bg-[#0F6FFF] text-white hover:bg-[#0B5DDB] disabled:opacity-60"
                                >
                                    {processing ? (
                                        <span className="flex items-center gap-2">
                                            <Spinner className="h-4 w-4" />
                                            Saving...
                                        </span>
                                    ) : (
                                        'Update Transaction'
                                    )}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <AlertDialog
                    open={showDeleteDialog}
                    onOpenChange={setShowDeleteDialog}
                >
                    <AlertDialogContent size="sm">
                        <AlertDialogHeader>
                            <AlertDialogTitle>
                                Delete Transaction
                            </AlertDialogTitle>
                            <AlertDialogDescription>
                                You are about to delete this{' '}
                                {selectedEntryLabel} transaction dated{' '}
                                {data.transaction_date || 'No date'} for{' '}
                                {formatCurrency(data.amount)}
                                {data.reference_jev_or_number
                                    ? `, reference ${data.reference_jev_or_number}`
                                    : ''}{' '}
                                under &quot;{fullName}&quot;. This affects the
                                student&apos;s ledger balance and cannot be
                                undone.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel disabled={deleteProcessing}>
                                Cancel
                            </AlertDialogCancel>
                            <AlertDialogAction
                                onClick={confirmDelete}
                                disabled={deleteProcessing}
                                className="bg-red-600 text-white hover:bg-red-700 disabled:opacity-60"
                            >
                                {deleteProcessing ? 'Deleting...' : 'Delete'}
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </div>
        </div>
    );
}
