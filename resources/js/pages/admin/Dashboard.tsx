import { Head, usePage } from '@inertiajs/react';
import { Users, Shield, User } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';

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
                return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border dark:border-emerald-800/40';
            case 'staff':
                return 'bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300 dark:border dark:border-purple-800/40';
            case 'cashier':
                return 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 dark:border dark:border-amber-800/40';
            case 'client':
            default:
                return 'bg-slate-100 text-slate-800 dark:bg-slate-800/60 dark:text-slate-300 dark:border dark:border-slate-700/40';
        }
    };

    return (
        <>
            <Head title="Admin Dashboard" />
            <div className="min-h-screen bg-page-background px-4 py-8">
                <div className="mx-auto max-w-7xl">
                    <div className="mb-8">
                        <CardTitle className="bg-[#3399FF] bg-clip-text font-['Poppins'] text-3xl font-black tracking-tight text-transparent sm:text-4xl">
                            Admin Dashboard
                        </CardTitle>
                        <p className="text-sm text-muted-foreground">
                            System overview and user statistics.
                        </p>
                    </div>

                    {/* Stats Cards */}
                    <div className="mb-8 grid gap-6 md:grid-cols-3">
                        <Card className="border-border bg-card shadow-sm transition-shadow hover:shadow-md">
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium text-muted-foreground">
                                    Staff Users
                                </CardTitle>
                                <Users className="h-6 w-6 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-3xl font-bold text-foreground">
                                    {stats.staffCount}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Active staff accounts
                                </p>
                            </CardContent>
                        </Card>

                        <Card className="border-border bg-card shadow-sm transition-shadow hover:shadow-md">
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium text-muted-foreground">
                                    Administrators
                                </CardTitle>
                                <Shield className="h-6 w-6 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-3xl font-bold text-foreground">
                                    {stats.adminCount}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    System administrators
                                </p>
                            </CardContent>
                        </Card>

                        <Card className="border-border bg-card shadow-sm transition-shadow hover:shadow-md">
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium text-muted-foreground">
                                    Client Users
                                </CardTitle>
                                <User className="h-6 w-6 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-3xl font-bold text-foreground">
                                    {stats.clientCount}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Registered clients
                                </p>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Recent Activity */}
                    <Card className="border-border bg-card shadow-sm">
                        <CardHeader className="border-b border-border">
                            <CardTitle className="text-lg font-semibold text-foreground">
                                Recent System Activity
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b border-border bg-muted/50">
                                            <th className="px-6 py-3 text-left text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                                Action
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                                Description
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                                Actor
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                                Type
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                                Date
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {recentActivity.length === 0 ? (
                                            <tr>
                                                <td
                                                    colSpan={5}
                                                    className="px-6 py-10 text-center text-sm text-muted-foreground"
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
                                                        className="hover:bg-muted/50"
                                                    >
                                                        <td className="px-6 py-4 whitespace-nowrap">
                                                            <span
                                                                className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                                                    log.event ===
                                                                    'created'
                                                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border dark:border-emerald-800/40'
                                                                        : log.event ===
                                                                            'updated'
                                                                          ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 dark:border dark:border-amber-800/40'
                                                                          : log.event ===
                                                                              'deleted'
                                                                            ? 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 dark:border dark:border-rose-800/40'
                                                                            : 'bg-blue-100 text-blue-800 dark:bg-sky-950/60 dark:text-sky-300 dark:border dark:border-sky-800/40'
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
                                                        <td className="max-w-md truncate px-6 py-4 text-sm text-foreground">
                                                            {log.description}
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap">
                                                            <div className="flex items-center gap-2">
                                                                <span className="font-medium text-foreground">
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
                                                        <td className="px-6 py-4 text-sm whitespace-nowrap text-muted-foreground">
                                                            {log.type}
                                                        </td>
                                                        <td className="px-6 py-4 text-sm whitespace-nowrap text-muted-foreground">
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
                                <div className="mt-4 mr-4 text-right">
                                    <Button variant={'outline'}>
                                        <a
                                            href="/admin/activity-log"
                                            className="text-sm font-medium text-primary hover:text-primary/90"
                                        >
                                            View all activity →
                                        </a>
                                    </Button>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
