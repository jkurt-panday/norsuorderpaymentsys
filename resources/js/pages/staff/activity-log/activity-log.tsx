import { Head, usePage } from '@inertiajs/react';
import { ActivityLogTable } from '@/components/ActivityLogTable';
import type {
    ActivityLogFilters,
    ActivityLogPaginator,
} from '@/components/ActivityLogTable';

interface ActivityLogPageProps {
    [key: string]: unknown;
    activityLogs: ActivityLogPaginator;
    activityFilters: ActivityLogFilters;
}

export default function ActivityLogPage() {
    const {
        activityLogs = { data: [] },
        activityFilters = {},
    } = usePage<ActivityLogPageProps>().props;

    return (
        <div className="min-h-screen bg-slate-50 px-4 py-8">
            <Head title="Activity Log" />
            <div className="mx-auto max-w-7xl">
                <div className="mb-8 flex flex-col gap-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-slate-950">
                            Activity Log
                        </h1>
                        <p className="text-sm text-slate-500">
                            Every change across requests, orders of payment,
                            and settings — who did it and what changed.
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
