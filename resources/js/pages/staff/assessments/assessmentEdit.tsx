import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    CheckCircle2,
    FileQuestion,
    Mail,
    Printer,
    Search,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import {
    edit as editAssessment,
    index as assessmentsIndex,
    emailSoa,
} from '@/actions/App/Http/Controllers/AssessmentController';
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
import { Textarea } from '@/components/ui/textarea';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { flashToast } from '@/utils/flashToast';

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
}

interface StudentCandidate {
    key: string;
    name: string;
    studentId: string | null;
    pdfQueryKey: 'student_id' | 'student';
    pdfQueryValue: string;
}

interface LedgerRecord {
    id: number;
    name: string;
    course: string | null;
    schoolYear: string | null;
    semester: string | null;
    transactionDate: string | null;
    referenceNo: string | null;
    particulars: string | null;
    type: string;
    amount: number;
}

interface LedgerStatement {
    source: 'graduate' | 'law' | 'unsupported';
    matchStatus: 'matched' | 'manual' | 'missing' | 'ambiguous' | 'unsupported';
    selectedStudent: StudentCandidate | null;
    candidates: StudentCandidate[];
    records: LedgerRecord[];
    summary: {
        totalCharges: number;
        totalPayments: number;
        outstandingBalance: number;
    };
    schoolYear: string;
    semester: string;
}

interface AssessmentEditProps {
    assessment: AssessmentFormModel;
    ledgerStatement: LedgerStatement;
    flash?: {
        success?: string;
        error?: string;
        warning?: string;
    };
}

const currencyFormatter = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 2,
});

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    const parsed = new Date(`${value.split('T')[0]}T00:00:00`);

    return Number.isNaN(parsed.getTime())
        ? value
        : parsed.toLocaleDateString('en-PH', {
              year: 'numeric',
              month: 'short',
              day: '2-digit',
          });
}

