import { Head, usePage } from '@inertiajs/react';
import { Users, Shield, User, BarChart3 } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AdminLayout from '@/layouts/admin/layout';

interface AdminDashboardProps {
    [key: string]: unknown;
    stats: {
        staffCount: number;
        adminCount: number;
        clientCount: number;
    };
    recentActivity: Array<{
        id: number;
        action: string;
        event: string;
        description: string;
        actor_name: string | null;
        actor_role: string | null;
        type: string;
        created_at: string;
    }>;
}

export default function AdminDashboardPage() {
    const {
        stats = { staffCount: 0, adminCount: 0, clientCount: 0 },
        recentActivity = [],
    } = usePage<AdminDashboardProps>().props;

    function formatDateTime(value?: string | null) {
        if (!value) {
return '-';
}

        const date = new Date(value);

        if (Number.isNaN(date.getTime())) {
return value;
}

        return date.toLocaleString('en-US', {
            month: 'short',
            day: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    const roleBadgeClass = (role: string | null) => {
        switch ((role ?? '').toLowerCase()) {
            case 'admin':
                return 'bg-emerald-100 text-emerald-800';
            case 'staff':
                return 'bg-blue-100 text-blue-800';
            case 'client':
                return 'bg-slate-100 text-slate-800';
            default:
                return 'bg-slate-100 text-slate-800';
        }
    };

    return (
        <AdminLayout>
            <Head title="Admin Dashboard" />
            <div className="min-h-screen bg-slate-50 px-4 py-8">
                <div className="mx-auto max-w-7xl">
                    <div className="mb-8">
                        <h1 className="text-2xl font-semibold text-slate-950">
                            Admin Dashboard
                        </h1>
                        <p className="text-sm text-slate-500">
                            System overview and user statistics.
                        </p>
                    </div>

                    {/* Stats Cards */}
                    <div className="mb-8 grid gap-6 md:grid-cols-3">
                        <Card className="border-slate-200 bg-white shadow-sm transition-shadow hover:shadow-md">
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium text-slate-500">
                                    Staff Users
                                </CardTitle>
                                <Users className="h-6 w-6 text-slate-400" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-3xl font-bold text-slate-950">
                                    {stats.staffCount}
                                </div>
                                <p className="text-xs text-slate-500">
                                    Active staff accounts
                                </p>
                            </CardContent>
                        </Card>

                        <Card className="border-slate-200 bg-white shadow-sm transition-shadow hover:shadow-md">
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium text-slate-500">
                                    Administrators
                                </CardTitle>
                                <Shield className="h-6 w-6 text-slate-400" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-3xl font-bold text-slate-950">
                                    {stats.adminCount}
                                </div>
                                <p className="text-xs text-slate-500">
                                    System administrators
                                </p>
                            </CardContent>
                        </Card>

                        <Card className="border-slate-200 bg-white shadow-sm transition-shadow hover:shadow-md">
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium text-slate-500">
                                    Client Users
                                </CardTitle>
                                <User className="h-6 w-6 text-slate-400" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-3xl font-bold text-slate-950">
                                    {stats.clientCount}
                                </div>
                                <p className="text-xs text-slate-500">
                                    Registered clients
                                </p>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Recent Activity */}
                    <Card className="border-slate-200 bg-white shadow-sm">
                        <CardHeader className="border-b border-slate-200">
                            <CardTitle className="text-lg font-semibold text-slate-950">
                                Recent System Activity
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b border-slate-200 bg-slate-50">
                                            <th className="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase">
                                                Action
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase">
                                                Description
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase">
                                                Actor
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase">
                                                Type
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase">
                                                Date
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-200">
                                        {recentActivity.length === 0 ? (
                                            <tr>
                                                <td
                                                    colSpan={5}
                                                    className="px-6 py-10 text-center text-sm text-slate-500"
                                                >
                                                    No recent activity.
                                                </td>
                                            </tr>
                                        ) : (
                                            recentActivity
                                                .slice(0, 10)
                                                .map((log) => (
                                                    <tr
                                                        key={log.id}
                                                        className="hover:bg-slate-50"
                                                    >
                                                        <td className="px-6 py-4 whitespace-nowrap">
                                                            <span
                                                                className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                                                    log.event ===
                                                                    'created'
                                                                        ? 'bg-emerald-100 text-emerald-800'
                                                                        : log.event ===
                                                                            'updated'
                                                                          ? 'bg-amber-100 text-amber-800'
                                                                          : log.event ===
                                                                              'deleted'
                                                                            ? 'bg-rose-100 text-rose-800'
                                                                            : 'bg-blue-100 text-blue-800'
                                                                }`}
                                                            >
                                                                {log.event
                                                                    .charAt(0)
                                                                    .toUpperCase() +
                                                                    log.event.slice(
                                                                        1,
                                                                    )}
                                                            </span>
                                                        </td>
                                                        <td className="max-w-md truncate px-6 py-4 text-sm text-slate-700">
                                                            {log.description}
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap">
                                                            <div className="flex items-center gap-2">
                                                                <span className="font-medium text-slate-900">
                                                                    {log.actor_name ??
                                                                        'System'}
                                                                </span>
                                                                {log.actor_role && (
                                                                    <span
                                                                        className={`rounded-full px-2 py-0.5 text-xs font-medium ${roleBadgeClass(log.actor_role)}`}
                                                                    >
                                                                        {
                                                                            log.actor_role
                                                                        }
                                                                    </span>
                                                                )}
                                                            </div>
                                                        </td>
                                                        <td className="px-6 py-4 text-sm whitespace-nowrap text-slate-600">
                                                            {log.type}
                                                        </td>
                                                        <td className="px-6 py-4 text-sm whitespace-nowrap text-slate-500">
                                                            {formatDateTime(
                                                                log.created_at,
                                                            )}
                                                        </td>
                                                    </tr>
                                                ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                            {recentActivity.length > 0 && (
                                <div className="mt-4 text-right">
                                    <a
                                        href="/admin/activity-log"
                                        className="text-sm font-medium text-blue-600 hover:text-blue-700"
                                    >
                                        View all activity →
                                    </a>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AdminLayout>
    );
}
