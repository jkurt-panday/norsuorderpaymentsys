import { Head, useForm, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Trash2,
    Search,
    Check,
    ChevronsUpDown,
    X,
} from 'lucide-react';
import React, { useState, useMemo } from 'react';
import {
    destroy as destroyGraduateLedger,
    index as graduateLedgerIndex,
    update as updateGraduateLedger,
} from '@/actions/App/Http/Controllers/GraduateLedgerController';
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

// ─── Constants ────────────────────────────────────────────────────────────────

const semesterOptions = [
    { label: '1st Sem.', value: 'First Semester' },
    { label: '2nd Sem.', value: 'Second Semester' },
    { label: 'Summer', value: 'Summer' },
];

const particularsOptions = [
    'Registration',
    'Tuition',
    'Miscellaneous',
    'Adjustment',
];

const entryTypeOptions = [
    { value: 'ar', label: 'AR' },
    { value: 'payment', label: 'Payment' },
    { value: 'adjustment', label: 'Adjustment' },
];

// ─── Types ────────────────────────────────────────────────────────────────────

interface StudentOption {
    id: number | string;
    student_number?: string | null;
    last_name: string;
    first_name: string;
    middle_name?: string | null;
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

interface LedgerFormRecord {
    id: number;
    student_id?: number | string;
    student_name?: string;
    course_id?: number | string;
    course?: string;
    academic_term_id?: number | string;
    school_year?: string;
    semester?: string;
    entry_type?: string;
    ar_payment?: string;
    units: number | string | null;
    transaction_date: string | null;
    reference_or_jev_number: string | null;
    particulars: string | null;
    tuition_per_unit_or_misc: number | string | null;
    amount: number | string | null;
    remarks: string | null;
    input_by: string | null;
}

interface Props {
    record: LedgerFormRecord;
    students: StudentOption[];
    courses: CourseOption[];
    academicTerms: AcademicTermOption[];
    authUserName: string;
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function formatStudentLabel(s: StudentOption): string {
    const studentName = !s.first_name
        ? String(s.last_name)
        : `${s.last_name}, ${s.first_name}${s.middle_name ? ` ${s.middle_name.charAt(0).toUpperCase()}.` : ''}`;

    if (s.student_number) {
        return `${s.student_number} — ${studentName}`;
    }

    return studentName;
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

    const selectedStudent = students.find(
        (s) => String(s.id) === String(value),
    );

    const filteredStudents = useMemo(() => {
        const query = search.toLowerCase().trim();

        if (!query) {
            return students.slice(0, 80);
        }

        return students
            .filter((s) => {
                const label = formatStudentLabel(s).toLowerCase();

                return label.includes(query);
            })
            .slice(0, 80);
    }, [students, search]);

    return (
        <div className="relative w-full">
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
                            onChange={(e) => setSearch(e.target.value)}
                            className="h-9 border-[#CFE3FF] pl-8 text-xs"
                            autoFocus
                        />
                        {search && (
                            <button
                                type="button"
                                onClick={() => setSearch('')}
                                className="absolute top-2.5 right-2.5 text-xs text-[#8AA8CC] hover:text-[#0B3D91]"
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
                            filteredStudents.map((s) => {
                                const isSelected =
                                    String(s.id) === String(value);

                                return (
                                    <button
                                        key={s.id}
                                        type="button"
                                        onClick={() => {
                                            onChange(s.id);
                                            setIsOpen(false);
                                        }}
                                        className={`flex w-full items-center justify-between px-3 py-2 text-left text-xs transition-colors hover:bg-[#F3F8FF] ${
                                            isSelected
                                                ? 'bg-[#EAF2FF] font-semibold text-[#0B3D91]'
                                                : 'text-[#334E68]'
                                        }`}
                                    >
                                        <span>{formatStudentLabel(s)}</span>
                                        {isSelected && (
                                            <Check className="h-3.5 w-3.5 text-[#0F6FFF]" />
                                        )}
                                    </button>
                                );
                            })
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

// ─── Main Edit Transaction Component ─────────────────────────────────────────

export default function EditTransaction({
    record,
    students,
    courses,
    academicTerms,
    authUserName,
}: Props) {
    const isNormalized = record.student_id !== undefined;

    const { data, setData, transform, put, processing, errors } = useForm({
        student_id: record.student_id ?? '',
        student_name: record.student_name ?? '',
        course_id: record.course_id ?? '',
        course: record.course ?? '',
        academic_term_id: record.academic_term_id ?? '',
        school_year: record.school_year ?? '',
        semester: record.semester ?? 'First Semester',
        entry_type: record.entry_type ?? 'ar',
        ar_payment: record.ar_payment ?? 'AR',
        units: String(record.units ?? ''),
        transaction_date: record.transaction_date
            ? String(record.transaction_date).split('T')[0]
            : '',
        reference_or_jev_number: record.reference_or_jev_number ?? '',
        particulars: record.particulars ?? 'Tuition',
        tuition_per_unit_or_misc: String(record.tuition_per_unit_or_misc ?? ''),
        amount: String(record.amount ?? ''),
        remarks: record.remarks ?? '',
        input_by: record.input_by ?? authUserName,
    });

    const parsedUnits = Number(data.units || 0);
    const parsedRate = Number(data.tuition_per_unit_or_misc || 0);
    const computedAmount = parsedUnits * parsedRate;

    const isAR = isNormalized
        ? data.entry_type === 'ar'
        : data.ar_payment === 'AR';
    const shouldAutoComputeAmount = isAR && data.amount === '';
    const displayedAmount = shouldAutoComputeAmount
        ? computedAmount.toFixed(2)
        : data.amount;

    function syncTerm(sy: string, semester: string) {
        if (academicTerms.length > 0) {
            const match = academicTerms.find(
                (term) => term.school_year === sy && term.semester === semester,
            );
            setData((prev) => ({
                ...prev,
                academic_term_id: match?.id ?? '',
                school_year: sy,
                semester,
            }));
        } else {
            setData((prev) => ({
                ...prev,
                school_year: sy,
                semester,
            }));
        }
    }

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const finalAmount = shouldAutoComputeAmount
            ? computedAmount.toFixed(2)
            : data.amount;

        transform((formData) => ({
            ...formData,
            amount: finalAmount,
            tuition_per_unit_or_misc:
                formData.tuition_per_unit_or_misc || '0.00',
        }));

        put(updateGraduateLedger.url(record.id), {
            preserveScroll: true,
        });
    };

    const handleDelete = () => {
        const studentLabel = isNormalized
            ? students.find((s) => String(s.id) === String(data.student_id))
                ? formatStudentLabel(
                      students.find(
                          (s) => String(s.id) === String(data.student_id),
                      )!,
                  )
                : 'this student'
            : data.student_name;

        if (
            !window.confirm(
                `Delete this transaction for "${studentLabel}"? This cannot be undone.`,
            )
        ) {
            return;
        }

        router.delete(destroyGraduateLedger.url(record.id));
    };

    const selectClass =
        'w-full rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm text-[#334E68] focus:ring-2 focus:ring-[#0F6FFF] focus:outline-none';

    return (
        <div className="min-h-full bg-[#FAFAF5] p-4 md:p-8">
            <Head title="Edit Transaction" />
            <div className="mx-auto max-w-5xl space-y-6">
                <div className="flex items-center justify-between border-b border-[#CFE3FF] pb-4">
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                router.get(graduateLedgerIndex.url())
                            }
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
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={handleDelete}
                        className="border-red-200 text-red-600 hover:bg-red-50"
                    >
                        <Trash2 className="mr-1 h-4 w-4" /> Delete
                    </Button>
                </div>

                <Card className="border-[#CFE3FF] bg-white">
                    <CardHeader>
                        <CardTitle className="text-base text-[#0B3D91]">
                            Transaction Details
                        </CardTitle>
                        <CardDescription className="text-[#7FA6D6]">
                            Update the student information and financial details
                            below.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={handleSubmit}
                            className="grid grid-cols-1 gap-4 md:grid-cols-2"
                        >
                            {/* Student Picker */}
                            <div className="md:col-span-2">
                                <label className="mb-1 block text-sm text-[#334E68]">
                                    Student
                                </label>
                                {isNormalized ? (
                                    <SearchableStudentSelect
                                        students={students}
                                        value={data.student_id}
                                        onChange={(id) =>
                                            setData('student_id', id)
                                        }
                                    />
                                ) : (
                                    <Input
                                        value={data.student_name}
                                        onChange={(e) =>
                                            setData(
                                                'student_name',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Student Name..."
                                        required
                                    />
                                )}
                                <FieldError
                                    message={
                                        errors.student_id || errors.student_name
                                    }
                                />
                            </div>

                            {/* Course */}
                            <div>
                                <label className="text-sm text-[#334E68]">
                                    Course
                                </label>
                                {isNormalized ? (
                                    <select
                                        value={String(data.course_id)}
                                        onChange={(e) =>
                                            setData('course_id', e.target.value)
                                        }
                                        className={selectClass}
                                    >
                                        <option value="">
                                            -- Select Course --
                                        </option>
                                        {courses.map((c) => (
                                            <option
                                                key={c.id}
                                                value={String(c.id)}
                                            >
                                                {c.code}
                                            </option>
                                        ))}
                                    </select>
                                ) : (
                                    <select
                                        value={data.course}
                                        onChange={(e) =>
                                            setData('course', e.target.value)
                                        }
                                        className={selectClass}
                                    >
                                        <option value="">
                                            -- Select Course --
                                        </option>
                                        {courses.map((c) => (
                                            <option key={c.id} value={c.code}>
                                                {c.code}
                                            </option>
                                        ))}
                                    </select>
                                )}
                                <FieldError
                                    message={errors.course_id || errors.course}
                                />
                            </div>

                            {/* School Year */}
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
                                        errors.school_year
                                            ? 'border-red-400'
                                            : ''
                                    }
                                />
                                <FieldError message={errors.school_year} />
                            </div>

                            {/* Semester */}
                            <div>
                                <label className="text-sm text-[#334E68]">
                                    Semester
                                </label>
                                <select
                                    value={data.semester}
                                    onChange={(e) =>
                                        syncTerm(
                                            data.school_year,
                                            e.target.value,
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

                            {/* Units */}
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
                                        errors.units ? 'border-red-400' : ''
                                    }
                                />
                                <FieldError message={errors.units} />
                            </div>

                            {/* Transaction Date */}
                            <div>
                                <label className="text-sm text-[#334E68]">
                                    Transaction Date
                                </label>
                                <Input
                                    type="date"
                                    value={data.transaction_date}
                                    onChange={(e) =>
                                        setData(
                                            'transaction_date',
                                            e.target.value,
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

                            {/* Reference / JEV / OR # */}
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

                            {/* Particulars */}
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
                                    {particularsOptions.map((option) => (
                                        <option key={option} value={option}>
                                            {option}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* Tuition / Unit */}
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
                                        errors.tuition_per_unit_or_misc
                                            ? 'border-red-400'
                                            : ''
                                    }
                                />
                                <FieldError
                                    message={errors.tuition_per_unit_or_misc}
                                />
                            </div>

                            {/* Type */}
                            <div>
                                <label className="text-sm text-[#334E68]">
                                    Type
                                </label>
                                {isNormalized ? (
                                    <select
                                        value={data.entry_type}
                                        onChange={(e) =>
                                            setData(
                                                'entry_type',
                                                e.target.value,
                                            )
                                        }
                                        className={selectClass}
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
                                ) : (
                                    <select
                                        value={data.ar_payment}
                                        onChange={(e) =>
                                            setData(
                                                'ar_payment',
                                                e.target.value,
                                            )
                                        }
                                        className={selectClass}
                                    >
                                        <option value="AR">AR</option>
                                        <option value="Payment">Payment</option>
                                        <option value="Adjustment">
                                            Adjustment
                                        </option>
                                    </select>
                                )}
                            </div>

                            {/* Amount */}
                            <div>
                                <label className="text-sm text-[#334E68]">
                                    Amount
                                </label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={displayedAmount}
                                    onChange={(e) =>
                                        setData('amount', e.target.value)
                                    }
                                    className={
                                        errors.amount ? 'border-red-400' : ''
                                    }
                                />
                                <FieldError message={errors.amount} />
                            </div>

                            {/* Input By */}
                            <div>
                                <label className="text-sm text-[#334E68]">
                                    Input By
                                </label>
                                <Input
                                    value={data.input_by}
                                    onChange={(e) =>
                                        setData('input_by', e.target.value)
                                    }
                                />
                            </div>

                            {/* Remarks */}
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

                            {/* Submit Button */}
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
                                        'Update Transaction'
                                    )}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
