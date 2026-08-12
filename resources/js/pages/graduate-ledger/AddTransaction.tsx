import { Head, useForm, router } from '@inertiajs/react';
import { ArrowLeft, Plus, X, Search, Check, ChevronsUpDown } from 'lucide-react';
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

interface Props {
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

// ─── Main Add Transaction Component ──────────────────────────────────────────

export default function AddTransaction({ students, courses, academicTerms, authUserName }: Props) {
    const [showNewStudent, setShowNewStudent] = useState(false);

    const { data, setData, post, processing, errors } = useForm<{
        student_id:               string | number;
        new_student:              { last_name: string; first_name: string; middle_name: string } | null;
        course_id:                string | number;
        academic_term_id:         string | number;
        school_year:              string;
        semester_short:           string;
        entry_type:               string;
        units:                    string;
        transaction_date:         string;
        reference_or_jev_number:  string;
        particulars:              string;
        tuition_per_unit_or_misc: string;
        amount:                   string;
        remarks:                  string;
        input_by:                 string;
    }>({
        student_id:               '',
        new_student:              null,
        course_id:                '',
        academic_term_id:         '',
        school_year:              '',
        semester_short:           '1st Sem.',
        entry_type:               'ar',
        units:                    '',
        transaction_date:         '',
        reference_or_jev_number:  '',
        particulars:              'Tuition',
        tuition_per_unit_or_misc: '',
        amount:                   '',
        remarks:                  '',
        input_by:                 authUserName,
    });

    const parsedUnits  = Number(data.units || 0);
    const parsedRate   = Number(data.tuition_per_unit_or_misc || 0);
    const computedAmt  = parsedUnits * parsedRate;
    const autoAmount   = data.entry_type === 'ar' && data.amount === '';
    const displayedAmt = autoAmount ? computedAmt.toFixed(2) : data.amount;

    function syncTerm(sy: string, sem: string) {
        if (academicTerms.length > 0) {
            const match = academicTerms.find(
                t => t.school_year === sy && t.semester_short === sem,
            );
            setData('academic_term_id', match?.id ?? '');
        }
        setData('school_year', sy);
        setData('semester_short', sem);
    }

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/graduate-ledger', {
            ...data,
            amount: displayedAmt,
            transaction_date: data.transaction_date || new Date().toISOString().slice(0, 10),
        });
    };

    const selectClass = 'w-full rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm text-[#334E68] focus:ring-2 focus:ring-[#0F6FFF] focus:outline-none';

    return (
        <div className="min-h-full bg-[#FAFAF5] p-4 md:p-8">
            <Head title="Add Transaction" />
            <div className="mx-auto max-w-5xl space-y-6">
                <div className="flex items-center gap-2 border-b border-[#CFE3FF] pb-4">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => router.get('/graduate-ledger')}
                        className="border-[#CFE3FF] text-[#0B3D91]"
                    >
                        <ArrowLeft className="mr-1 h-4 w-4" /> Back
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold text-[#0B3D91]">Add New Transaction</h1>
                        <p className="mt-1 text-sm text-[#5C7A9E]">
                            Create a manual ledger transaction entry for a student.
                        </p>
                    </div>
                </div>

                <Card className="border-[#CFE3FF] bg-white">
                    <CardHeader>
                        <CardTitle className="text-base text-[#0B3D91]">Transaction Details</CardTitle>
                        <CardDescription className="text-[#7FA6D6]">
                            Fill in the student information and financial details below.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="grid grid-cols-1 gap-4 md:grid-cols-2">

                            {/* ── Student Picker ──────────────────────────────── */}
                            <div className="md:col-span-2">
                                <div className="mb-1 flex items-center justify-between">
                                    <label className="text-sm text-[#334E68]">Student</label>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setShowNewStudent(!showNewStudent);
                                            if (!showNewStudent) {
                                                setData('student_id', '');
                                                setData('new_student', { last_name: '', first_name: '', middle_name: '' });
                                            } else {
                                                setData('new_student', null);
                                            }
                                        }}
                                        className="flex items-center gap-1 text-xs text-[#0F6FFF] hover:underline"
                                    >
                                        {showNewStudent
                                            ? <><X className="h-3 w-3" /> Cancel new student</>
                                            : <><Plus className="h-3 w-3" /> Add new student</>
                                        }
                                    </button>
                                </div>

                                {showNewStudent ? (
                                    <div className="grid grid-cols-3 gap-2 rounded-md border border-blue-100 bg-blue-50 p-3">
                                        <div>
                                            <label className="text-xs text-[#334E68]">Last Name *</label>
                                            <Input
                                                value={data.new_student?.last_name ?? ''}
                                                onChange={e => setData('new_student', { ...data.new_student!, last_name: e.target.value })}
                                                placeholder="DELA CRUZ"
                                                required
                                            />
                                        </div>
                                        <div>
                                            <label className="text-xs text-[#334E68]">First Name *</label>
                                            <Input
                                                value={data.new_student?.first_name ?? ''}
                                                onChange={e => setData('new_student', { ...data.new_student!, first_name: e.target.value })}
                                                placeholder="JUAN"
                                                required
                                            />
                                        </div>
                                        <div>
                                            <label className="text-xs text-[#334E68]">Middle Name</label>
                                            <Input
                                                value={data.new_student?.middle_name ?? ''}
                                                onChange={e => setData('new_student', { ...data.new_student!, middle_name: e.target.value })}
                                                placeholder="P."
                                            />
                                        </div>
                                    </div>
                                ) : (
                                    <SearchableStudentSelect
                                        students={students}
                                        value={data.student_id}
                                        onChange={id => setData('student_id', id)}
                                    />
                                )}
                                <FieldError message={(errors as any).student_id || (errors as any).student_name} />
                            </div>

                            {/* ── Course ──────────────────────────────────────── */}
                            <div>
                                <label className="text-sm text-[#334E68]">Course</label>
                                <select
                                    value={String(data.course_id)}
                                    onChange={e => setData('course_id', e.target.value)}
                                    className={selectClass}
                                >
                                    <option value="">-- Select Course --</option>
                                    {courses.map(c => (
                                        <option key={c.id} value={String(c.id)}>{c.code}</option>
                                    ))}
                                </select>
                                <FieldError message={(errors as any).course_id || (errors as any).course} />
                            </div>

                            {/* ── School Year ─────────────────────────────────── */}
                            <div>
                                <label className="text-sm text-[#334E68]">School Year</label>
                                <Input
                                    value={data.school_year}
                                    placeholder="e.g. 2025-2026"
                                    pattern="\d{4}-\d{4}"
                                    title="Format: YYYY-YYYY (e.g. 2025-2026)"
                                    onChange={e => syncTerm(e.target.value, data.semester_short)}
                                    className={(errors as any).school_year ? 'border-red-400' : ''}
                                />
                                <FieldError message={(errors as any).school_year} />
                            </div>

                            {/* ── Semester ────────────────────────────────────── */}
                            <div>
                                <label className="text-sm text-[#334E68]">Semester</label>
                                <p className="mb-1 text-xs text-[#7FA6D6]">Short label for the term.</p>
                                <select
                                    value={data.semester_short}
                                    onChange={e => syncTerm(data.school_year, e.target.value)}
                                    className={selectClass}
                                >
                                    {semesterOptions.map(opt => (
                                        <option key={opt.short} value={opt.short}>{opt.short}</option>
                                    ))}
                                </select>
                            </div>

                            {/* ── Entry Type ──────────────────────────────────── */}
                            <div>
                                <label className="text-sm text-[#334E68]">Type</label>
                                <select
                                    value={data.entry_type}
                                    onChange={e => setData('entry_type', e.target.value)}
                                    className={selectClass}
                                >
                                    {entryTypeOptions.map(opt => (
                                        <option key={opt.value} value={opt.value}>{opt.label}</option>
                                    ))}
                                </select>
                            </div>

                            {/* ── Units ───────────────────────────────────────── */}
                            <div>
                                <label className="text-sm text-[#334E68]">Units</label>
                                <Input
                                    type="number"
                                    min="0"
                                    value={data.units}
                                    onChange={e => setData('units', e.target.value)}
                                    className={(errors as any).units ? 'border-red-400' : ''}
                                />
                                <FieldError message={(errors as any).units} />
                            </div>

                            {/* ── Transaction Date ────────────────────────────── */}
                            <div>
                                <label className="text-sm text-[#334E68]">Transaction Date</label>
                                <Input
                                    type="date"
                                    value={data.transaction_date}
                                    onChange={e => setData('transaction_date', e.target.value)}
                                    className={(errors as any).transaction_date ? 'border-red-400' : ''}
                                />
                                <FieldError message={(errors as any).transaction_date} />
                            </div>

                            {/* ── Reference / JEV / OR # ──────────────────────── */}
                            <div>
                                <label className="text-sm text-[#334E68]">Reference / JEV / OR #</label>
                                <Input
                                    value={data.reference_or_jev_number}
                                    onChange={e => setData('reference_or_jev_number', e.target.value)}
                                />
                            </div>

                            {/* ── Particulars ─────────────────────────────────── */}
                            <div className="md:col-span-2">
                                <label className="text-sm text-[#334E68]">Particulars</label>
                                <select
                                    value={data.particulars}
                                    onChange={e => setData('particulars', e.target.value)}
                                    className={selectClass}
                                >
                                    {particularsOptions.map(opt => (
                                        <option key={opt} value={opt}>{opt}</option>
                                    ))}
                                </select>
                            </div>

                            {/* ── Tuition / Unit ──────────────────────────────── */}
                            <div>
                                <label className="text-sm text-[#334E68]">Tuition / Unit</label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={data.tuition_per_unit_or_misc}
                                    onChange={e => setData('tuition_per_unit_or_misc', e.target.value)}
                                    className={(errors as any).tuition_per_unit_or_misc ? 'border-red-400' : ''}
                                />
                                <FieldError message={(errors as any).tuition_per_unit_or_misc} />
                            </div>

                            {/* ── Amount ──────────────────────────────────────── */}
                            <div>
                                <label className="text-sm text-[#334E68]">Amount</label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={displayedAmt}
                                    onChange={e => setData('amount', e.target.value)}
                                    className={(errors as any).amount ? 'border-red-400' : ''}
                                />
                                <p className="mt-1 text-xs text-slate-500">
                                    If Type is AR, amount is auto-calculated from units × tuition per unit.
                                </p>
                                <FieldError message={(errors as any).amount} />
                            </div>

                            {/* ── Input By ────────────────────────────────────── */}
                            <div>
                                <label className="text-sm text-[#334E68]">Input By</label>
                                <Input
                                    value={data.input_by}
                                    onChange={e => setData('input_by', e.target.value)}
                                />
                            </div>

                            {/* ── Remarks ─────────────────────────────────────── */}
                            <div className="md:col-span-2">
                                <label className="text-sm text-[#334E68]">Remarks</label>
                                <Input
                                    value={data.remarks}
                                    onChange={e => setData('remarks', e.target.value)}
                                />
                            </div>

                            {/* ── Submit ──────────────────────────────────────── */}
                            <div className="flex justify-end md:col-span-2">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="bg-[#0F6FFF] text-white hover:bg-[#0B5DDB] disabled:opacity-60"
                                >
                                    {processing ? 'Saving...' : 'Save Transaction'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
