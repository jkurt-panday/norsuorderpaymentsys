import { Head } from '@inertiajs/react';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { BarChartCard } from '@/components/charts/bar-chart-card';
import { LineChartCard } from '@/components/charts/line-chart-card';
import { AreaChartCard } from '@/components/charts/area-chart-card';
import { DonutChartCard } from '@/components/charts/donut-chart-card';

interface AssessmentDashboardProps {
    byCourse: { course: string; total: number }[];
    bySemester: { semester: string; total: number }[];
    byEnrolledUnder: { enrolled_under: string; total: number }[];
    monthlyTrend: { month: string; total: number }[];
    dailyRequests: { date: string; total: number }[];
    requestsTrend: { value: number; recent: number; previous: number };
}

export default function AssessmentDashboard({
    byCourse,
    bySemester,
    byEnrolledUnder,
    monthlyTrend,
    dailyRequests,
    requestsTrend,
}: AssessmentDashboardProps) {
    return (
        <div className="mx-auto min-h-screen w-full max-w-7xl min-w-0 space-y-4 bg-slate-50 p-3 sm:p-6">
            <Head title="Dashboard Assessment" />

            <Card className="rounded-2xl border border-slate-200/70 bg-white px-2! py-2! shadow-sm">
                <CardHeader className="flex flex-row items-center justify-between p-3">
                    <CardTitle className="text-xl font-bold text-slate-900">
                        Assessments Dashboard
                    </CardTitle>
                </CardHeader>
            </Card>

            <AreaChartCard
                title="Assessment Requests"
                description="Assessment requests over the last 30 days"
                data={dailyRequests}
                xAxisKey="date"
                series={[{ key: 'total', label: 'Requests', color: '#007FFF' }]}
                height="h-50"
                stacked={false}
                xAxisInterval={4}
                trend={{
                    value: requestsTrend.value,
                    label: `${requestsTrend.value >= 0 ? 'Trending up' : 'Trending down'} by ${Math.abs(requestsTrend.value)}% vs the prior 15 days`,
                }}
                footer={`${dailyRequests[0]?.date} – ${dailyRequests[dailyRequests.length - 1]?.date}`}
            />

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <DonutChartCard
                    title="Assessments by Semester"
                    data={bySemester.map((d) => ({
                        category: d.semester,
                        value: d.total,
                        color:
                            d.semester === 'First Semester'
                                ? '#1A8CFF'
                                : d.semester === 'Second Semester'
                                  ? '#4DA5FF'
                                  : '#99CCFF', // Summer
                    }))}
                    centerLabel="Total"
                />
                <DonutChartCard
                    title="Assessments by Enrollment Type"
                    data={byEnrolledUnder.map((d) => ({
                        category: d.enrolled_under,
                        value: d.total,
                        color:
                            d.enrolled_under === 'Graduate'
                                ? '#1A8CFF'
                                : d.enrolled_under === 'School of Law'
                                  ? '#4DA5FF'
                                  : '#99CCFF', // Summer
                    }))}
                    centerLabel="Total"
                />

                <LineChartCard
                    title="Monthly Trend"
                    description="Last 6 months"
                    data={monthlyTrend}
                    xAxisKey="month"
                    series={[{ key: 'total', label: 'Assessments' }]}
                    height="h-50"
                    className="lg:col-span-2"
                />
                <BarChartCard
                    title="Assessments by Course"
                    data={byCourse}
                    xAxisKey="course"
                    series={[{ key: 'total', label: 'Assessments', color: '#007FFF' }]}
                    height="h-50"
                    className="lg:col-span-2"
                />
            </div>
        </div>
    );
}

{
    /*<BarChartCard
    title="Assessments by Semester"
    data={bySemester}
    xAxisKey="semester"
    series={[{ key: 'total', label: 'Assessments' }]}
/>*/
}
{
    /*<BarChartCard
    title="Assessments by Enrollment Type"
    data={byEnrolledUnder}
    xAxisKey="enrolled_under"
    series={[{ key: 'total', label: 'Assessments' }]}
/>*/
}
