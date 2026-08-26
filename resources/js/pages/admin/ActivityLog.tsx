import { Head, usePage } from '@inertiajs/react';
import { ActivityLogTable } from '@/components/ActivityLogTable';
import type {
    ActivityLogFilters,
    ActivityLogPaginator,
} from '@/components/ActivityLogTable';
import AdminLayout from '@/layouts/admin/layout';

interface AdminActivityLogPageProps {
    [key: string]: unknown;
    activityLogs: ActivityLogPaginator;
    activityFilters: ActivityLogFilters;
}

export default function AdminActivityLogPage() {
    const {
        activityLogs = { data: [] },
        activityFilters = {},
    } = usePage<AdminActivityLogPageProps>().props;

    return (
        <AdminLayout>
            <Head title="Activity Log" />
            <div className="min-h-screen bg-slate-50 px-4 py-8">
                <div className="mx-auto max-w-7xl">
                    <div className="mb-8 flex flex-col gap-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm md:flex-row md:items-center md:justify-between">
                        <div>
                            <h1 className="text-2xl font-semibold text-slate-950">
                                Activity Log
                            </h1>
                            <p className="text-sm text-slate-500">
                                System-wide activity log — all user actions including logins, account creation, and modifications.
                            </p>
                        </div>
                    </div>

                    <ActivityLogTable
                        logs={activityLogs}
                        filters={activityFilters}
                    />
                </div>
            </div>
        </AdminLayout>
    );
}