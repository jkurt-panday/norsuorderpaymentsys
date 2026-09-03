import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2 } from 'lucide-react';
import type { FormEvent } from 'react';
import cashier from '@/routes/cashier';

interface PaymentRequest {
    id: number;
    status: 'processed' | 'paid';
    purpose: string | null;
    or_no: string | null;
    or_date: string | null;
    form_input: {
        reference_number: string;
        firstname_or_office: string;
        middlename_or_project: string | null;
        lastname_or_agency: string;
        email: string;
        amount: string | number;
        membership: { member_code: string } | null;
        payment_detail_option: { payment_desc: string } | null;
    };
}

const money = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
});

export default function CashierRequestShow({
    request,
}: {
    request: PaymentRequest;
}) {
    const form = useForm({
        or_no: request.or_no ?? '',
        or_date:
            request.or_date?.slice(0, 10) ??
            new Date().toISOString().slice(0, 10),
    });
    const payer = [
        request.form_input.firstname_or_office,
        request.form_input.middlename_or_project,
        request.form_input.lastname_or_agency,
    ]
        .filter(Boolean)
        .join(' ');

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(cashier.requests.payment.update.url(request.id));
    };

    return (
        <>
            <Head title={`Payment ${request.form_input.reference_number}`} />

            <div className="mx-auto max-w-3xl space-y-5">
                <Link
                    href={cashier.requests.index.url()}
                    className="inline-flex items-center gap-2 text-sm font-medium text-blue-700 hover:underline"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back to requests
                </Link>

                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex flex-col gap-3 border-b border-slate-200 pb-5 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p className="text-xs font-medium tracking-wide text-slate-500 uppercase">
                                Reference number
                            </p>
                            <h1 className="mt-1 text-2xl font-semibold text-slate-900">
                                {request.form_input.reference_number}
                            </h1>
                        </div>
                        <span
                            className={`w-fit rounded-full px-3 py-1 text-xs font-semibold ${
                                request.status === 'paid'
                                    ? 'bg-emerald-100 text-emerald-800'
                                    : 'bg-amber-100 text-amber-800'
                            }`}
                        >
                            {request.status === 'paid'
                                ? 'Paid'
                                : 'Ready for payment'}
                        </span>
                    </div>

                    <dl className="grid gap-x-8 gap-y-5 py-6 sm:grid-cols-2">
                        <Detail label="Payer" value={payer} />
                        <Detail
                            label="Email"
                            value={request.form_input.email}
                        />
                        <Detail
                            label="Amount"
                            value={money.format(
                                Number(request.form_input.amount),
                            )}
                        />
                        <Detail
                            label="Payment for"
                            value={
                                request.form_input.payment_detail_option
                                    ?.payment_desc ?? 'Not specified'
                            }
                        />
                        <Detail
                            label="Membership"
                            value={
                                request.form_input.membership?.member_code ??
                                'N/A'
                            }
                        />
                        <Detail
                            label="Purpose"
                            value={request.purpose ?? 'Not specified'}
                        />
                    </dl>

                    {request.status === 'paid' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-5">
                            <div className="mb-4 flex items-center gap-2 font-semibold text-emerald-800">
                                <CheckCircle2 className="h-5 w-5" />
                                Payment recorded
                            </div>
                            <dl className="grid gap-4 sm:grid-cols-2">
                                <Detail
                                    label="OR number"
                                    value={request.or_no ?? '—'}
                                />
                                <Detail
                                    label="Payment date"
                                    value={request.or_date ?? '—'}
                                />
                            </dl>
                        </div>
                    )}
                    <form
                        onSubmit={submit}
                        className="mt-5 space-y-5 rounded-lg border border-blue-200 bg-blue-50/50 p-5"
                    >
                        <h2 className="font-semibold text-slate-900">
                            {request.status === 'paid'
                                ? 'Correct payment details'
                                : 'Record payment'}
                        </h2>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <label className="text-sm font-medium text-slate-700">
                                OR number
                                <input
                                    required
                                    maxLength={50}
                                    inputMode="numeric"
                                    pattern="[0-9\-\.\/\s]+"
                                    title="Only numbers, dashes, slashes, dots and spaces are allowed"
                                    value={form.data.or_no}
                                    onChange={(event) => {
                                        const filtered =
                                            event.target.value.replace(
                                                /[^0-9\-\.\/\s]/g,
                                                '',
                                            );
                                        form.setData('or_no', filtered);
                                    }}
                                    className="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                />
                                {form.errors.or_no && (
                                    <span className="mt-1 block text-xs text-rose-600">
                                        {form.errors.or_no}
                                    </span>
                                )}
                            </label>
                            <label className="text-sm font-medium text-slate-700">
                                Payment date
                                <input
                                    required
                                    type="date"
                                    max={new Date().toISOString().slice(0, 10)}
                                    value={form.data.or_date}
                                    onChange={(event) =>
                                        form.setData(
                                            'or_date',
                                            event.target.value,
                                        )
                                    }
                                    className="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                />
                                {form.errors.or_date && (
                                    <span className="mt-1 block text-xs text-rose-600">
                                        {form.errors.or_date}
                                    </span>
                                )}
                            </label>
                        </div>
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="rounded-lg bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {form.processing
                                ? 'Saving…'
                                : request.status === 'paid'
                                  ? 'Update payment details'
                                  : 'Mark as paid'}
                        </button>
                    </form>
                </div>
            </div>
        </>
    );
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-xs font-medium tracking-wide text-slate-500 uppercase">
                {label}
            </dt>
            <dd className="mt-1 text-sm break-words text-slate-900">{value}</dd>
        </div>
    );
}
