import { Head, usePage } from '@inertiajs/react';
import { ActivityLogTable } from '@/components/ActivityLogTable';
import type {
    ActivityLogFilters,
    ActivityLogPaginator,
} from '@/components/ActivityLogTable';
import { CardTitle } from '@/components/ui/card';

interface AdminActivityLogPageProps {
    [key: string]: unknown;
    activityLogs: ActivityLogPaginator;
    activityFilters: ActivityLogFilters;
}

export default function AdminActivityLogPage() {
    const { activityLogs = { data: [] }, activityFilters = {} } =
        usePage<AdminActivityLogPageProps>().props;

    return (
        <>
            <Head title="Activity Log" />
            <div className="min-h-screen bg-page-background px-4 py-8 text-foreground transition-colors">
                <div className="mx-auto max-w-7xl">
                    <div className="mb-8 flex flex-col gap-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition-colors md:flex-row md:items-center md:justify-between dark:border-slate-800 dark:bg-slate-900/80">
                        <div>
                            <CardTitle className="bg-linear-to-r from-[#3399FF] to-[#1677d2] bg-clip-text font-['Poppins'] text-3xl font-black tracking-tight text-transparent sm:text-4xl dark:from-sky-400 dark:to-cyan-300">
                                Activity Log
                            </CardTitle>
                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                System-wide activity log — all user actions
                                including logins, account creation, and
                                modifications.
                            </p>
                        </div>
                    </div>

                    <ActivityLogTable
                        logs={activityLogs}
                        filters={activityFilters}
                    />
                </div>
            </div>
        </>
    );
}