export default function AssessmentEdit({
    assessment,
    ledgerStatement,
}: AssessmentEditProps) {
    const { auth, flash } = usePage().props as {
        auth?: { user?: { role?: string } };
        flash?: {
            success?: string;
            error?: string;
            warning?: string;
        };
    };
    const [search, setSearch] = useState('');
    const [isEmailPreviewOpen, setIsEmailPreviewOpen] = useState(false);
    const [emailSubject, setEmailSubject] = useState('');
    const [emailRecipientName, setEmailRecipientName] = useState('');
    const [emailNote, setEmailNote] = useState('');
    const [isSendingEmail, setIsSendingEmail] = useState(false);
    
    useEffect(() => {
        flashToast('success', flash?.success);
        flashToast('error', flash?.error);
        flashToast('warning', flash?.warning);
    }, [flash]);
    
    const fullName = [
        assessment.first_name,
        assessment.middle_name,
        assessment.last_name,
    ]
        .filter(Boolean)
        .join(' ');
    const needsSelection = ['missing', 'ambiguous'].includes(
        ledgerStatement.matchStatus,
    );
    const filteredCandidates = useMemo(() => {
        const term = search.trim().toLowerCase();

        if (!term) {
            return ledgerStatement.candidates;
        }

        return ledgerStatement.candidates.filter((candidate) =>
            `${candidate.name} ${candidate.studentId ?? ''}`
                .toLowerCase()
                .includes(term),
        );
    }, [ledgerStatement.candidates, search]);

    const selectStudent = (key: string) => {
        if (!key) {
            return;
        }

        router.get(
            editAssessment.url(assessment.id, {
                query: { ledger_student: key },
            }),
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    const printStatement = () => {
      const url = `/staff/assessments/print_soa/${assessment.id}` +
        (ledgerStatement.selectedStudent
          ? `?ledger_student=${encodeURIComponent(ledgerStatement.selectedStudent.key)}`
          : '');
      window.open(url, '_blank');
    };

    const buildDefaultEmailSubject = () =>
        `Statement of Account - ${assessment.reference_number}`;

    const buildDefaultRecipientName = () =>
        `${assessment.first_name} ${assessment.last_name}`;

    const openEmailPreview = () => {
        setEmailSubject(buildDefaultEmailSubject());
        setEmailRecipientName(buildDefaultRecipientName());
        setEmailNote('');
        setIsEmailPreviewOpen(true);
    };

    const handleConfirmSendEmail = () => {
        setIsSendingEmail(true);
        
        const ledgerStudentKey = ledgerStatement.selectedStudent?.key ?? '';
        
        router.post(
            emailSoa.url(assessment.id),
            {
                subject: emailSubject,
                recipient_name: emailRecipientName,
                note: emailNote,
                ledger_student: ledgerStudentKey,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsEmailPreviewOpen(false);
                },
                onError: () => {
                    // Error handling - toast will show from server
                },
                onFinish: () => {
                    setIsSendingEmail(false);
                },
            },
        );
    };

    return (
        <>
            <Head
                title={`Edit Assessment - ${assessment.reference_number || assessment.id}`}
            />

            <div className="mx-auto min-h-screen w-full max-w-7xl min-w-0 space-y-4 bg-slate-50 p-3 sm:p-6">
                <Card className="rounded-2xl border border-slate-200/70 bg-white px-2! py-2! shadow-sm">
                    <CardHeader className="flex flex-row items-center justify-between gap-4 p-3">
                        <CardTitle className="text-xl font-bold text-slate-900">
                            Assessment: {assessment.reference_number}
                        </CardTitle>
                        <Button
                            variant="outline"
                            render={<Link href={assessmentsIndex.url()} />}
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Back
                        </Button>
                    </CardHeader>
                </Card>

                <Card className="rounded-2xl border border-slate-200/70 bg-white px-2! py-2! shadow-sm">
                    <CardContent className="space-y-6 p-4 sm:p-6">
                        <section>
                            <h2 className="mb-3 text-sm font-bold tracking-wider text-blue-500 uppercase">
                                Student Information
                            </h2>
                            <div className="grid grid-cols-1 gap-4 rounded-xl border border-slate-100 bg-slate-50 p-4 sm:grid-cols-2 md:grid-cols-3">
                                <Detail label="Full Name" value={fullName} className=''/>
                                <Detail
                                    label="Email"
                                    value={assessment.email}
                                />
                                <Detail
                                    label="Contact Number"
                                    value={assessment.contact_num
                                        ?.replace(/\D/g, '')
                                        .replace(
                                            /(\d{4})(\d{3})(\d{4})/,
                                            '$1 $2 $3',
                                        )}
                                />
                                <Detail
                                    label="Address"
                                    value={assessment.address}
                                    className="sm:col-span-2"
                                />
                                <Detail
                                    label="Student ID"
                                    value={assessment.student_id}
                                />
                            </div>
                        </section>

                        <section>
                            <h2 className="mb-3 text-sm font-bold tracking-wider text-blue-500 uppercase">
                                Course & Academic Information Requested
                            </h2>
                            <div className="grid grid-cols-1 gap-4 rounded-xl border border-slate-100 bg-slate-50 p-4 sm:grid-cols-2 md:grid-cols-3">
                                <Detail
                                    label="Course Code"
                                    value={
                                        assessment.course?.course_code ||
                                        `ID: ${assessment.course_id}`
                                    }
                                />
                                <Detail
                                    label="Course Description"
                                    value={assessment.course?.course_desc}
                                />
                                <Detail
                                    label="Enrolled Under"
                                    value={assessment.enrolled_under}
                                />
                                <Detail
                                    label="Semester"
                                    value={assessment.semester}
                                />
                                <Detail
                                    label="SY Last Attended"
                                    value={assessment.sy_last_attended}
                                />
                            </div>
                        </section>
                    </CardContent>
                </Card>

                <Card className="overflow-hidden rounded-2xl border border-blue-100 bg-[#FAFAF5] shadow-sm">
                    <CardHeader className="gap-3 border-b border-blue-100 bg-white sm:flex-row sm:items-start sm:justify-between">
                        <div className="space-y-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <CardTitle className="text-xl font-bold text-[#0B3D91]">
                                    Student Statement Printer
                                </CardTitle>
                                <Badge variant="outline">
                                    {ledgerStatement.schoolYear} ·{' '}
                                    {ledgerStatement.semester}
                                </Badge>
                            </div>
                            <CardDescription>
                                The preview and PDF include only the term
                                requested in this assessment.
                            </CardDescription>
                        </div>
                        <div className="flex gap-2">
                            <Button
                                onClick={openEmailPreview}
                                disabled={!ledgerStatement.selectedStudent}
                                variant="outline"
                                className="bg-white text-[#0B3D91] border-blue-200 hover:bg-blue-50"
                            >
                                <Mail className="h-4 w-4" />
                                Email Statement
                            </Button>
                            <Button
                                onClick={printStatement}
                                disabled={!ledgerStatement.selectedStudent}
                                className="bg-[#0F6FFF] text-white hover:bg-[#0B5DDB]"
                            >
                                <Printer className="h-4 w-4" />
                                Print Statement
                            </Button>
                        </div>
                    </CardHeader>

                    <CardContent className="space-y-6 p-4 sm:p-6">
                        <MatchNotice statement={ledgerStatement} />

                        {needsSelection && (
                            <Card className="border-amber-200 bg-amber-50/60">
                                <CardHeader>
                                    <CardTitle className="text-base text-amber-950">
                                        Select the correct student
                                    </CardTitle>
                                    <CardDescription className="text-amber-800">
                                        Only students with ledger transactions
                                        in the requested term are shown.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <div className="relative">
                                        <Search className="absolute top-2.5 left-3 h-4 w-4 text-slate-400" />
                                        <Input
                                            value={search}
                                            onChange={(event) =>
                                                setSearch(event.target.value)
                                            }
                                            placeholder="Search by name or student ID..."
                                            className="bg-white pr-9 pl-9"
                                        />
                                        {search && (
                                            <button
                                                type="button"
                                                onClick={() => setSearch('')}
                                                aria-label="Clear student search"
                                                className="absolute top-2.5 right-3 text-slate-400 hover:text-slate-700"
                                            >
                                                <X className="h-4 w-4" />
                                            </button>
                                        )}
                                    </div>
                                    <select
                                        defaultValue=""
                                        onChange={(event) =>
                                            selectStudent(event.target.value)
                                        }
                                        className="w-full rounded-md border border-amber-200 bg-white px-3 py-2 text-sm text-slate-700 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                    >
                                        <option value="" disabled>
                                            Choose a student
                                        </option>
                                        {filteredCandidates.map((candidate) => (
                                            <option
                                                key={candidate.key}
                                                value={candidate.key}
                                            >
                                                {candidate.name}
                                                {candidate.studentId
                                                    ? ` · ${candidate.studentId}`
                                                    : ''}
                                            </option>
                                        ))}
                                    </select>
                                    {filteredCandidates.length === 0 && (
                                        <p className="text-sm text-amber-800">
                                            No term-specific student matches
                                            this search.
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                        )}

                        {ledgerStatement.selectedStudent && (
                            <>
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <SummaryCard
                                        label="Total Billed Charges (AR)"
                                        value={
                                            ledgerStatement.summary.totalCharges
                                        }
                                    />
                                    <SummaryCard
                                        label="Total Payments Received"
                                        value={
                                            ledgerStatement.summary
                                                .totalPayments
                                        }
                                        valueClassName="text-emerald-600"
                                    />
                                    <SummaryCard
                                        label="Outstanding Balance"
                                        value={
                                            ledgerStatement.summary
                                                .outstandingBalance
                                        }
                                    />
                                </div>

                                <Card className="border-blue-100 bg-white">
                                    <CardHeader>
                                        <CardTitle className="text-lg text-[#0B3D91]">
                                            {
                                                ledgerStatement.selectedStudent
                                                    .name
                                            }
                                        </CardTitle>
                                        <CardDescription>
                                            {ledgerStatement.records.length}{' '}
                                            transaction
                                            {ledgerStatement.records.length ===
                                            1
                                                ? ''
                                                : 's'}{' '}
                                            found for the requested term
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="overflow-x-auto p-0 sm:p-0">
                                        <table className="w-full min-w-180 border-collapse text-left text-xs">
                                            <thead>
                                                <tr className="border-y border-blue-100 bg-blue-50/60 font-semibold text-[#0B3D91]">
                                                    <th className="px-4 py-3">
                                                        Date
                                                    </th>
                                                    <th className="px-4 py-3">
                                                        Course
                                                    </th>
                                                    <th className="px-4 py-3">
                                                        Ref / OR #
                                                    </th>
                                                    <th className="px-4 py-3">
                                                        Particulars
                                                    </th>
                                                    <th className="px-4 py-3">
                                                        Type
                                                    </th>
                                                    <th className="px-4 py-3 text-right">
                                                        Amount
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {ledgerStatement.records
                                                    .length === 0 ? (
                                                    <tr>
                                                        <td
                                                            colSpan={6}
                                                            className="px-4 py-10 text-center text-slate-500"
                                                        >
                                                            No ledger
                                                            transactions were
                                                            found for this term.
                                                        </td>
                                                    </tr>
                                                ) : (
                                                    ledgerStatement.records.map(
                                                        (record) => (
                                                            <tr
                                                                key={record.id}
                                                                className="border-b border-blue-50 hover:bg-blue-50/40"
                                                            >
                                                                <td className="px-4 py-3 text-slate-700">
                                                                    {formatDate(
                                                                        record.transactionDate,
                                                                    )}
                                                                </td>
                                                                <td className="px-4 py-3 text-slate-700">
                                                                    {record.course ||
                                                                        '—'}
                                                                </td>
                                                                <td className="px-4 py-3 text-slate-700">
                                                                    {record.referenceNo ||
                                                                        '—'}
                                                                </td>
                                                                <td className="px-4 py-3 text-slate-700">
                                                                    {record.particulars ||
                                                                        '—'}
                                                                </td>
                                                                <td className="px-4 py-3">
                                                                    <Badge
                                                                        variant={
                                                                            record.type ===
                                                                            'AR'
                                                                                ? 'outline'
                                                                                : 'secondary'
                                                                        }
                                                                    >
                                                                        {record.type ||
                                                                            '—'}
                                                                    </Badge>
                                                                </td>
                                                                <td className="px-4 py-3 text-right font-medium text-[#0B3D91]">
                                                                    {currencyFormatter.format(
                                                                        record.amount,
                                                                    )}
                                                                </td>
                                                            </tr>
                                                        ),
                                                    )
                                                )}
                                            </tbody>
                                        </table>
                                    </CardContent>
                                </Card>
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Email Preview Modal */}
            <Dialog open={isEmailPreviewOpen} onOpenChange={setIsEmailPreviewOpen}>
                <DialogContent className="sm:max-w-[480px]">
                    <DialogHeader>
                        <DialogTitle>Email Statement of Account</DialogTitle>
                        <DialogDescription>
                            Review and customize the email before sending the Statement of Account PDF.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 py-4">
                        <div className="space-y-2">
                            <label className="block text-sm font-medium text-slate-700">
                                To
                            </label>
                            <Input
                                value={assessment.email}
                                disabled
                                className="bg-slate-50 text-slate-600"
                            />
                        </div>
                        <div className="space-y-2">
                            <label className="block text-sm font-medium text-slate-700">
                                Recipient Name
                            </label>
                            <Input
                                value={emailRecipientName}
                                onChange={(e) => setEmailRecipientName(e.target.value)}
                                placeholder="Recipient name"
                            />
                        </div>
                        <div className="space-y-2">
                            <label className="block text-sm font-medium text-slate-700">
                                Subject
                            </label>
                            <Input
                                value={emailSubject}
                                onChange={(e) => setEmailSubject(e.target.value)}
                                placeholder="Email subject"
                            />
                        </div>
                        <div className="space-y-2">
                            <label className="block text-sm font-medium text-slate-700">
                                Additional Note (optional)
                            </label>
                            <Textarea
                                value={emailNote}
                                onChange={(e) => setEmailNote(e.target.value)}
                                placeholder="Add a personal note..."
                                rows={4}
                            />
                        </div>
                    </div>
                    <DialogFooter className="gap-2">
                        <Button
                            variant="outline"
                            onClick={() => setIsEmailPreviewOpen(false)}
                            disabled={isSendingEmail}
                        >
                            Cancel
                        </Button>
                        <Button
                            onClick={handleConfirmSendEmail}
                            disabled={isSendingEmail}
                            className="bg-[#0B3D91] hover:bg-[#092D6F]"
                        >
                            {isSendingEmail ? 'Sending...' : 'Send Email'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function Detail({
    label,
    value,
    className = '',
}: {
    label: string;
    value?: string | null;
    className?: string;
}) {
    return (
        <div className={className}>
            <span className="text-x font-semibold text-slate-400">
                {label}
            </span>
            <p className="text-lg font-medium text-slate-900">{value || '—'}</p>
        </div>
    );
}

function SummaryCard({
    label,
    value,
    valueClassName = 'text-[#0B3D91]',
}: {
    label: string;
    value: number;
    valueClassName?: string;
}) {
    return (
        <Card className="border-blue-100 bg-white p-4">
            <p className="text-xs text-slate-500">{label}</p>
            <p className={`mt-1 text-base font-bold ${valueClassName}`}>
                {currencyFormatter.format(value)}
            </p>
        </Card>
    );
}

function MatchNotice({ statement }: { statement: LedgerStatement }) {
    if (statement.matchStatus === 'unsupported') {
        return (
            <div className="flex gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-slate-700">
                <FileQuestion className="mt-0.5 h-5 w-5 shrink-0" />
                <div>
                    <p className="font-semibold">Ledger unavailable</p>
                    <p className="text-sm">
                        Undergraduate ledger statements are not available in the
                        current system.
                    </p>
                </div>
            </div>
        );
    }

    if (statement.selectedStudent) {
        return (
            <div className="flex gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900">
                <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0" />
                <div>
                    <p className="font-semibold">
                        {statement.matchStatus === 'manual'
                            ? 'Student selected'
                            : 'Student matched automatically'}
                    </p>
                    <p className="text-sm">
                        {statement.selectedStudent.name}
                        {statement.selectedStudent.studentId
                            ? ` · ${statement.selectedStudent.studentId}`
                            : ''}
                    </p>
                </div>
            </div>
        );
    }

    return (
        <div className="flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-950">
            <AlertCircle className="mt-0.5 h-5 w-5 shrink-0" />
            <div>
                <p className="font-semibold">
                    {statement.matchStatus === 'ambiguous'
                        ? 'Multiple possible students found'
                        : 'Student could not be matched automatically'}
                </p>
                <p className="text-sm">
                    Select the correct student below before previewing or
                    printing the statement.
                </p>
            </div>
        </div>
    );
}
