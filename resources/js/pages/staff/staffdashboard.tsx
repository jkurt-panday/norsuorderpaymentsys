import React from 'react';
import { Link, Head, usePage } from '@inertiajs/react';
import { StatCard, ChartCard, ActivityLogList, type ActivityLogItem } from '@/components/Reusable';

interface RecentRequest {
    id: number;
    reference_number: string;
    full_name: string;
    created_at: string;
    staffInput?: {
        status: string;
    } | null;
}

interface ChartDatum {
    [key: string]: string | number;
}

interface DashboardPageProps {
    [key: string]: unknown;
    totalRequests?: number;
    pendingRequests?: number;
    approvedRequests?: number;
    cancelledRequests?: number;
    statusBreakdown?: ChartDatum[];
    requestsOverTime?: ChartDatum[];
    requestsByMembership?: ChartDatum[];
    recentRequests?: RecentRequest[];
    recentActivity?: ActivityLogItem[];
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
            return 'bg-slate-100 text-slate-800';
    }
};

const formatDate = (value?: string | null) => {

    if (!value)
        
        {
        return '-'
        };

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
};

export default function Dashboard() {
    const {
        totalRequests = 0,
        pendingRequests = 0,
        approvedRequests = 0,
        cancelledRequests = 0,
        statusBreakdown = [],
        requestsOverTime = [],
        requestsByMembership = [],
        recentRequests = [],
        recentActivity = [],
    } = usePage<DashboardPageProps>().props;

    return (
        <div className="min-h-screen bg-slate-50 px-4 py-8">
            <Head title="Dashboard" />
            <div className="mx-auto max-w-7xl">
                <div className="mb-8 flex flex-col gap-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-slate-950">Dashboard</h1>
                        <p className="text-sm text-slate-500">Staff dashboard overview for requests and activity.</p>
                    </div>
                    <div className="text-right text-sm text-slate-500 md:text-base">
                        <p>Total Requests: <span className="font-semibold text-slate-900">{totalRequests}</span></p>
                    </div>
                </div>

                {/* Stat cards */}
                <div className="grid gap-4 md:grid-cols-4">
                    <StatCard label="Total Requests" value={totalRequests} bgClass="bg-slate-900" textClass="text-white" />
                    <StatCard label="Pending" value={pendingRequests} bgClass="bg-amber-100" textClass="text-slate-950" />
                    <StatCard label="Approved" value={approvedRequests} bgClass="bg-emerald-100" textClass="text-slate-950" />
                    <StatCard label="Cancelled" value={cancelledRequests} bgClass="bg-rose-100" textClass="text-slate-950" />
                </div>

                {/* Charts */}
                <div className="mt-6 grid gap-4 lg:grid-cols-3">
                    <ChartCard
                        title="Requests by Status"
                        type="pie"
                        data={statusBreakdown}
                        xKey="name"
                        yKey="count"
                    />
                    <div className="lg:col-span-2">
                        <ChartCard
                            title="Requests Over the Last 30 Days"
                            type="line"
                            data={requestsOverTime}
                            xKey="date"
                            yKey="count"
                        />
                    </div>
                </div>

                <div className="mt-4">
                    <ChartCard
                        title="Requests by Membership"
                        type="bar"
                        data={requestsByMembership}
                        xKey="name"
                        yKey="count"
                        height={240}
                    />
                </div>

                {/* Recent Requests + Activity Log */}
                <div className="mt-6 grid gap-4 lg:grid-cols-2">
                    <section className="rounded-3xl border border-slate-200 bg-white shadow-sm">
                        <div className="flex items-center gap-2 border-b border-slate-200 px-6 py-4">
                            <h2 className="text-base font-semibold text-slate-900">Recent Requests</h2>
                        </div>
                        <div className="p-6">
                            {recentRequests.length > 0 ? (
                                <div className="overflow-x-auto">
                                    <table className="min-w-full text-left text-sm text-slate-700">
                                        <thead>
                                            <tr>
                                                <th className="pb-3 font-medium text-slate-900">Reference</th>
                                                <th className="pb-3 font-medium text-slate-900">Name</th>
                                                <th className="pb-3 font-medium text-slate-900">Status</th>
                                                <th className="pb-3 font-medium text-slate-900">Date</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-200">
                                            {recentRequests.map((request) => (
                                                <tr key={request.id}>
                                                    <td className="py-3">
                                                        <span className="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">
                                                            {request.reference_number}
                                                        </span>
                                                    </td>
                                                    <td className="py-3 text-slate-800">{request.full_name}</td>
                                                    <td className="py-3">
                                                        <span
                                                            className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${statusBadgeClass(request.staffInput?.status)}`}
                                                        >
                                                            {request.staffInput?.status
                                                                ? request.staffInput.status.charAt(0).toUpperCase() +
                                                                  request.staffInput.status.slice(1)
                                                                : 'Unprocessed'}
                                                        </span>
                                                    </td>
                                                    <td className="py-3 text-slate-600">{formatDate(request.created_at)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <p className="text-center text-sm text-slate-500">No recent requests</p>
                            )}
                        </div>
                    </section>

                    <ActivityLogList title="Recent Activity" logs={recentActivity} limit={8} />
                </div>
            </div>
        </div>
    );
}
