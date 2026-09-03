import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import type { FormEvent} from 'react';
import { useState } from 'react';
import cashier from '@/routes/cashier';

interface FormInput {
    reference_number: string;
    firstname_or_office: string;
    middlename_or_project: string | null;
    lastname_or_agency: string;
    email: string;
    amount: string | number;
}

interface PaymentRequest {
    id: number;
    status: 'processed' | 'paid';
    or_no: string | null;
    or_date: string | null;
    form_input: FormInput;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    requests: {
        data: PaymentRequest[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: {
        search: string;
        status: 'processed' | 'paid';
    };
}

const money = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
});

function fullName(formInput: FormInput) {
    return [
        formInput.firstname_or_office,
        formInput.middlename_or_project,
        formInput.lastname_or_agency,
    ]
        .filter(Boolean)
        .join(' ');
}

export default function CashierRequestsIndex({ requests, filters }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status);

    const filter = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            cashier.requests.index.url(),
            { search: search || undefined, status },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Cashier Requests" />

            <div className="mx-auto max-w-7xl space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">
                        Payment Requests
                    </h1>
                    <p className="mt-1 text-sm text-slate-600">
                        Record the official receipt for requests processed by
                        Accounting.
                    </p>
                </div>

                <form
                    onSubmit={filter}
                    className="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row"
                >
                    <div className="relative min-w-0 flex-1">
                        <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Reference, payer, email, or OR number"
                            className="w-full rounded-lg border border-slate-300 py-2 pr-3 pl-9 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        />
                    </div>
                    <select
                        value={status}
                        onChange={(event) =>
                            setStatus(
                                event.target.value as 'processed' | 'paid',
                            )
                        }
                        className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500"
                    >
                        <option value="processed">Ready for payment</option>
                        <option value="paid">Paid</option>
                    </select>
                    <button
                        type="submit"
                        className="rounded-lg bg-blue-700 px-5 py-2 text-sm font-medium text-white hover:bg-blue-800"
                    >
                        Filter
                    </button>
                </form>

                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-slate-50 text-xs tracking-wide text-slate-600 uppercase">
                                <tr>
                                    <th className="px-5 py-3">Reference</th>
                                    <th className="px-5 py-3">Payer</th>
                                    <th className="px-5 py-3">Amount</th>
                                    <th className="px-5 py-3">Status</th>
                                    <th className="px-5 py-3">OR number</th>
                                    <th className="px-5 py-3 text-right">
                                        Action
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {requests.data.map((paymentRequest) => (
                                    <tr key={paymentRequest.id}>
                                        <td className="px-5 py-4 font-medium text-slate-900">
                                            {
                                                paymentRequest.form_input
                                                    .reference_number
                                            }
                                        </td>
                                        <td className="px-5 py-4">
                                            <div className="text-slate-900">
                                                {fullName(
                                                    paymentRequest.form_input,
                                                )}
                                            </div>
                                            <div className="text-xs text-slate-500">
                                                {
                                                    paymentRequest.form_input
                                                        .email
                                                }
                                            </div>
                                        </td>
                                        <td className="px-5 py-4 tabular-nums">
                                            {money.format(
                                                Number(
                                                    paymentRequest.form_input
                                                        .amount,
                                                ),
                                            )}
                                        </td>
                                        <td className="px-5 py-4">
                                            <span
                                                className={`rounded-full px-2.5 py-1 text-xs font-medium ${
                                                    paymentRequest.status ===
                                                    'paid'
                                                        ? 'bg-emerald-100 text-emerald-800'
                                                        : 'bg-amber-100 text-amber-800'
                                                }`}
                                            >
                                                {paymentRequest.status ===
                                                'paid'
                                                    ? 'Paid'
                                                    : 'Ready'}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4 text-slate-700">
                                            {paymentRequest.or_no ?? '—'}
                                        </td>
                                        <td className="px-5 py-4 text-right">
                                            <Link
                                                href={cashier.requests.show.url(
                                                    paymentRequest.id,
                                                )}
                                                className="font-medium text-blue-700 hover:underline"
                                            >
                                                {paymentRequest.status ===
                                                'paid'
                                                    ? 'View'
                                                    : 'Record payment'}
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                                {requests.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-5 py-12 text-center text-slate-500"
                                        >
                                            No matching payment requests.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="flex flex-col gap-3 border-t border-slate-200 px-5 py-4 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between">
                        <span>
                            {requests.total === 0
                                ? 'No results'
                                : `Showing ${requests.from}–${requests.to} of ${requests.total}`}
                        </span>
                        <div className="flex flex-wrap gap-1">
                            {requests.links.map((link, index) => (
                                <button
                                    key={`${link.label}-${index}`}
                                    type="button"
                                    disabled={!link.url}
                                    onClick={() =>
                                        link.url &&
                                        router.get(
                                            link.url,
                                            {},
                                            { preserveState: true },
                                        )
                                    }
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                    className={`rounded-md border px-3 py-1.5 disabled:cursor-not-allowed disabled:opacity-40 ${
                                        link.active
                                            ? 'border-blue-700 bg-blue-700 text-white'
                                            : 'border-slate-300 bg-white hover:bg-slate-50'
                                    }`}
                                />
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
