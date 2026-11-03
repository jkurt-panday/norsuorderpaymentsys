import { Head, usePage } from '@inertiajs/react';
import { ActivityLogTable } from '@/components/ActivityLogTable';
import type {
    ActivityLogFilters,
    ActivityLogPaginator,
} from '@/components/ActivityLogTable';
import { CardTitle } from '@/components/ui/card';

interface ActivityLogPageProps {
    [key: string]: unknown;
    activityLogs: ActivityLogPaginator;
    activityFilters: ActivityLogFilters;
}

export default function ActivityLogPage() {
    const { activityLogs = { data: [] }, activityFilters = {} } =
        usePage<ActivityLogPageProps>().props;

    return (
        <div className="min-h-screen bg-page-background px-4 py-8 text-foreground transition-colors">
            <Head title="Activity Log" />
            <div className="mx-auto max-w-7xl">
                <div className="mb-8 flex flex-col gap-4 rounded-3xl border border-border bg-card p-6 shadow-sm md:flex-row md:items-center md:justify-between">
                    <div>
                        <CardTitle className="bg-linear-to-r from-[#3399FF] to-[#1677d2] bg-clip-text font-['Poppins'] text-3xl font-black tracking-tight text-transparent sm:text-4xl dark:from-sky-400 dark:to-cyan-300">
                            Activity Log
                        </CardTitle>
                        <p className="text-sm text-muted-foreground">
                            Every change across requests, orders of payment, and
                            settings — who did it and what changed.
                        </p>
                    </div>
                </div>

                <ActivityLogTable
                    logs={activityLogs}
                    filters={activityFilters}
                />
            </div>
        </div>
    );
}
