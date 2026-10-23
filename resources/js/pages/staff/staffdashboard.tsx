import { Head, usePage, usePoll } from '@inertiajs/react';
import React from 'react';
import {
    StatCard,
    ChartCard,
    ActivityLogList,
    getDayLabel,
} from '@/components/Reusable';
import type { ActivityLogItem } from '@/components/Reusable';
import { CardTitle } from '@/components/ui/card';

interface RecentRequest {
    id: number;
    reference_number: string;
    full_name: string;
    created_at: string;
    staff_input?: {
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
    processedRequests?: number;
    paidRequests?: number;
    cancelledRequests?: number;
    unprocessedRequests?: number;
    totalRequestsLastDate?: string | null;
    pendingLastDate?: string | null;
    processedLastDate?: string | null;
    paidLastDate?: string | null;
    cancelledLastDate?: string | null;
    unprocessedLastDate?: string | null;
    statusBreakdown?: ChartDatum[];
    requestsOverTime?: ChartDatum[];
    requestsByMembership?: ChartDatum[];
    recentRequests?: RecentRequest[];
    recentActivity?: ActivityLogItem[];
}

const statusBadgeClass = (status?: string) => {
    switch (status) {
        case 'approved':
            return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border dark:border-emerald-800/40';
        case 'cancelled':
            return 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 dark:border dark:border-rose-800/40';
        case 'pending':
            return 'bg-amber-100 text-amber-900 dark:bg-amber-950/60 dark:text-amber-300 dark:border dark:border-amber-800/40';
        default:
            return 'bg-slate-100 text-slate-800 dark:bg-slate-800/60 dark:text-slate-300 dark:border dark:border-slate-700/40';
    }
};

const formatDate = (value?: string | null) => {
    if (!value) {
        return '-';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
    });
};

