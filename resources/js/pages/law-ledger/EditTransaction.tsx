import { Head, useForm, router } from '@inertiajs/react';
import { ArrowLeft, Trash2 } from 'lucide-react';
import React, { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';

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
    entry_type: 'ar' | 'payment' | 'adjustment' | null;
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

interface StudentOption {
    id: string | number;
    student_number?: string | null;
    last_name: string;
    first_name: string;
    middle_name?: string | null;
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

interface EditTransactionProps {
    record: LawLedgerRecord;
    students: StudentOption[];
    courses: CourseOption[];
    academicTerms: AcademicTermOption[];
    filterOptions?: {
        schoolYears: string[];
        statuses: string[];
    };
}

function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return <p className="mt-1 text-xs text-red-500">{message}</p>;
}

export default function EditTransaction({
    record,
    students,
    courses,
    academicTerms,
    filterOptions,
}: EditTransactionProps) {
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);

    const { data, setData, processing, errors } = useForm({
        student_id: String(record.student_id ?? ''),
        course_id: String(record.course_id ?? ''),
        academic_term_id: String(record.academic_term_id ?? ''),
        school_year: record.school_year ?? '',
        semester:
            record.semester ?? record.semester_or_summer ?? 'First Semester',
        entry_type: record.entry_type ?? 'ar',
        units: String(record.units ?? ''),
        transaction_date: record.transaction_date
            ? String(record.transaction_date).split('T')[0]
            : '',
        reference_jev_or_number: record.reference_jev_or_number ?? '',
        particulars: record.particulars ?? '',
        tuition_per_unit_or_fee_per_semester: String(
            record.tuition_per_unit_or_fee_per_semester ?? '',
        ),
        amount: String(record.amount ?? ''),
        remarks: record.remarks ?? '',
        input_by: record.input_by ?? '',
    });

    const selectedStudent = students.find(
        (student) => String(student.id) === data.student_id,
    );

    const selectedCourse = courses.find(
        (course) => String(course.id) === data.course_id,
    );

    const semesterOptions = useMemo(
        () => ['First Semester', 'Second Semester', 'Summer'],
        [],
    );

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

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        router.put(`/law-ledger/${record.id}`, data);
    };

    const handleDelete = () => {
        setShowDeleteDialog(true);
    };

    const confirmDelete = () => {
        setShowDeleteDialog(false);
        router.delete(`/law-ledger/${record.id}`);
    };

    const fullName = selectedStudent
        ? `${selectedStudent.last_name}, ${selectedStudent.first_name}${selectedStudent.middle_name ? ` ${selectedStudent.middle_name.charAt(0).toUpperCase()}.` : ''}`
        : [record.last_name, record.first_name, record.middle_initial ?? '']
              .filter(Boolean)
              .join(', ');

    return (
        <div className="min-h-full bg-[#FAFAF5] p-4 md:p-8">
            <Head title="Edit Transaction - Law School Ledger" />
            <div className="mx-auto max-w-5xl space-y-6">
                <div className="flex items-center justify-between border-b border-[#CFE3FF] pb-4">
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => router.get('/law-ledger')}
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
                            {data.entry_type === 'ar'
                                ? 'AR'
                                : data.entry_type === 'payment'
                                  ? 'Payment'
                                  : 'Adjustment'}
                        </Badge>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleDelete}
                            className="border-red-200 text-red-600 hover:bg-red-50"
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
                                {selectedCourse && (
                                    <Badge
                                        variant="outline"
                                        className="w-fit border-[#B9D8FF] bg-[#EAF2FF] text-[#0B62E0]"
                                    >
                                        {selectedCourse.code}
                                    </Badge>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                )}

                <Card className="border-[#CFE3FF] bg-white">
                    <CardHeader>
                        <CardTitle className="text-base text-[#0B3D91]">
                            Student Information
                        </CardTitle>
                        <CardDescription className="text-[#7FA6D6]">
                            Basic student details and academic information
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={handleSubmit}
                            className="grid grid-cols-1 gap-4 md:grid-cols-2"
                        >
                            <div className="md:col-span-2">
                                <label className="text-sm text-[#334E68]">
                                    Student
                                </label>
                                <select
                                    value={data.student_id}
                                    onChange={(e) =>
                                        setData('student_id', e.target.value)
                                    }
                                    className="w-full rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm text-[#334E68] focus:ring-2 focus:ring-[#0F6FFF] focus:outline-none"
                                >
                                    <option value="">
                                        -- Select Student --
                                    </option>
                                    {students.map((student) => (
                                        <option
                                            key={student.id}
                                            value={String(student.id)}
                                        >
                                            {student.student_number
                                                ? `${student.student_number} — `
                                                : ''}
                                            {student.last_name},{' '}
                                            {student.first_name}
                                            {student.middle_name
                                                ? ` ${student.middle_name.charAt(0).toUpperCase()}.`
                                                : ''}
                                        </option>
                                    ))}
                                </select>
                                <FieldError message={errors.student_id} />
                            </div>

                            <div>
                                <label className="text-sm text-[#334E68]">
                                    Student ID
                                </label>
                                <Input
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
                                <label className="text-sm text-[#334E68]">
                                    Course
                                </label>
                                <select
                                    value={data.course_id}
                                    onChange={(e) =>
                                        setData('course_id', e.target.value)
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
                                <label className="text-sm text-[#334E68]">
                                    School Year
                                </label>
                                <select
                                    value={data.school_year}
                                    onChange={(e) =>
                                        syncTerm(e.target.value, data.semester)
                                    }
                                    className="w-full rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm text-[#334E68] focus:ring-2 focus:ring-[#0F6FFF] focus:outline-none"
                                >
                                    <option value="">
                                        -- Select School Year --
                                    </option>
                                    {(filterOptions?.schoolYears ?? []).map(
                                        (year) => (
                                            <option key={year} value={year}>
                                                {year}
                                            </option>
                                        ),
                                    )}
                                </select>
                                <FieldError message={errors.school_year} />
                            </div>

                            <div>
                                <label className="text-sm text-[#334E68]">
                                    Semester/Summer
                                </label>
                                <select
                                    value={data.semester}
                                    onChange={(e) =>
                                        syncTerm(
                                            data.school_year,
                                            e.target.value,
                                        )
                                    }
                                    className="w-full rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm text-[#334E68] focus:ring-2 focus:ring-[#0F6FFF] focus:outline-none"
                                >
                                    <option value="">
                                        -- Select Semester --
                                    </option>
                                    {semesterOptions.map((sem) => (
                                        <option key={sem} value={sem}>
                                            {sem}
                                        </option>
                                    ))}
                                </select>
                                <FieldError message={errors.semester} />
                            </div>

                            <div>
                                <label className="text-sm text-[#334E68]">
                                    Units
                                </label>
                                <Input
                                    type="number"
                                    step="0.01"
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
                                    Reference JEV/O.R. Number
                                </label>
                                <Input
                                    value={data.reference_jev_or_number}
                                    placeholder="e.g., JEV-2024-001"
                                    onChange={(e) =>
                                        setData(
                                            'reference_jev_or_number',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>

                            <div>
                                <label className="text-sm text-[#334E68]">
                                    Tuition per Unit / Reg. & Misc. Fee per
                                    Semester
                                </label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={
                                        data.tuition_per_unit_or_fee_per_semester
                                    }
                                    onChange={(e) =>
                                        setData(
                                            'tuition_per_unit_or_fee_per_semester',
                                            e.target.value,
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
                                <label className="text-sm text-[#334E68]">
                                    Type
                                </label>
                                <select
                                    value={data.entry_type}
                                    onChange={(e) =>
                                        setData(
                                            'entry_type',
                                            e.target.value as
                                                'ar' | 'payment' | 'adjustment',
                                        )
                                    }
                                    className="w-full rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm text-[#334E68] focus:ring-2 focus:ring-[#0F6FFF] focus:outline-none"
                                >
                                    <option value="ar">AR</option>
                                    <option value="payment">Payment</option>
                                    <option value="adjustment">
                                        Adjustment
                                    </option>
                                </select>
                                <FieldError message={errors.entry_type} />
                            </div>

                            <div>
                                <label className="text-sm text-[#334E68]">
                                    Amount
                                </label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    value={data.amount}
                                    onChange={(e) =>
                                        setData('amount', e.target.value)
                                    }
                                    className={
                                        errors.amount ? 'border-red-400' : ''
                                    }
                                />
                                <FieldError message={errors.amount} />
                            </div>

                            <div>
                                <label className="text-sm text-[#334E68]">
                                    Input By
                                </label>
                                <Input
                                    value={data.input_by}
                                    placeholder="Encoder ID / Initials"
                                    onChange={(e) =>
                                        setData('input_by', e.target.value)
                                    }
                                />
                            </div>

                            <div className="md:col-span-2">
                                <label className="text-sm text-[#334E68]">
                                    Remarks
                                </label>
                                <textarea
                                    value={data.remarks}
                                    onChange={(e) =>
                                        setData('remarks', e.target.value)
                                    }
                                    placeholder="Additional notes or comments"
                                    className="min-h-[80px] w-full rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm text-[#334E68] focus:ring-2 focus:ring-[#0F6FFF] focus:outline-none"
                                />
                            </div>

                            <div className="flex items-center justify-end gap-3 md:col-span-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => router.get('/law-ledger')}
                                    className="border-[#CFE3FF] text-[#0B3D91] hover:bg-[#F3F8FF]"
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="bg-[#0F6FFF] text-white hover:bg-[#0B5DDB] disabled:opacity-60"
                                >
                                    {processing
                                        ? 'Saving...'
                                        : 'Update Transaction'}
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
                                Are you sure you want to delete this transaction
                                for &quot;{record.last_name}, {record.first_name}
                                &quot;? This action cannot be undone.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                            <AlertDialogAction
                                onClick={confirmDelete}
                                className="bg-red-600 text-white hover:bg-red-700"
                            >
                                Delete
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </div>
        </div>
    );
}
