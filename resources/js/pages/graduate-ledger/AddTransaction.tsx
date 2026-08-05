import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { router } from '@inertiajs/react';

const courseOptions = [
    'PhD Educational Management',
    'PhD Mathematics Education',
    'EdD Educational Management',
    'EdD Instruction',
    'EdD Science Education',
    'EdD Filipino',
    'EdD Technology Management',
    'DM HRM',
    'DM Public Administration',
    'MBA',
    'MPH',
    'MA Education',
    'MA English',
    'MA Filipino',
    'MA History',
    'MA Psychology',
    'MA Mathematics',
    'MAECE',
    'MS Agriculture',
    'MSIT',
    'MTE',
    'MPM HRM',
    'MPM LGA',
];

const semesterOptions = ['1st Sem.', '2nd Sem.', 'Summer'];
const particularsOptions = ['Registration', 'Tuition', 'Miscellaneous'];
const typeOptions = ['AR', 'Payment', 'Adjustment'];

interface Props {
    studentNames: string[];
    authUserName: string;
}

function FieldError({ message }: { message?: string }) {
    if (!message) return null;
    return <p className="mt-1 text-xs text-red-500">{message}</p>;
}

export default function AddTransaction({ studentNames, authUserName }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        student_name: '',
        course: '',
        school_year: '',
        semester_short: '1st Sem.',
        semester: 'First Semester',
        units: '',
        transaction_date: '',
        reference_or_jev_number: '',
        particulars: 'Tuition',
        tuition_per_unit_or_misc: '',
        ar_payment: 'AR',
        amount: '',
        remarks: '',
        input_by: authUserName,
    });

    const parsedUnits = Number(data.units || 0);
    const parsedRate = Number(data.tuition_per_unit_or_misc || 0);
    const computedAmount = parsedUnits * parsedRate;
    const shouldAutoComputeAmount =
        data.ar_payment === 'AR' && data.amount === '';
    const displayedAmount = shouldAutoComputeAmount
        ? computedAmount.toFixed(2)
        : data.amount;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        router.post('/graduate-ledger', {
            ...data,
            amount: displayedAmount,
            transaction_date:
                data.transaction_date || new Date().toISOString().slice(0, 10),
        });
    };

    return (
        <div className="min-h-screen bg-[#FAFAF5] p-4 md:p-8">
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
                        <h1 className="text-2xl font-bold text-[#0B3D91]">
                            Add New Transaction
                        </h1>
                        <p className="mt-1 text-sm text-[#5C7A9E]">
                            Create a manual ledger transaction entry for a
                            student.
                        </p>
                    </div>
                </div>

                <Card className="border-[#CFE3FF] bg-white">
                    <CardHeader>
                        <CardTitle className="text-base text-[#0B3D91]">
                            Transaction Details
                        </CardTitle>
                        <CardDescription className="text-[#7FA6D6]">
                            Fill in the student information and financial
                            details below.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={handleSubmit}
                            className="grid grid-cols-1 gap-4 md:grid-cols-2"
                        >
                            {/* Student Name with autocomplete datalist */}
                            <div className="md:col-span-2">
                                <label className="text-sm text-[#334E68]">
                                    Student Name
                                </label>
                                <Input
                                    list="student-names-list"
                                    value={data.student_name}
                                    onChange={(e) =>
                                        setData('student_name', e.target.value)
                                    }
                                    placeholder="Type or select a student name..."
                                    required
                                    className={
                                        errors.student_name
                                            ? 'border-red-400'
                                            : ''
                                    }
                                />
                                <datalist id="student-names-list">
                                    {studentNames.map((name) => (
                                        <option key={name} value={name} />
                                    ))}
                                </datalist>
                                <FieldError message={errors.student_name} />
                            </div>

                            <div>
                                <label className="text-sm text-[#334E68]">
                                    Course
                                </label>
                                <select
                                    value={data.course}
                                    onChange={(e) =>
                                        setData('course', e.target.value)
                                    }
                                    className="w-full rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm text-[#334E68] focus:ring-2 focus:ring-[#0F6FFF] focus:outline-none"
                                >
                                    <option value="">
                                        -- Select Course --
                                    </option>
                                    {courseOptions.map((course) => (
                                        <option key={course} value={course}>
                                            {course}
                                        </option>
                                    ))}
                                </select>
                                <FieldError message={errors.course} />
                            </div>

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
                                        setData('school_year', e.target.value)
                                    }
                                    className={
                                        errors.school_year
                                            ? 'border-red-400'
                                            : ''
                                    }
                                />
                                <FieldError message={errors.school_year} />
                            </div>

                            <div>
                                <label className="text-sm text-[#334E68]">
                                    Semester Short
                                </label>
                                <p className="mb-1 text-xs text-[#7FA6D6]">
                                    Short label for the term, such as 1st Sem.
                                    or 2nd Sem.
                                </p>
                                <select
                                    value={data.semester_short}
                                    onChange={(e) => {
                                        const value = e.target.value;
                                        setData('semester_short', value);
                                        setData(
                                            'semester',
                                            value === 'Summer'
                                                ? 'Summer'
                                                : value === '2nd Sem.'
                                                  ? 'Second Semester'
                                                  : 'First Semester',
                                        );
                                    }}
                                    className="w-full rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm text-[#334E68] focus:ring-2 focus:ring-[#0F6FFF] focus:outline-none"
                                >
                                    {semesterOptions.map((option) => (
                                        <option key={option} value={option}>
                                            {option}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="text-sm text-[#334E68]">
                                    Semester
                                </label>
                                <Input
                                    value={data.semester}
                                    readOnly
                                    className="bg-[#F8FAFC]"
                                />
                            </div>

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

                            <div className="md:col-span-2">
                                <label className="text-sm text-[#334E68]">
                                    Particulars
                                </label>
                                <select
                                    value={data.particulars}
                                    onChange={(e) =>
                                        setData('particulars', e.target.value)
                                    }
                                    className="w-full rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm text-[#334E68] focus:ring-2 focus:ring-[#0F6FFF] focus:outline-none"
                                >
                                    {particularsOptions.map((option) => (
                                        <option key={option} value={option}>
                                            {option}
                                        </option>
                                    ))}
                                </select>
                            </div>

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

                            <div>
                                <label className="text-sm text-[#334E68]">
                                    Type
                                </label>
                                <select
                                    value={data.ar_payment}
                                    onChange={(e) =>
                                        setData('ar_payment', e.target.value)
                                    }
                                    className="w-full rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm text-[#334E68] focus:ring-2 focus:ring-[#0F6FFF] focus:outline-none"
                                >
                                    {typeOptions.map((option) => (
                                        <option key={option} value={option}>
                                            {option}
                                        </option>
                                    ))}
                                </select>
                            </div>

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
                                    className={`${errors.amount ? 'border-red-400' : ''}`}
                                />
                                <p className="mt-1 text-xs text-slate-500">
                                    If Type is AR, amount is auto-calculated
                                    from units × tuition per unit. You may still
                                    edit it manually.
                                </p>
                                <FieldError message={errors.amount} />
                            </div>

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

                            <div className="flex justify-end md:col-span-2">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="bg-[#0F6FFF] text-white hover:bg-[#0B5DDB] disabled:opacity-60"
                                >
                                    {processing
                                        ? 'Saving...'
                                        : 'Save Transaction'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
