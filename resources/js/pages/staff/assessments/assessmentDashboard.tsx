import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Head } from '@inertiajs/react';

export default function AssessmentDashboard() {
    return (
        <>
            <div className="mx-auto min-h-screen w-full max-w-7xl min-w-0 space-y-4 bg-slate-50 p-3 sm:p-6">
                <Head title="Assessment Dashboard" />
                <Card className="rounded-2xl border border-slate-200/70 bg-white px-2! py-2! shadow-sm">
                    <CardHeader className="flex flex-row items-center justify-between p-3">
                        <CardTitle className="text-xl font-bold text-slate-900">
                            Assessments Dashboard
                        </CardTitle>
                    </CardHeader>
                </Card>
            </div>
        </>
    );
}
