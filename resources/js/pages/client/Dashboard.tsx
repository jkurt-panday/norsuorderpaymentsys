import { Head, Link, usePage, usePoll } from '@inertiajs/react';
import {
    FileText,
    ArrowRight,
    CheckCircle2,
    ShieldCheck,
    User,
    Clock,
    Printer,
} from 'lucide-react';
import React from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

interface Membership {
    member_code: string;
}

interface PaymentDetailOption {
    payment_desc: string;
}

interface StaffInput {
    status: 'pending' | 'approved' | 'cancelled';
}

interface Submission {
    id: number;
    reference_number: string;
    request_type: string;
    amount: number;
    created_at: string;
    membership: Membership | null;
    payment_detail_option: PaymentDetailOption | null;
    staff_input: StaffInput | null;
}

interface PageProps {
    auth: { user: { name: string; email: string; role: string } | null };
    submissions: Submission[];
}

const statusBadgeClass = (status?: string) => {
    switch (status) {
        case 'approved':
            return 'bg-emerald-100 text-emerald-800';
        case 'cancelled':
            return 'bg-rose-100 text-rose-800';
        case 'pending':
            return 'bg-amber-100 text-amber-900';
        default:
            return 'bg-slate-100 text-slate-700';
    }
};

const statusLabel = (status?: string) => {
    if (!status) return 'Unprocessed';
    return status.charAt(0).toUpperCase() + status.slice(1);
};

const formatDate = (value: string) => {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
    });
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);
};

