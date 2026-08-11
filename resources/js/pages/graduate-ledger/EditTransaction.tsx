import { Head, useForm, router } from '@inertiajs/react';
import { ArrowLeft, Trash2, Search, Check, ChevronsUpDown, X } from 'lucide-react';
import React, { useState, useMemo } from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';

// ─── Constants ────────────────────────────────────────────────────────────────

const semesterOptions = [
    { short: '1st Sem.', full: 'First Semester' },
    { short: '2nd Sem.', full: 'Second Semester' },
    { short: 'Summer',   full: 'Summer' },
];

const particularsOptions = ['Registration', 'Tuition', 'Miscellaneous'];

const entryTypeOptions = [
    { value: 'ar',          label: 'AR' },
    { value: 'payment',     label: 'Payment' },
    { value: 'adjustment',  label: 'Adjustment' },
];

// ─── Types ────────────────────────────────────────────────────────────────────

interface StudentOption {
    id: number | string;
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
    semester_short: string;
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
    semester_short?: string;
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
    if (!s.first_name) return String(s.last_name);
    const mi = s.middle_name ? ` ${s.middle_name.charAt(0).toUpperCase()}.` : '';
    return `${s.last_name}, ${s.first_name}${mi}`;
}

function FieldError({ message }: { message?: string }) {
    if (!message) return null;
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

    const selectedStudent = students.find(s => String(s.id) === String(value));

    const filteredStudents = useMemo(() => {
        const query = search.toLowerCase().trim();
        if (!query) return students.slice(0, 80);
        return students
            .filter(s => {
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
                className="w-full flex items-center justify-between rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm text-[#334E68] focus:ring-2 focus:ring-[#0F6FFF] focus:outline-none"
            >
                <span className={selectedStudent ? 'text-[#0B3D91] font-medium' : 'text-[#7FA6D6]'}>
                    {selectedStudent ? formatStudentLabel(selectedStudent) : '-- Select / Search Student --'}
                </span>
                <ChevronsUpDown className="h-4 w-4 text-[#7FA6D6]" />
            </button>

            {isOpen && (
                <div className="absolute z-50 mt-1 w-full rounded-md border border-[#CFE3FF] bg-white shadow-lg p-2 space-y-2">
                    <div className="relative">
                        <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-[#8AA8CC]" />
                        <Input
                            type="text"
                            placeholder="Type student name to filter list..."
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            className="pl-8 h-9 text-xs border-[#CFE3FF]"
                            autoFocus
                        />
                        {search && (
                            <button
                                type="button"
                                onClick={() => setSearch('')}
                                className="absolute right-2.5 top-2.5 text-xs text-[#8AA8CC] hover:text-[#0B3D91]"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        )}
                    </div>

                    <div className="max-h-60 overflow-y-auto divide-y divide-[#EAF2FF] rounded-md border border-[#EAF2FF]">
                        {filteredStudents.length === 0 ? (
                            <p className="p-3 text-center text-xs text-[#8AA8CC]">No students found.</p>
                        ) : (
                            filteredStudents.map(s => {
                                const isSelected = String(s.id) === String(value);
                                return (
                                    <button
                                        key={s.id}
                                        type="button"
                                        onClick={() => {
                                            onChange(s.id);
                                            setIsOpen(false);
                                        }}
                                        className={`w-full flex items-center justify-between px-3 py-2 text-xs text-left transition-colors hover:bg-[#F3F8FF] ${
                                            isSelected ? 'bg-[#EAF2FF] font-semibold text-[#0B3D91]' : 'text-[#334E68]'
                                        }`}
                                    >
                                        <span>{formatStudentLabel(s)}</span>
                                        {isSelected && <Check className="h-3.5 w-3.5 text-[#0F6FFF]" />}
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

    const { data, setData, put, processing, errors } = useForm({
        student_id: record.student_id ?? '',
        student_name: record.student_name ?? '',
        course_id: record.course_id ?? '',
        course: record.course ?? '',
        academic_term_id: record.academic_term_id ?? '',
        school_year: record.school_year ?? '',
        semester_short: record.semester_short ?? '1st Sem.',
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

    const isAR = isNormalized ? data.entry_type === 'ar' : data.ar_payment === 'AR';
    const shouldAutoComputeAmount = isAR && data.amount === '';
    const displayedAmount = shouldAutoComputeAmount
        ? computedAmount.toFixed(2)
        : data.amount;

    function syncTerm(sy: string, sem: string) {
        if (academicTerms.length > 0) {
            const match = academicTerms.find(
                t => t.school_year === sy && t.semester_short === sem,
            );
            setData(prev => ({
                ...prev,
                academic_term_id: match?.id ?? '',
                school_year: sy,
                semester_short: sem,
            }));
        } else {
            setData(prev => ({
                ...prev,
                school_year: sy,
                semester_short: sem,
            }));
        }
    }

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        router.put(`/graduate-ledger/${record.id}`, {
            ...data,
            amount: displayedAmount,
        });
    };

    const handleDelete = () => {
        const studentLabel = isNormalized
            ? (students.find(s => String(s.id) === String(data.student_id))
                ? formatStudentLabel(students.find(s => String(s.id) === String(data.student_id))!)
                : 'this student')
            : data.student_name;

        if (
            !window.confirm(
                `Delete this transaction for "${studentLabel}"? This cannot be undone.`,
            )
        )
            return;
        router.delete(`/graduate-ledger/${record.id}`);
    };

    const selectClass = 'w-full rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm text-[#334E68] focus:ring-2 focus:ring-[#0F6FFF] focus:outline-none';

    return (
        <div className="min-h-screen bg-[#FAFAF5] p-4 md:p-8">
            <Head title="Edit Transaction" />
            <div className="mx-auto max-w-5xl space-y-6">
                <div className="flex items-center justify-between border-b border-[#CFE3FF] pb-4">
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => router.get('/graduate-ledger')}
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
                            Update the student information and financial details below.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={handleSubmit}
                            className="grid grid-cols-1 gap-4 md:grid-cols-2"
                        >
                            {/* Student Picker */}
                            <div className="md:col-span-2">
                                <label className="text-sm text-[#334E68] mb-1 block">
                                    Student
                                </label>
                                {isNormalized ? (
                                    <SearchableStudentSelect
                                        students={students}
                                        value={data.student_id}
                                        onChange={id => setData('student_id', id)}
                                    />
                                ) : (
                                    <Input
                                        value={data.student_name}
                                        onChange={(e) => setData('student_name', e.target.value)}
                                        placeholder="Student Name..."
                                        required
                                    />
                                )}
                                <FieldError message={errors.student_id || errors.student_name} />
                            </div>

                            {/* Course */}
                            <div>
                                <label className="text-sm text-[#334E68]">
                                    Course
                                </label>
                                {isNormalized ? (
                                    <select
                                        value={String(data.course_id)}
                                        onChange={(e) => setData('course_id', e.target.value)}
                                        className={selectClass}
                                    >
                                        <option value="">-- Select Course --</option>
                                        {courses.map(c => (
                                            <option key={c.id} value={String(c.id)}>
                                                {c.code}
                                            </option>
                                        ))}
                                    </select>
                                ) : (
                                    <select
                                        value={data.course}
                                        onChange={(e) => setData('course', e.target.value)}
                                        className={selectClass}
                                    >
                                        <option value="">-- Select Course --</option>
                                        {courses.map(c => (
                                            <option key={c.id} value={c.code}>
                                                {c.code}
                                            </option>
                                        ))}
                                    </select>
                                )}
                                <FieldError message={errors.course_id || errors.course} />
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
                                    onChange={(e) => syncTerm(e.target.value, data.semester_short)}
                                    className={errors.school_year ? 'border-red-400' : ''}
                                />
                                <FieldError message={errors.school_year} />
                            </div>

                            {/* Semester Short */}
                            <div>
                                <label className="text-sm text-[#334E68]">
                                    Semester Short
                                </label>
                                <select
                                    value={data.semester_short}
                                    onChange={(e) => syncTerm(data.school_year, e.target.value)}
                                    className={selectClass}
                                >
                                    {semesterOptions.map((option) => (
                                        <option key={option.short} value={option.short}>
                                            {option.short}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* Semester Full (Read-Only) */}
                            <div>
                                <label className="text-sm text-[#334E68]">
                                    Semester
                                </label>
                                <Input
                                    value={
                                        semesterOptions.find(o => o.short === data.semester_short)?.full ?? 
                                        data.semester
                                    }
                                    readOnly
                                    className="bg-[#F8FAFC]"
                                />
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
                                    onChange={(e) => setData('units', e.target.value)}
                                    className={errors.units ? 'border-red-400' : ''}
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
                                    onChange={(e) => setData('transaction_date', e.target.value)}
                                    className={errors.transaction_date ? 'border-red-400' : ''}
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
                                    onChange={(e) => setData('reference_or_jev_number', e.target.value)}
                                />
                            </div>

                            {/* Particulars */}
                            <div className="md:col-span-2">
                                <label className="text-sm text-[#334E68]">
                                    Particulars
                                </label>
                                <select
                                    value={data.particulars}
                                    onChange={(e) => setData('particulars', e.target.value)}
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
                                    onChange={(e) => setData('tuition_per_unit_or_misc', e.target.value)}
                                    className={errors.tuition_per_unit_or_misc ? 'border-red-400' : ''}
                                />
                                <FieldError message={errors.tuition_per_unit_or_misc} />
                            </div>

                            {/* Type */}
                            <div>
                                <label className="text-sm text-[#334E68]">
                                    Type
                                </label>
                                {isNormalized ? (
                                    <select
                                        value={data.entry_type}
                                        onChange={(e) => setData('entry_type', e.target.value)}
                                        className={selectClass}
                                    >
                                        {entryTypeOptions.map((option) => (
                                            <option key={option.value} value={option.value}>
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                ) : (
                                    <select
                                        value={data.ar_payment}
                                        onChange={(e) => setData('ar_payment', e.target.value)}
                                        className={selectClass}
                                    >
                                        <option value="AR">AR</option>
                                        <option value="Payment">Payment</option>
                                        <option value="Adjustment">Adjustment</option>
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
                                    onChange={(e) => setData('amount', e.target.value)}
                                    className={errors.amount ? 'border-red-400' : ''}
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
                                    onChange={(e) => setData('input_by', e.target.value)}
                                />
                            </div>

                            {/* Remarks */}
                            <div className="md:col-span-2">
                                <label className="text-sm text-[#334E68]">
                                    Remarks
                                </label>
                                <Input
                                    value={data.remarks}
                                    onChange={(e) => setData('remarks', e.target.value)}
                                />
                            </div>

                            {/* Submit Button */}
                            <div className="flex justify-end md:col-span-2">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="bg-[#0F6FFF] text-white hover:bg-[#0B5DDB] disabled:opacity-60"
                                >
                                    {processing ? 'Saving...' : 'Update Transaction'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
