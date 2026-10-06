import { router } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowDownUp,
    CalendarDays,
    ExternalLink,
    FileText,
    GraduationCap,
    Loader2,
    Mail,
    Pencil,
    Phone,
    PlusCircle,
    ReceiptText,
    RotateCcw,
    Wallet,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    create,
    edit,
    generatePdf,
    studentBalance,
} from '@/actions/App/Http/Controllers/GraduateLedgerController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';

interface DrawerTransaction {
    id: number | string;
    transactionDate: string;
    referenceNo?: string | null;
    orLink?: string | null;
    particulars?: string | null;
    arPayment: string;
    amount: number;
    schoolYear?: string;
    semester?: string;
}

interface StudentBalanceData {
    student: {
        id: number;
        studentNumber?: string | null;
        name: string;
        email?: string | null;
        contactNumber?: string | null;
        course?: string | null;
    };
    summary: {
        totalCharges: number;
        totalPayments: number;
        totalAdjustments?: number;
        outstandingBalance: number;
    };
    transactions: DrawerTransaction[];
}

interface Props {
    open: boolean;
    studentId: number | null;
    selectedTransactionId: number | string | null;
    onOpenChange: (open: boolean) => void;
}

function currency(value: number): string {
    return `₱${Number(value ?? 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

function formatDate(value?: string | null): string {
    if (!value) {
return 'No date';
}

    const datePart = String(value).split('T')[0].split(' ')[0];
    const date = new Date(`${datePart}T00:00:00`);

    return Number.isNaN(date.getTime())
        ? datePart
        : date.toLocaleDateString('en-PH', {
              month: 'short',
              day: 'numeric',
              year: 'numeric',
          });
}

function isPayment(type: string): boolean {
    const normalized = (type ?? '').trim().toLowerCase();
    return ['payment', 'p'].includes(normalized);
}

function isAdjustment(type: string): boolean {
    const normalized = (type ?? '').trim().toLowerCase();
    return normalized.includes('adjust');
}

export default function StudentBalanceDrawer({
    open,
    studentId,
    selectedTransactionId,
    onOpenChange,
}: Props) {
    const [data, setData] = useState<StudentBalanceData | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [retryCount, setRetryCount] = useState(0);

    useEffect(() => {
        if (!open || studentId === null) {
return;
}

        const controller = new AbortController();
        Promise.resolve()
            .then(() => {
                setLoading(true);
                setError(null);
                setData(null);

                return fetch(studentBalance.url(studentId), {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });
            })
            .then((response) => {
                if (!response.ok) {
throw new Error('Unable to load this ledger.');
}

                return response.json() as Promise<StudentBalanceData>;
            })
            .then(setData)
            .catch((requestError: Error) => {
                if (requestError.name !== 'AbortError') {
                    setError(requestError.message);
                }
            })
            .finally(() => {
                if (!controller.signal.aborted) {
setLoading(false);
}
            });

        return () => controller.abort();
    }, [open, studentId, retryCount]);

    const balance = data?.summary.outstandingBalance ?? 0;

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full gap-0 border-[#CFE3FF] p-0 sm:max-w-xl">
                <SheetHeader className="border-b border-[#CFE3FF] bg-[#F7FAFF] px-6 py-5 pr-14">
                    <SheetTitle className="text-lg font-bold text-[#0B3D91]">
                        Student balance
                    </SheetTitle>
                    <SheetDescription>
                        Complete account summary and transaction history
                    </SheetDescription>
                </SheetHeader>

                <div className="min-h-0 flex-1 overflow-y-auto px-6 py-5">
                    {loading && (
                        <div className="space-y-5" aria-label="Loading student balance">
                            <div className="h-24 animate-pulse rounded-xl bg-slate-100" />
                            <div className="grid grid-cols-3 gap-3">
                                {[0, 1, 2].map((item) => (
                                    <div key={item} className="h-20 animate-pulse rounded-lg bg-slate-100" />
                                ))}
                            </div>
                            <div className="h-64 animate-pulse rounded-xl bg-slate-100" />
                        </div>
                    )}

                    {error && (
                        <div className="flex items-start justify-between gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-red-700">
                            <div className="flex gap-3">
                                <AlertCircle className="mt-0.5 h-5 w-5 shrink-0" />
                                <div>
                                    <p className="font-semibold">
                                        Could not load student balance
                                    </p>
                                    <p className="mt-1 text-xs">{error}</p>
                                </div>
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                disabled={loading}
                                onClick={() => setRetryCount((c) => c + 1)}
                                className="shrink-0 border-red-300 bg-white text-red-700 hover:bg-red-100 hover:text-red-800"
                            >
                                {loading ? (
                                    <Loader2 className="mr-1 h-3.5 w-3.5 animate-spin" />
                                ) : (
                                    <RotateCcw className="mr-1 h-3.5 w-3.5" />
                                )}
                                Retry
                            </Button>
                        </div>
                    )}

                    {data && (
                        <div className="space-y-6">
                            <section className="rounded-xl border border-[#CFE3FF] bg-white p-4 shadow-xs">
                                <div className="flex items-start gap-3">
                                    <div className="rounded-full bg-[#EAF2FF] p-2.5 text-[#0F6FFF]">
                                        <GraduationCap className="h-5 w-5" />
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-bold text-[#0B3D91]">{data.student.name}</p>
                                        <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-[#5C7A9E]">
                                            {data.student.studentNumber && <span>{data.student.studentNumber}</span>}
                                            {data.student.course && <Badge variant="outline">{data.student.course}</Badge>}
                                        </div>
                                    </div>
                                </div>
                                {(data.student.email || data.student.contactNumber) && (
                                    <div className="mt-4 grid gap-2 border-t border-[#EAF2FF] pt-3 text-xs text-[#5C7A9E] sm:grid-cols-2">
                                        {data.student.email && (
                                            <span className="flex items-center gap-2"><Mail className="h-3.5 w-3.5" />{data.student.email}</span>
                                        )}
                                        {data.student.contactNumber && (
                                            <span className="flex items-center gap-2"><Phone className="h-3.5 w-3.5" />{data.student.contactNumber}</span>
                                        )}
                                    </div>
                                )}
                            </section>

                            <section className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div className="rounded-lg border border-blue-200 bg-blue-50/60 p-3">
                                    <ReceiptText className="h-4 w-4 text-blue-600" />
                                    <p className="mt-2 text-[11px] font-medium text-blue-700">Account Receivables</p>
                                    <p className="mt-0.5 font-bold text-blue-900">{currency(data.summary.totalCharges)}</p>
                                </div>
                                <div className="rounded-lg border border-emerald-200 bg-emerald-50/60 p-3">
                                    <Wallet className="h-4 w-4 text-emerald-600" />
                                    <p className="mt-2 text-[11px] font-medium text-emerald-700">Payments</p>
                                    <p className="mt-0.5 font-bold text-emerald-900">{currency(data.summary.totalPayments)}</p>
                                </div>
                                {(data.summary.totalAdjustments ?? 0) > 0 && (
                                    <div className="rounded-lg border border-purple-200 bg-purple-50/60 p-3">
                                        <ArrowDownUp className="h-4 w-4 text-purple-600" />
                                        <p className="mt-2 text-[11px] font-medium text-purple-700">Adjustments</p>
                                        <p className="mt-0.5 font-bold text-purple-900">{currency(data.summary.totalAdjustments ?? 0)}</p>
                                    </div>
                                )}
                                <div className={`rounded-lg border p-3 ${balance > 0 ? 'border-amber-200 bg-amber-50/70' : 'border-emerald-200 bg-emerald-50/60'}`}>
                                    <AlertCircle className={`h-4 w-4 ${balance > 0 ? 'text-amber-600' : 'text-emerald-600'}`} />
                                    <p className={`mt-2 text-[11px] font-medium ${balance > 0 ? 'text-amber-700' : 'text-emerald-700'}`}>Current balance</p>
                                    <p className={`mt-0.5 font-bold ${balance > 0 ? 'text-amber-900' : 'text-emerald-900'}`}>{currency(balance)}</p>
                                </div>
                            </section>

                            <section>
                                <div className="mb-3 flex items-center justify-between gap-3">
                                    <h3 className="font-semibold text-[#0B3D91]">Transaction timeline</h3>
                                    <span className="text-xs text-[#7FA6D6]">{data.transactions.length} entries</span>
                                </div>
                                {data.transactions.length === 0 ? (
                                    <div className="rounded-lg border border-dashed border-[#CFE3FF] p-8 text-center text-sm text-[#7FA6D6]">
                                        No transactions found for this student.
                                    </div>
                                ) : (
                                    <ol className="relative ml-2 border-l border-[#CFE3FF]">
                                        {data.transactions.map((transaction) => {
                                            const payment = isPayment(transaction.arPayment);
                                            const adjustment = isAdjustment(transaction.arPayment);

                                            return (
                                                <li key={transaction.id} className="relative pb-5 pl-6 last:pb-0">
                                                    <span className={`absolute -left-2 top-1 flex h-4 w-4 rounded-full border-2 border-white ${adjustment ? 'bg-purple-500' : payment ? 'bg-emerald-500' : 'bg-blue-500'}`} />
                                                    <div className={`rounded-lg border p-3 ${String(transaction.id) === String(selectedTransactionId) ? 'border-[#0F6FFF] bg-[#F3F8FF] ring-1 ring-[#0F6FFF]/20' : 'border-[#E1ECFA] bg-white'}`}>
                                                        <div className="flex items-start justify-between gap-3">
                                                            <div className="min-w-0">
                                                                <p className="font-medium text-[#334E68]">{transaction.particulars || transaction.arPayment}</p>
                                                                <p className="mt-1 flex items-center gap-1 text-[11px] text-[#7FA6D6]"><CalendarDays className="h-3 w-3" />{formatDate(transaction.transactionDate)}</p>
                                                            </div>
                                                            <p className={`shrink-0 font-bold ${adjustment ? 'text-purple-700' : payment ? 'text-emerald-700' : 'text-blue-700'}`}>
                                                                {(payment || adjustment) ? '−' : '+'}{currency(Math.abs(Number(transaction.amount || 0)))}
                                                            </p>
                                                        </div>
                                                        <div className="mt-2 flex items-center justify-between gap-3 border-t border-[#EDF3FB] pt-2 text-[11px] text-[#7FA6D6]">
                                                            {transaction.orLink ? (
                                                                <a
                                                                    href={transaction.orLink}
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    className="inline-flex items-center gap-1 font-medium text-[#0B62E0] hover:underline"
                                                                >
                                                                    <ExternalLink className="h-3 w-3" />
                                                                    {transaction.referenceNo}
                                                                </a>
                                                            ) : (
                                                                <span>{transaction.referenceNo || 'No reference number'}</span>
                                                            )}
                                                            <button type="button" onClick={() => router.get(edit.url(transaction.id))} className="inline-flex items-center gap-1 font-medium text-[#0B62E0] hover:underline">
                                                                <Pencil className="h-3 w-3" /> Edit
                                                            </button>
                                                        </div>
                                                    </div>
                                                </li>
                                            );
                                        })}
                                    </ol>
                                )}
                            </section>
                        </div>
                    )}
                </div>

                {data && (
                    <SheetFooter className="flex flex-row items-center justify-between gap-2 border-t border-[#CFE3FF] bg-white px-6 py-3">
                        <Button
                            onClick={() => router.get(create.url({ query: { student_id: data.student.id, entry_type: 'payment' } }))}
                            className="flex-1 bg-[#0F6FFF] text-white hover:bg-[#0B5DDB]"
                        >
                            <PlusCircle className="h-4 w-4" /> Add payment
                        </Button>
                        <div className="flex items-center gap-1">
                            <Button
                                variant="outline"
                                size="icon"
                                title="Edit selected transaction"
                                disabled={selectedTransactionId === null}
                                onClick={() => selectedTransactionId !== null && router.get(edit.url(selectedTransactionId))}
                                className="border-[#CFE3FF] text-[#0B3D91] hover:bg-[#EAF2FF]"
                            >
                                <Pencil className="h-4 w-4" />
                            </Button>
                            <Button
                                variant="outline"
                                size="icon"
                                title="Print statement"
                                onClick={() => window.open(generatePdf.url({ query: { student_id: data.student.id } }), '_blank', 'noopener,noreferrer')}
                                className="border-[#CFE3FF] text-[#0B3D91] hover:bg-[#EAF2FF]"
                            >
                                <FileText className="h-4 w-4" />
                            </Button>
                        </div>
                    </SheetFooter>
                )}
            </SheetContent>
        </Sheet>
    );
}
