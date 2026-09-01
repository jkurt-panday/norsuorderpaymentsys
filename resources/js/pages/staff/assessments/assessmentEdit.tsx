import {
    Card,
    CardHeader,
    CardTitle,
    CardContent,
    CardDescription,
} from '@/components/ui/card';
import { Search } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer, X } from 'lucide-react';

interface Course {
    id: number;
    course_code: string;
    course_desc: string;
}

interface AssessmentFormModel {
    id: number;
    reference_number: string;
    email: string;
    contact_num: string;
    first_name: string;
    middle_name?: string;
    last_name: string;
    student_id?: string;
    course_id: string;
    address: string;
    enrolled_under: string;
    sy_last_attended: string;
    semester: string;
    course?: Course;
    created_at?: string;
    updated_at?: string;
}

interface LedgerRecord {
    date: string | null;
    particulars: string | null;
    course: string | null;
    units: number | null;
    amount: number;
    reference: string | null;
}

interface AssessmentEditProps {
    assessment: AssessmentFormModel;
    ledgerRecords: LedgerRecord;
}

export default function AssessmentEdit({
    assessment,
    ledgerRecords,
}: AssessmentEditProps) {
    const fullName = [
        assessment.first_name,
        assessment.middle_name,
        assessment.last_name,
    ]
        .filter(Boolean)
        .join(' ');

    return (
        <>
            <Head
                title={`Edit Assessment - ${assessment.reference_number || assessment.id}`}
            />

            <div className="mx-auto min-h-screen w-full max-w-7xl min-w-0 space-y-4 bg-slate-50 p-3 sm:p-6">
                <Card className="rounded-2xl border border-slate-200/70 bg-white px-2! py-2! shadow-sm">
                    <CardHeader className="flex flex-row items-center justify-between p-3">
                        {/*<div className="flex flex-col justify-between gap-2 sm:flex-row sm:items-center sm:justify-between">*/}
                        <CardTitle className="text-xl font-bold text-slate-900">
                            Assessment: {assessment.reference_number}
                        </CardTitle>
                        <Link
                            href="/staff/assessments"
                            className="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Back
                        </Link>
                        {/*</div>*/}
                    </CardHeader>
                </Card>

                <Card className="rounded-2xl border border-slate-200/70 bg-white px-2! py-2! shadow-sm">
                    <CardContent className="space-y-6 p-4 sm:p-6">
                        {/* Student Details Grid */}
                        <div>
                            <h3 className="mb-3 text-sm font-bold tracking-wider text-slate-500 uppercase">
                                Student Information
                            </h3>
                            <div className="grid grid-cols-1 gap-4 rounded-xl border border-slate-100 bg-slate-50 p-4 sm:grid-cols-2 md:grid-cols-3">
                                <div>
                                    <span className="text-xs font-semibold text-slate-400">
                                        Full Name
                                    </span>
                                    <p className="text-sm font-medium text-slate-900">
                                        {fullName || '—'}
                                    </p>
                                </div>
                                <div>
                                    <span className="text-xs font-semibold text-slate-400">
                                        Email
                                    </span>
                                    <p className="text-sm font-medium text-slate-900">
                                        {assessment.email || '—'}
                                    </p>
                                </div>
                                <div>
                                    <span className="text-xs font-semibold text-slate-400">
                                        Contact Number
                                    </span>
                                    <p className="text-sm font-medium text-slate-900">
                                        {assessment.contact_num
                                            ? assessment.contact_num
                                                  .replace(/\D/g, '')
                                                  .replace(
                                                      /(\d{4})(\d{3})(\d{4})/,
                                                      '$1 $2 $3',
                                                  )
                                            : '—'}
                                    </p>
                                </div>
                                <div className="sm:col-span-2 md:col-span-3">
                                    <span className="text-xs font-semibold text-slate-400">
                                        Address
                                    </span>
                                    <p className="text-sm font-medium text-slate-900">
                                        {assessment.address || '—'}
                                    </p>
                                </div>
                                <div className="sm:col-span-2 md:col-span-3">
                                    <span className="text-xs font-semibold text-slate-400">
                                        Student ID
                                    </span>
                                    <p className="text-sm font-medium text-slate-900">
                                        {assessment.student_id || '—'}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Course & Academic Details Grid */}
                        <div>
                            <h3 className="mb-3 text-sm font-bold tracking-wider text-slate-500 uppercase">
                                Course & Academic Information
                            </h3>
                            <div className="grid grid-cols-1 gap-4 rounded-xl border border-slate-100 bg-slate-50 p-4 sm:grid-cols-2 md:grid-cols-3">
                                <div>
                                    <span className="text-xs font-semibold text-slate-400">
                                        Course Code
                                    </span>
                                    <p className="text-sm font-medium text-slate-900">
                                        {assessment.course?.course_code ||
                                            `ID: ${assessment.course_id}`}
                                    </p>
                                </div>
                                <div>
                                    <span className="text-xs font-semibold text-slate-400">
                                        Course Description
                                    </span>
                                    <p className="text-sm font-medium text-slate-900">
                                        {assessment.course?.course_desc ||
                                            'No relationship loaded'}
                                    </p>
                                </div>
                                <div>
                                    <span className="text-xs font-semibold text-slate-400">
                                        Enrolled Under
                                    </span>
                                    <p className="text-sm font-medium text-slate-900">
                                        {assessment.enrolled_under || '—'}
                                    </p>
                                </div>
                                <div>
                                    <span className="text-xs font-semibold text-slate-400">
                                        Semester
                                    </span>
                                    <p className="text-sm font-medium text-slate-900">
                                        {assessment.semester || '—'}
                                    </p>
                                </div>
                                <div>
                                    <span className="text-xs font-semibold text-slate-400">
                                        SY Last Attended
                                    </span>
                                    <p className="text-sm font-medium text-slate-900">
                                        {assessment.sy_last_attended || '—'}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Raw Model Dump */}
                        <div>
                            <h3 className="mb-2 text-xs font-bold tracking-wider text-slate-400 uppercase">
                                this here is where mini ledger statement will be
                                put
                            </h3>
                            <pre className="max-h-80 overflow-auto rounded-xl bg-slate-950 p-4 font-mono text-xs text-emerald-400 shadow-inner">
                                {JSON.stringify(assessment, null, 2)}
                            </pre>
                            <pre className="max-h-80 overflow-auto rounded-xl bg-slate-950 p-4 font-mono text-xs text-emerald-400 shadow-inner">
                                {JSON.stringify(ledgerRecords, null, 2)}
                            </pre>
                            <div className="min-h-full bg-[#FAFAF5] p-4 md:p-8">
                                <div className="mx-auto max-w-5xl space-y-6">
                                    {/* Header */}
                                    <div className="flex items-center justify-between border-b border-[#CFE3FF] pb-4">
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="border-[#CFE3FF] text-[#0B3D91]"
                                                >
                                                    <ArrowLeft className="mr-1 h-4 w-4" /> Back
                                                </Button>
                            
                                                <h1 className="text-2xl font-bold text-[#0B3D91]">
                                                    Student Statement Printer
                                                </h1>
                                            </div>
                            
                                            <p className="mt-1 text-sm text-[#5C7A9E]">
                                                Select a graduate student to review their transaction
                                                breakdown and print a formal SOA PDF.
                                            </p>
                                        </div>
                                    </div>
                            
                                    <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                                        {/* Left: Interactive List Search */}
                                        <Card className="border-[#CFE3FF] bg-white md:col-span-1">
                                            <CardHeader className="pb-3">
                                                <CardTitle className="text-sm font-semibold text-[#0B3D91]">
                                                    Search Student
                                                </CardTitle>
                            
                                                <CardDescription className="text-xs text-[#7FA6D6]">
                                                    Click a name to preview records
                                                </CardDescription>
                                            </CardHeader>
                            
                                            <CardContent className="space-y-4">
                                                <div className="relative">
                                                    <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-[#8AA8CC]" />
                            
                                                    <Input
                                                        type="text"
                                                        placeholder="Type to filter list..."
                                                        className="pl-9 pr-8"
                                                    />
                            
                                                    <button
                                                        className="absolute right-2.5 top-2.5 text-[#8AA8CC] hover:text-[#0B3D91]"
                                                    >
                                                        <X className="h-4 w-4" />
                                                    </button>
                                                </div>
                            
                                                {/* Dropdown for Mobile */}
                                                <div className="block md:hidden">
                                                    <select
                                                        className="w-full rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm text-[#334E68] focus:ring-2 focus:ring-[#0F6FFF] focus:outline-none"
                                                    >
                                                        <option value="">
                                                            -- Choose Student --
                                                        </option>
                            
                                                        <option value="">
                                                            Student Name
                                                        </option>
                                                    </select>
                                                </div>
                            
                                                {/* List for Desktop */}
                                                <div className="hidden max-h-95 overflow-y-auto rounded-md border border-[#EAF2FF] md:block">
                                                    <div className="divide-y divide-[#EAF2FF]">
                                                        <button
                                                            type="button"
                                                            className="w-full px-3 py-2 text-left text-xs text-[#334E68] transition-colors hover:bg-[#F3F8FF]"
                                                        >
                                                            Student Name
                                                        </button>
                            
                                                        <button
                                                            type="button"
                                                            className="w-full bg-[#EAF2FF] px-3 py-2 text-left text-xs font-medium text-[#0B3D91] transition-colors hover:bg-[#F3F8FF]"
                                                        >
                                                            Selected Student
                                                        </button>
                                                    </div>
                                                </div>
                                            </CardContent>
                                        </Card>
                            
                                        {/* Right: Detailed SOA preview */}
                                        <div className="space-y-6 md:col-span-2">
                                            {/* Stats Row */}
                                            <div className="grid grid-cols-3 gap-4">
                                                <Card className="border-[#CFE3FF] bg-white p-4">
                                                    <p className="text-xs text-[#5C7A9E]">
                                                        Total Billed Charges (AR)
                                                    </p>
                            
                                                    <h3 className="mt-1 text-base font-bold text-[#0B3D91]">
                                                        ₱ 0.00
                                                    </h3>
                                                </Card>
                            
                                                <Card className="border-[#CFE3FF] bg-white p-4">
                                                    <p className="text-xs text-[#5C7A9E]">
                                                        Total Payments Received
                                                    </p>
                            
                                                    <h3 className="mt-1 text-base font-bold text-emerald-600">
                                                        ₱ 0.00
                                                    </h3>
                                                </Card>
                            
                                                <Card className="border-[#CFE3FF] bg-white p-4">
                                                    <p className="text-xs text-[#5C7A9E]">
                                                        Outstanding Balance
                                                    </p>
                            
                                                    <h3 className="mt-1 text-base font-bold text-[#0B3D91]">
                                                        ₱ 0.00
                                                    </h3>
                                                </Card>
                                            </div>
                            
                                            <Card className="border-[#CFE3FF] bg-white">
                                                <CardHeader className="flex flex-row items-center justify-between pb-3">
                                                    <div>
                                                        <CardTitle className="text-lg font-bold text-[#0B3D91]">
                                                            Student Record
                                                        </CardTitle>
                            
                                                        <CardDescription className="text-xs text-[#7FA6D6]">
                                                            0 transactions on ledger
                                                        </CardDescription>
                                                    </div>
                            
                                                    <Button
                                                        className="bg-[#0F6FFF] text-white hover:bg-[#0B5DDB]"
                                                        size="sm"
                                                    >
                                                        <Printer className="mr-1.5 h-4 w-4" />
                                                        Print Statement
                                                    </Button>
                                                </CardHeader>
                            
                                                <CardContent className="overflow-x-auto">
                                                    <table className="w-full border-collapse text-left text-xs">
                                                        <thead>
                                                            <tr className="border-b border-[#CFE3FF] bg-[#F7FAFE] font-semibold text-[#0B3D91]">
                                                                <th className="px-3 py-3">
                                                                    Date
                                                                </th>
                            
                                                                <th className="px-3 py-3">
                                                                    S.Y. / Term
                                                                </th>
                            
                                                                <th className="px-3 py-3">
                                                                    Ref / OR #
                                                                </th>
                            
                                                                <th className="px-3 py-3">
                                                                    Particulars
                                                                </th>
                            
                                                                <th className="px-3 py-3">
                                                                    Type
                                                                </th>
                            
                                                                <th className="px-3 py-3 text-right">
                                                                    Amount
                                                                </th>
                                                            </tr>
                                                        </thead>
                            
                                                        <tbody>
                                                            <tr>
                                                                <td
                                                                    colSpan={6}
                                                                    className="py-6 text-center text-[#8AA8CC]"
                                                                >
                                                                    No records found for this student.
                                                                </td>
                                                            </tr>
                            
                                                            <tr className="border-b border-[#EAF2FF] hover:bg-[#F3F8FF]">
                                                                <td className="px-3 py-2 text-[#334E68]">
                                                                    2026-01-01
                                                                </td>
                            
                                                                <td className="px-3 py-2 text-[#334E68]">
                                                                    2025-2026 (1st)
                                                                </td>
                            
                                                                <td className="px-3 py-2 text-[#334E68]">
                                                                    -
                                                                </td>
                            
                                                                <td className="px-3 py-2 text-[#334E68]">
                                                                    Tuition
                                                                </td>
                            
                                                                <td className="px-3 py-2">
                                                                    <Badge
                                                                        variant="outline"
                                                                        className="text-[10px]"
                                                                    >
                                                                        AR
                                                                    </Badge>
                                                                </td>
                            
                                                                <td className="px-3 py-2 text-right font-medium text-[#0B3D91]">
                                                                    ₱ 0.00
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </CardContent>
                                            </Card>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