export default function Dashboard() {
    // Polls the server every minute and re-fetches this page's props,
    // re-rendering automatically with fresh data — no full page reload,
    // no manual refresh needed. `only` limits the refetch to just the
    // data this dashboard needs, instead of every shared prop.
    usePoll(60000, {
        only: [
            'totalRequests',
            'pendingRequests',
            'processedRequests',
            'paidRequests',
            'cancelledRequests',
            'unprocessedRequests',
            'totalRequestsLastDate',
            'pendingLastDate',
            'processedLastDate',
            'paidLastDate',
            'cancelledLastDate',
            'statusBreakdown',
            'requestsOverTime',
            'requestsByMembership',
            'recentRequests',
            'recentActivity',
        ],
    });

    const {
        totalRequests = 0,
        pendingRequests = 0,
        processedRequests = 0,
        paidRequests = 0,
        cancelledRequests = 0,
        unprocessedRequests = 0,
        totalRequestsLastDate = null,
        pendingLastDate = null,
        processedLastDate = null,
        paidLastDate = null,
        cancelledLastDate = null,
        unprocessedLastDate = null,
        statusBreakdown = [],
        requestsOverTime = [],
        requestsByMembership = [],
        recentRequests: allRecentRequests = [],
        recentActivity = [],
    } = usePage<DashboardPageProps>().props;

    // Only show requests created today — anything before or after today is excluded.
    const todayStr = new Date().toDateString();
    const recentRequests = allRecentRequests.filter(
        (request) => new Date(request.created_at).toDateString() === todayStr,
    );

    return (
        <div className="min-h-screen bg-page-background px-4 py-8">
            <Head title="Dashboard" />
            <div className="mx-auto max-w-7xl">
                <div className="mb-8 flex flex-col gap-4 rounded-3xl border border-border bg-card p-6 shadow-sm md:flex-row md:items-center md:justify-between">
                    <div>
                        <CardTitle className="bg-[#3399FF] bg-clip-text font-['Poppins'] text-3xl font-black tracking-tight text-transparent sm:text-4xl">
                            Dashboard
                        </CardTitle>
                        <p className="text-sm text-muted-foreground">
                            Staff dashboard overview for requests and activity.
                        </p>
                    </div>
                    <div className="text-right text-sm text-muted-foreground md:text-base">
                        <p>
                            Total Requests:{' '}
                            <span className="font-semibold text-foreground">
                                {totalRequests}
                            </span>
                        </p>
                    </div>
                </div>

                {/* Stat cards */}
                <div className="grid gap-5 md:grid-cols-6">
                    <StatCard
                        label="Total Requests"
                        value={totalRequests}
                        bgClass="bg-card border border-border"
                        textClass="text-foreground"
                        subtitle={getDayLabel(totalRequestsLastDate)}
                    />
                    <StatCard
                        label="Pending"
                        value={pendingRequests}
                        bgClass="bg-amber-500/10 border border-amber-500/20"
                        textClass="text-amber-600 dark:text-amber-400"
                        subtitle={getDayLabel(pendingLastDate)}
                    />
                    <StatCard
                        label="Processed"
                        value={processedRequests}
                        bgClass="bg-emerald-500/10 border border-emerald-500/20"
                        textClass="text-emerald-600 dark:text-emerald-400"
                        subtitle={getDayLabel(processedLastDate)}
                    />
                    <StatCard
                        label="Paid"
                        value={paidRequests}
                        bgClass="bg-blue-500/10 border border-blue-500/20"
                        textClass="text-blue-600 dark:text-blue-400"
                        subtitle={getDayLabel(paidLastDate)}
                    />
                    <StatCard
                        label="Cancelled"
                        value={cancelledRequests}
                        bgClass="bg-rose-500/10 border border-rose-500/20"
                        textClass="text-rose-600 dark:text-rose-400"
                        subtitle={getDayLabel(cancelledLastDate)}
                    />
                    <StatCard
                        label="Unprocessed"
                        value={unprocessedRequests}
                        bgClass="bg-slate-500/10 border border-slate-500/20"
                        textClass="text-slate-600 dark:text-slate-300"
                        subtitle={getDayLabel(unprocessedLastDate)}
                    />
                </div>

                {/* Charts */}
                <div className="mt-6 grid gap-4 lg:grid-cols-3">
                    <ChartCard
                        title="OP Requests by Status"
                        description="Current breakdown of all requests"
                        type="pie"
                        data={statusBreakdown}
                        xKey="name"
                        yKey="count"
                        pieColors={['#94a3b8', '#f59e0b', '#3b82f6', '#ef4444', '#22c55e']}
                    />
                    <div className="lg:col-span-2">
                        <ChartCard
                            title="OP Requests Over the Last 30 Days"
                            description="Daily request volume, rolling window"
                            type="line"
                            data={requestsOverTime}
                            xKey="date"
                            yKey="count"
                        />
                    </div>
                </div>

                <div className="mt-4">
                    <ChartCard
                        title="OP Requests by Membership"
                        description="Total requests submitted per membership type"
                        type="bar"
                        data={requestsByMembership}
                        xKey="name"
                        yKey="count"
                        height={240}
                    />
                </div>

                {/* Recent Requests + Activity Log */}
                <div className="mt-6 grid gap-4 lg:grid-cols-2">
                    <section className="rounded-3xl border border-border bg-card shadow-sm">
                        <div className="flex items-center gap-2 border-b border-border px-6 py-4">
                            <h2 className="text-base font-semibold text-foreground">
                                Recent Requests
                            </h2>
                        </div>
                        <div className="p-4">
                            {recentRequests.length > 0 ? (
                                <div className="max-h-96 overflow-y-auto">
                                    <table className="w-full table-fixed text-left text-sm text-foreground">
                                        <thead className="sticky top-0 z-10 border-b border-border bg-card">
                                            <tr>
                                                <th className="w-[25%] pb-3 font-medium text-muted-foreground">
                                                    Reference
                                                </th>
                                                <th className="w-[25%] pb-3 font-medium text-muted-foreground">
                                                    Name
                                                </th>
                                                <th className="w-[20%] pb-3 font-medium text-muted-foreground">
                                                    Status
                                                </th>
                                                <th className="w-[20%] pb-3 font-medium text-muted-foreground">
                                                    Date
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-border">
                                            {recentRequests.map((request) => (
                                                <tr key={request.id}>
                                                    <td className="py-3">
                                                        <span className="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-medium whitespace-nowrap text-slate-700 dark:border dark:border-slate-700/40 dark:bg-slate-800/60 dark:text-slate-300">
                                                            {
                                                                request.reference_number
                                                            }
                                                        </span>
                                                    </td>
                                                    <td className="py-3 text-foreground">
                                                        {request.full_name}
                                                    </td>
                                                    <td className="py-3">
                                                        <span
                                                            className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${statusBadgeClass(request.staff_input?.status)}`}
                                                        >
                                                            {request.staff_input
                                                                ?.status
                                                                ? request.staff_input.status
                                                                      .charAt(0)
                                                                      .toUpperCase() +
                                                                  request.staff_input.status.slice(
                                                                      1,
                                                                  )
                                                                : 'Unprocessed'}
                                                        </span>
                                                    </td>
                                                    <td className="py-3 text-muted-foreground">
                                                        {formatDate(
                                                            request.created_at,
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <p className="text-center text-sm text-muted-foreground">
                                    No recent requests
                                </p>
                            )}
                        </div>
                    </section>

                    <ActivityLogList
                        title="Recent OP Activity"
                        logs={recentActivity}
                        limit={20}
                        maxVisible={10}
                    />
                </div>
            </div>
        </div>
    );
}
