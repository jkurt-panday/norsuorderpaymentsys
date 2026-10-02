import { Head, useForm, router } from '@inertiajs/react';
import { ArrowLeft, Plus, X } from 'lucide-react';
import React, { useState } from 'react';
import SearchableStudentSelect from './components/SearchableStudentSelect';
import type { StudentOption } from './components/SearchableStudentSelect';
import {
    entryTypeOptions,
    particularsOptions,
    semesterOptions,
} from './constants';
import {
    index as lawLedgerIndex,
    store as storeLawLedger,
} from '@/actions/App/Http/Controllers/LawSchoolLedgerController';
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

// ─── Types ────────────────────────────────────────────────────────────────────

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
    statuses: string[];
    authUserName: string;
    users?: Array<{ id: number | string; name: string }>;
    selectedStudentId?: number | string | null;
    defaultEntryType?: 'ar' | 'payment' | 'adjustment';
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return <p className="mt-1 text-xs text-red-500">{message}</p>;
}

// ─── Main Add Transaction Component ──────────────────────────────────────────

export default function AddTransaction({
    students,
    courses,
    academicTerms,
    statuses,
    authUserName,
    users = [],
    selectedStudentId = null,
    defaultEntryType = 'ar',
}: Props) {
    const [showNewStudent, setShowNewStudent] = useState(false);
    const [availableStudents, setAvailableStudents] =
        useState<StudentOption[]>(students);

    const todayStr = new Date().toISOString().slice(0, 10);
    const currentYear = new Date().getFullYear();
    const defaultSy = `${currentYear}-${currentYear + 1}`;

    const { data, setData, transform, post, processing, errors } = useForm<{
        student_id: string | number;
        new_student: {
            student_number: string;
            last_name: string;
            first_name: string;
            middle_name: string;
        } | null;
        course_id: string | number;
        academic_term_id: string | number;
        school_year: string;
        semester: string;
        entry_type: string;
        units: string;
        transaction_date: string;
        reference_or_jev_number: string;
        particulars: string;
        tuition_per_unit_or_misc: string;
        amount: string;
        status: string;
        remarks: string;
        input_by: string;
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
        particulars: defaultEntryType === 'payment' ? 'Payment' : 'Tuition',
        tuition_per_unit_or_misc: '',
        amount: '',
        status: 'Pending',
        remarks: '',
        input_by: authUserName,
    });

    const parsedUnits = Number(data.units || 0);
    const parsedRate = Number(data.tuition_per_unit_or_misc || 0);
    const computedAmt = parsedUnits * parsedRate;
    const autoAmount = data.entry_type === 'ar' && data.amount === '';
    const displayedAmt = autoAmount ? computedAmt.toFixed(2) : data.amount;

    function syncTerm(sy: string, semester: string) {
        if (academicTerms.length > 0) {
            const match = academicTerms.find(
                (term) => term.school_year === sy && term.semester === semester,
            );
            setData((currentData) => ({
                ...currentData,
                academic_term_id: match?.id ?? '',
                school_year: sy,
                semester,
            }));

            return;
        }

        setData((currentData) => ({
            ...currentData,
            school_year: sy,
            semester,
        }));
    }

    function removeStudent(id: string | number) {
        router.delete(`/law-ledger/students/${id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setAvailableStudents((prev) =>
                    prev.filter((s) => String(s.id) !== String(id)),
                );

                if (String(data.student_id) === String(id)) {
                    setData((prev) => ({
                        ...prev,
                        student_id: '',
                        course_id: '',
                    }));
                }
            },
        });
    }

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const finalAmount = autoAmount ? computedAmt.toFixed(2) : data.amount;
        const finalDate =
            data.transaction_date || new Date().toISOString().slice(0, 10);

        transform((formData) => ({
            ...formData,
            amount: finalAmount,
            transaction_date: finalDate,
            tuition_per_unit_or_fee_per_semester:
                formData.tuition_per_unit_or_misc || '0.00',
        }));

        post(storeLawLedger.url(), {
            preserveScroll: true,
        });
    };

    const selectClass =
        'w-full rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm text-[#334E68] focus:ring-2 focus:ring-[#0F6FFF] focus:outline-none';

    return (
        <div className="mx-auto max-w-5xl space-y-6">
            <Head title="Add Transaction" />
            <div className="flex items-center gap-2 border-b border-[#CFE3FF] pb-4">
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
                    <p className="mt-1 text-sm text-[#5C7A9E]">
                        Create a manual ledger transaction entry for a law
                        school student.
                    </p>
                </div>
            </div>

            <Card className="border-[#CFE3FF] bg-white">
                <CardHeader>
                    <CardTitle className="text-base text-[#0B3D91]">
                        Transaction Details
                    </CardTitle>
                    <CardDescription className="text-[#7FA6D6]">
                        Fill in the student information and financial details
                        below.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form
                        onSubmit={handleSubmit}
                        className="grid grid-cols-1 gap-4 md:grid-cols-2"
                    >
                        {/* ── Student Picker ──────────────────────────────── */}
                        <div className="md:col-span-2">
                            <div className="mb-1 flex items-center justify-between">
                                <label className="text-sm text-[#334E68]">
                                    Student
                                </label>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setShowNewStudent(!showNewStudent);

                                        if (!showNewStudent) {
                                            setData('student_id', '');
                                            setData('new_student', {
                                                student_number: '',
                                                last_name: '',
                                                first_name: '',
                                                middle_name: '',
                                            });
                                        } else {
                                            setData('new_student', null);
                                        }
                                    }}
                                    className="flex items-center gap-1 text-xs text-[#0F6FFF] hover:underline"
                                >
                                    {showNewStudent ? (
                                        <>
                                            <X className="h-3 w-3" /> Cancel new
                                            student
                                        </>
                                    ) : (
                                        <>
                                            <Plus className="h-3 w-3" /> Add new
                                            student
                                        </>
                                    )}
                                </button>
                            </div>

                            {showNewStudent ? (
                                <div className="grid grid-cols-1 gap-2 rounded-md border border-blue-100 bg-blue-50 p-3 sm:grid-cols-2 lg:grid-cols-4">
                                    <div>
                                        <label className="text-xs text-[#334E68]">
                                            Student ID
                                        </label>
                                        <Input
                                            value={
                                                data.new_student
                                                    ?.student_number ?? ''
                                            }
                                            onChange={(e) =>
                                                setData('new_student', {
                                                    ...data.new_student!,
                                                    student_number:
                                                        e.target.value,
                                                })
                                            }
                                            placeholder="e.g. 2026-00123"
                                            className={
                                                (
                                                    errors as Record<
                                                        string,
                                                        string
                                                    >
                                                )['new_student.student_number']
                                                    ? 'border-red-400'
                                                    : ''
                                            }
                                        />
                                        <FieldError
                                            message={
                                                (
                                                    errors as Record<
                                                        string,
                                                        string
                                                    >
                                                )['new_student.student_number']
                                            }
                                        />
                                    </div>
                                    <div>
                                        <label className="text-xs text-[#334E68]">
                                            Last Name *
                                        </label>
                                        <Input
                                            value={
                                                data.new_student?.last_name ??
                                                ''
                                            }
                                            onChange={(e) =>
                                                setData('new_student', {
                                                    ...data.new_student!,
                                                    last_name: e.target.value,
                                                })
                                            }
                                            placeholder="DELA CRUZ"
                                            required
                                        />
                                    </div>
                                    <div>
                                        <label className="text-xs text-[#334E68]">
                                            First Name *
                                        </label>
                                        <Input
                                            value={
                                                data.new_student?.first_name ??
                                                ''
                                            }
                                            onChange={(e) =>
                                                setData('new_student', {
                                                    ...data.new_student!,
                                                    first_name: e.target.value,
                                                })
                                            }
                                            placeholder="JUAN"
                                            required
                                        />
                                    </div>
                                    <div>
                                        <label className="text-xs text-[#334E68]">
                                            Middle Name
                                        </label>
                                        <Input
                                            value={
                                                data.new_student?.middle_name ??
                                                ''
                                            }
                                            onChange={(e) =>
                                                setData('new_student', {
                                                    ...data.new_student!,
                                                    middle_name: e.target.value,
                                                })
                                            }
                                            placeholder="P."
                                        />
                                    </div>
                                </div>
                            ) : (
                                <SearchableStudentSelect
                                    students={availableStudents}
                                    value={data.student_id}
                                    onChange={(id) => {
                                        const selected = availableStudents.find(
                                            (s) => String(s.id) === String(id),
                                        );

                                        if (selected?.last_course_id) {
                                            setData((prev) => ({
                                                ...prev,
                                                student_id: id,
                                                course_id: String(
                                                    selected.last_course_id,
                                                ),
                                            }));
                                        } else {
                                            setData('student_id', id);
                                        }
                                    }}
                                    onClear={() => {
                                        setData((prev) => ({
                                            ...prev,
                                            student_id: '',
                                            course_id: '',
                                        }));
                                    }}
                                    onRemove={removeStudent}
                                />
                            )}
                            <FieldError
                                message={
                                    (errors as any).student_id ||
                                    (errors as any).student_name
                                }
                            />
                        </div>

                        {/* ── Course ──────────────────────────────────────── */}
                        <div>
                            <label className="text-sm text-[#334E68]">
                                Course
                            </label>
                            <select
                                value={String(data.course_id)}
                                onChange={(e) =>
                                    setData('course_id', e.target.value)
                                }
                                className={selectClass}
                            >
                                <option value="">-- Select Course --</option>
                                {courses.map((c) => (
                                    <option key={c.id} value={String(c.id)}>
                                        {c.code}
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

                        {/* ── School Year ─────────────────────────────────── */}
                        <div>
                            <label className="text-sm text-[#334E68]">
                                School Year
                            </label>
                            <Input
                                value={data.school_year}
                                placeholder="e.g. 2025-2026"
                                pattern="\d{4}-\d{4}"
                                title="Format: YYYY-YYYY (e.g. 2025-2026)"
                                onChange={(e) =>
                                    syncTerm(e.target.value, data.semester)
                                }
                                className={
                                    (errors as any).school_year
                                        ? 'border-red-400'
                                        : ''
                                }
                            />
                            <FieldError message={(errors as any).school_year} />
                        </div>

                        {/* ── Semester ────────────────────────────────────── */}
                        <div>
                            <label className="text-sm text-[#334E68]">
                                Semester
                            </label>
                            <p className="mb-1 text-xs text-[#7FA6D6]">
                                Short label for the term.
                            </p>
                            <select
                                value={data.semester}
                                onChange={(e) =>
                                    syncTerm(data.school_year, e.target.value)
                                }
                                className={selectClass}
                            >
                                {semesterOptions.map((opt) => (
                                    <option key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </option>
                                ))}
                            </select>
                        </div>

                        {/* ── Entry Type ──────────────────────────────────── */}
                        <div>
                            <label className="text-sm text-[#334E68]">
                                Type
                            </label>
                            <select
                                value={data.entry_type}
                                onChange={(e) =>
                                    setData('entry_type', e.target.value)
                                }
                                className={selectClass}
                            >
                                {entryTypeOptions.map((opt) => (
                                    <option key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </option>
                                ))}
                            </select>
                        </div>

                        {/* ── Status ─────────────────────────────────────── */}
                        <div>
                            <label className="text-sm text-[#334E68]">
                                Status
                            </label>
                            <select
                                value={data.status}
                                onChange={(e) =>
                                    setData('status', e.target.value)
                                }
                                className={selectClass}
                            >
                                {statuses.map((status) => (
                                    <option key={status} value={status}>
                                        {status}
                                    </option>
                                ))}
                            </select>
                        </div>

                        {/* ── Units ───────────────────────────────────────── */}
                        <div>
                            <label className="text-sm text-[#334E68]">
                                Units
                            </label>
                            <Input
                                type="number"
                                min="0"
                                value={data.units}
                                onChange={(e) =>
                                    setData('units', e.target.value)
                                }
                                className={
                                    (errors as any).units
                                        ? 'border-red-400'
                                        : ''
                                }
                            />
                            <FieldError message={(errors as any).units} />
                        </div>

                        {/* ── Transaction Date ────────────────────────────── */}
                        <div>
                            <div className="flex items-center justify-between">
                                <label className="text-sm text-[#334E68]">
                                    Transaction Date
                                </label>
                                <button
                                    type="button"
                                    onClick={() =>
                                        setData('transaction_date', todayStr)
                                    }
                                    className="text-xs font-medium text-[#0F6FFF] hover:underline"
                                >
                                    Today
                                </button>
                            </div>
                            <Input
                                type="date"
                                value={data.transaction_date}
                                onChange={(e) =>
                                    setData('transaction_date', e.target.value)
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

                        {/* ── Reference / JEV / OR # ──────────────────────── */}
                        <div>
                            <label className="text-sm text-[#334E68]">
                                Reference / JEV / OR #
                            </label>
                            <Input
                                value={data.reference_or_jev_number}
                                onChange={(e) =>
                                    setData(
                                        'reference_or_jev_number',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>

                        {/* ── Particulars ─────────────────────────────────── */}
                        <div className="md:col-span-2">
                            <label className="text-sm text-[#334E68]">
                                Particulars
                            </label>
                            <select
                                value={data.particulars}
                                onChange={(e) =>
                                    setData('particulars', e.target.value)
                                }
                                className={selectClass}
                            >
                                {particularsOptions.map((opt) => (
                                    <option key={opt} value={opt}>
                                        {opt}
                                    </option>
                                ))}
                            </select>
                        </div>

                        {/* ── Tuition / Unit ──────────────────────────────── */}
                        <div>
                            <label className="text-sm text-[#334E68]">
                                Tuition / Unit
                            </label>
                            <Input
                                type="number"
                                step="0.01"
                                min="0"
                                value={data.tuition_per_unit_or_misc}
                                onChange={(e) =>
                                    setData(
                                        'tuition_per_unit_or_misc',
                                        e.target.value,
                                    )
                                }
                                className={
                                    (errors as any).tuition_per_unit_or_misc
                                        ? 'border-red-400'
                                        : ''
                                }
                            />
                            <FieldError
                                message={
                                    (errors as any).tuition_per_unit_or_misc
                                }
                            />
                        </div>

                        {/* ── Amount ──────────────────────────────────────── */}
                        <div>
                            <label className="text-sm text-[#334E68]">
                                Amount
                            </label>
                            <Input
                                type="number"
                                step="0.01"
                                min="0"
                                value={displayedAmt}
                                onChange={(e) =>
                                    setData('amount', e.target.value)
                                }
                                className={
                                    (errors as any).amount
                                        ? 'border-red-400'
                                        : ''
                                }
                            />
                            <p className="mt-1 text-xs text-slate-500">
                                If Type is AR, amount is auto-calculated from
                                units × tuition per unit.
                            </p>
                            <FieldError message={(errors as any).amount} />
                        </div>

                        {/* ── Input By ────────────────────────────────────── */}
                        <div>
                            <label className="text-sm text-[#334E68]">
                                Input By
                            </label>
                            <Input
                                value={data.input_by}
                                list="users-list-add"
                                onChange={(e) =>
                                    setData('input_by', e.target.value)
                                }
                                className={
                                    (errors as any).input_by
                                        ? 'border-red-400'
                                        : ''
                                }
                            />
                            {users.length > 0 && (
                                <datalist id="users-list-add">
                                    {users.map((u) => (
                                        <option key={u.id} value={u.name} />
                                    ))}
                                </datalist>
                            )}
                            <FieldError message={(errors as any).input_by} />
                        </div>

                        {/* ── Remarks ─────────────────────────────────────── */}
                        <div className="md:col-span-2">
                            <label className="text-sm text-[#334E68]">
                                Remarks
                            </label>
                            <Input
                                value={data.remarks}
                                onChange={(e) =>
                                    setData('remarks', e.target.value)
                                }
                            />
                        </div>

                        {/* ── Submit ──────────────────────────────────────── */}
                        <div className="flex justify-end md:col-span-2">
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
                                    'Save Transaction'
                                )}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}