export default function ClientDashboard() {
    const { auth, submissions } = usePage().props as unknown as PageProps;
    const user = auth?.user;

    // Keeps the submissions table live — a staff member could approve/
    // cancel a request while this page is open, and the client should see
    // that without needing to manually refresh. `only: ['submissions']`
    // means each poll re-fetches just this prop, not the whole page.
    usePoll(5000, {
        only: ['submissions'],
    });

    return (
        <div className="mx-auto max-w-5xl space-y-6">
            <Head title="Client Dashboard" />

            {/* Header Banner */}
            <div className="flex flex-col gap-4 border-b border-[#CFE3FF] pb-5 md:flex-row md:items-center md:justify-between">
                <div>
                    <div className="flex items-center gap-2">
                        <h1 className="text-2xl font-bold tracking-tight text-[#0B3D91]">
                            Welcome back, {user?.name || 'Client'}!
                        </h1>
                        <Badge
                            variant="outline"
                            className="border-[#B9D8FF] bg-[#EAF2FF] font-semibold text-[#0B62E0] capitalize"
                        >
                            {user?.role || 'Client'} Account
                        </Badge>
                    </div>
                    <p className="mt-1 text-sm text-[#5C7A9E]">
                        NORSU Order of Payment System — Client Portal
                    </p>
                </div>

                <div>
                    <Link href="/public/opform">
                        <Button className="gap-2 bg-[#0F6FFF] text-white shadow-sm hover:bg-[#0B5DDB]">
                            <FileText className="h-4 w-4" />
                            Submit New Request
                            <ArrowRight className="h-4 w-4" />
                        </Button>
                    </Link>
                </div>
            </div>

            {/* Main Cards Grid */}
            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                {/* Account Details Card */}
                <Card className="border-[#CFE3FF] bg-white shadow-sm">
                    <CardHeader className="pb-3">
                        <CardTitle className="flex items-center gap-2 text-base text-[#0B3D91]">
                            <User className="h-4 w-4 text-[#0F6FFF]" />
                            Account Information
                        </CardTitle>
                        <CardDescription className="text-[#7FA6D6]">
                            Your registered client profile details
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm text-[#334E68]">
                        <div className="flex justify-between border-b border-[#EAF2FF] py-2">
                            <span className="text-[#7FA6D6]">Full Name</span>
                            <span className="font-medium text-[#0B3D91]">
                                {user?.name}
                            </span>
                        </div>
                        <div className="flex justify-between border-b border-[#EAF2FF] py-2">
                            <span className="text-[#7FA6D6]">
                                Email Address
                            </span>
                            <span className="font-medium text-[#0B3D91]">
                                {user?.email}
                            </span>
                        </div>
                        <div className="flex justify-between py-2">
                            <span className="text-[#7FA6D6]">
                                Account Status
                            </span>
                            <span className="inline-flex items-center gap-1 font-semibold text-emerald-600">
                                <CheckCircle2 className="h-4 w-4" /> Active
                            </span>
                        </div>
                    </CardContent>
                </Card>

                {/* Quick Actions Card */}
                <Card className="border-[#CFE3FF] bg-white shadow-sm">
                    <CardHeader className="pb-3">
                        <CardTitle className="flex items-center gap-2 text-base text-[#0B3D91]">
                            <ShieldCheck className="h-4 w-4 text-[#0F6FFF]" />
                            Services & Actions
                        </CardTitle>
                        <CardDescription className="text-[#7FA6D6]">
                            Available client services for payment processing
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex items-start gap-3 rounded-lg border border-[#EAF2FF] bg-[#F8FAFC] p-4">
                            <FileText className="mt-0.5 h-5 w-5 shrink-0 text-[#0F6FFF]" />
                            <div>
                                <h4 className="text-sm font-semibold text-[#0B3D91]">
                                    Submit Order of Payment
                                </h4>
                                <p className="mt-1 text-xs text-[#5C7A9E]">
                                    Fill out and submit an order of payment
                                    request form directly to the university
                                    cashier.
                                </p>
                                <Link
                                    href="/public/opform"
                                    className="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-[#0F6FFF] hover:underline"
                                >
                                    Go to Submission Form →
                                </Link>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Recent Submissions */}
            <Card className="border-[#CFE3FF] bg-white shadow-sm">
                <CardHeader className="pb-3">
                    <CardTitle className="flex items-center gap-2 text-base text-[#0B3D91]">
                        <Clock className="h-4 w-4 text-[#0F6FFF]" />
                        Recent Submissions
                    </CardTitle>
                    <CardDescription className="text-[#7FA6D6]">
                        Your submitted order of payment requests
                    </CardDescription>
                </CardHeader>
                <CardContent className="p-0">
                    {submissions.length > 0 ? (
                        <Table>
                            <TableHeader>
                                <TableRow className="border-b border-[#EAF2FF] hover:bg-transparent">
                                    <TableHead className="text-[#7FA6D6]">
                                        Reference
                                    </TableHead>
                                    <TableHead className="text-[#7FA6D6]">
                                        Type
                                    </TableHead>
                                    <TableHead className="text-[#7FA6D6]">
                                        Payment Option
                                    </TableHead>
                                    <TableHead className="text-right text-[#7FA6D6]">
                                        Amount
                                    </TableHead>
                                    <TableHead className="text-[#7FA6D6]">
                                        Status
                                    </TableHead>
                                    <TableHead className="text-[#7FA6D6]">
                                        Date
                                    </TableHead>
                                    <TableHead className="text-center text-[#7FA6D6]">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {submissions.map((submission) => (
                                    <TableRow
                                        key={submission.id}
                                        className="border-b border-[#EAF2FF] last:border-0"
                                    >
                                        <TableCell className="font-medium text-[#0B3D91]">
                                            {submission.reference_number}
                                        </TableCell>
                                        <TableCell className="text-[#334E68]">
                                            {submission.request_type}
                                        </TableCell>
                                        <TableCell className="text-[#334E68]">
                                            {submission.payment_detail_option
                                                ?.payment_desc ?? 'N/A'}
                                        </TableCell>
                                        <TableCell className="text-right font-medium text-[#0B3D91]">
                                            {formatCurrency(submission.amount)}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="secondary"
                                                className={statusBadgeClass(
                                                    submission.staff_input
                                                        ?.status,
                                                )}
                                            >
                                                {statusLabel(
                                                    submission.staff_input
                                                        ?.status,
                                                )}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-[#5C7A9E]">
                                            {formatDate(submission.created_at)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <a
                                                href={`/public/success/${submission.reference_number}/print`}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="inline-flex h-8 items-center gap-1.5 rounded-md border border-[#CFE3FF] px-3 text-xs font-medium text-[#0F6FFF] hover:bg-[#EAF2FF] hover:text-[#0B5DDB]"
                                            >
                                                <Printer className="h-3.5 w-3.5" />
                                                Print
                                            </a>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    ) : (
                        <p className="py-8 text-center text-sm text-[#7FA6D6]">
                            You haven't submitted any requests yet.
                        </p>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
