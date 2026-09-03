import { Plus, Pencil, Trash2, CheckCircle2, Activity } from 'lucide-react';
import React from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Legend,
    Line,
    LineChart,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export function getDayLabel(dateString?: string | null): string | null {
    if (!dateString) {
return null;
}

    const date = new Date(dateString);
    const today = new Date();
    date.setHours(0, 0, 0, 0);
    today.setHours(0, 0, 0, 0);

    const diffDays = Math.round((today.getTime() - date.getTime()) / 86400000);

    if (diffDays <= 0) {
return 'Today';
}

    if (diffDays === 1) {
return '1 day ago';
}

    return `${diffDays} days ago`;
}

/* ============================================================
   1. StatCard — single number metric
   ============================================================ */
export interface StatCardProps {
    label: string;
    value: number | string;
    bgClass?: string;
    textClass?: string;
    icon?: React.ReactNode;
    subtitle?: string | null;
}

export function StatCard({
    label,
    value,
    bgClass = 'bg-slate-900',
    textClass = 'text-white',
    icon,
    subtitle,
}: StatCardProps) {
    return (
        <div className={`rounded-3xl p-6 shadow-sm ${bgClass} ${textClass}`}>
            <div className="flex items-start justify-between">
                <p className="text-sm tracking-[0.2em] uppercase opacity-70">
                    {label}
                </p>
                {icon && <span className="opacity-80">{icon}</span>}
            </div>
            <p className="mt-4 text-3xl font-semibold">{value}</p>
            {subtitle && <p className="mt-1 text-xs opacity-60">{subtitle}</p>}
        </div>
    );
}

/* ============================================================
   2. ChartCard — bar / line / pie chart via Recharts
   ============================================================ */
export interface ChartCardProps {
    title: string;
    description?: string;
    type: 'bar' | 'line' | 'pie';
    data: Record<string, string | number>[];
    xKey: string;
    yKey: string;
    color?: string;
    height?: number;
    pieColors?: string[];
}

const DEFAULT_PIE_COLORS = [
    '#0d6efd',
    '#0dcaf0',
    '#ffc107',
    '#198754',
    '#dc3545',
    '#6f42c1',
];

// Custom dot renderer for the line chart — only draws a visible dot where
// the value is greater than 0, so long flat (zero) stretches read as
// intentionally quiet instead of being cluttered with dots at zero.
function renderLineDot(yKey: string, color: string) {
    return (props: {
        cx?: number;
        cy?: number;
        payload?: Record<string, string | number>;
    }) => {
        const { cx, cy, payload } = props;

        if (cx === undefined || cy === undefined || !payload) {
return <></>;
}

        const value = Number(payload[yKey] ?? 0);

        if (value <= 0) {
return <></>;
}

        return <circle cx={cx} cy={cy} r={3} fill={color} />;
    };
}

export function ChartCard({
    title,
    description,
    type,
    data,
    xKey,
    yKey,
    color = '#0d6efd',
    height = 280,
    pieColors = DEFAULT_PIE_COLORS,
}: ChartCardProps) {
    const pieTotal =
        type === 'pie'
            ? data.reduce((sum, d) => sum + Number(d[yKey] ?? 0), 0)
            : 0;

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base font-semibold text-slate-900">
                    {title}
                </CardTitle>
                {description && (
                    <p className="text-sm text-slate-500">{description}</p>
                )}
            </CardHeader>
            <CardContent>
                <div style={{ width: '100%', height, position: 'relative' }}>
                    <div className="relative z-10 h-full w-full">
                        <ResponsiveContainer>
                            {type === 'bar' ? (
                                <BarChart
                                    data={data}
                                    layout="vertical"
                                    margin={{ left: -10, right: 50 }}
                                >
                                    <CartesianGrid
                                        strokeDasharray="3 3"
                                        stroke="#e2e8f0"
                                    />
                                    <XAxis
                                        type="number"
                                        tick={{ fontSize: 12, fill: '#64748b' }}
                                        allowDecimals={false}
                                        domain={[
                                            0,
                                            (dataMax: number) =>
                                                Math.ceil(dataMax * 1.2),
                                        ]}
                                    />
                                    <YAxis
                                        type="category"
                                        dataKey={xKey}
                                        tick={{ fontSize: 11, fill: '#64748b' }}
                                        width={100}
                                    />
                                    <Tooltip
                                        contentStyle={{
                                            borderRadius: 8,
                                            border: '1px solid #e2e8f0',
                                            fontSize: 12,
                                        }}
                                    />
                                    <Bar
                                        dataKey={yKey}
                                        fill={color}
                                        radius={[0, 6, 6, 0]}
                                        barSize={28}
                                    />
                                </BarChart>
                            ) : type === 'line' ? (
                                <LineChart data={data}>
                                    <CartesianGrid
                                        strokeDasharray="3 3"
                                        stroke="#e2e8f0"
                                    />
                                    <XAxis
                                        dataKey={xKey}
                                        tick={{ fontSize: 12, fill: '#64748b' }}
                                    />
                                    <YAxis
                                        tick={{ fontSize: 12, fill: '#64748b' }}
                                        allowDecimals={false}
                                    />
                                    <Tooltip
                                        contentStyle={{
                                            borderRadius: 8,
                                            border: '1px solid #e2e8f0',
                                            fontSize: 12,
                                        }}
                                        labelFormatter={(label) =>
                                            new Date(String(label)).toLocaleDateString(
                                                'en-US',
                                                {
                                                    month: 'short',
                                                    day: 'numeric',
                                                },
                                            )
                                        }
                                    />
                                    <Line
                                        type="monotone"
                                        dataKey={yKey}
                                        stroke={color}
                                        strokeWidth={2.5}
                                        dot={renderLineDot(yKey, color)}
                                    />
                                </LineChart>
                            ) : (
                                <PieChart>
                                    <Tooltip
                                        contentStyle={{
                                            borderRadius: 8,
                                            border: '1px solid #e2e8f0',
                                            fontSize: 12,
                                        }}
                                    />
                                    <Legend
                                        verticalAlign="bottom"
                                        height={36}
                                        iconType="circle"
                                        wrapperStyle={{ fontSize: 12 }}
                                    />
                                    <Pie
                                        data={data}
                                        dataKey={yKey}
                                        nameKey={xKey}
                                        innerRadius={height / 5}
                                        outerRadius={height / 2.6}
                                        paddingAngle={2}
                                    >
                                        {data.map((_, index) => (
                                            <Cell
                                                key={index}
                                                fill={
                                                    pieColors[
                                                        index % pieColors.length
                                                    ]
                                                }
                                            />
                                        ))}
                                    </Pie>
                                </PieChart>
                            )}
                        </ResponsiveContainer>
                    </div>
                    {type === 'pie' && (
                        <div className="pointer-events-none absolute inset-0 z-0 flex flex-col items-center justify-center pb-9">
                            <span className="text-2xl font-bold text-slate-900">
                                {pieTotal}
                            </span>
                            <span className="text-xs text-slate-500">
                                Total
                            </span>
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

/* ============================================================
   3. ActivityLogList — timeline of created/updated/deleted/processed events
   ============================================================ */
export interface ActivityLogItem {
    id: number;
    event: string;
    description: string;
    causerName: string | null;
    createdAt: string;
}

export interface ActivityLogListProps {
    title?: string;
    logs: ActivityLogItem[];
    emptyMessage?: string;
    limit?: number;
}

const EVENT_CONFIG: Record<
    string,
    { icon: React.ElementType; badgeClass: string; label: string }
> = {
    created: {
        icon: Plus,
        badgeClass: 'bg-emerald-100 text-emerald-800',
        label: 'Created',
    },
    updated: {
        icon: Pencil,
        badgeClass: 'bg-amber-100 text-amber-900',
        label: 'Updated',
    },
    deleted: {
        icon: Trash2,
        badgeClass: 'bg-rose-100 text-rose-800',
        label: 'Deleted',
    },
    processed: {
        icon: CheckCircle2,
        badgeClass: 'bg-blue-100 text-blue-800',
        label: 'Processed',
    },
};

const getEventConfig = (event: string) =>
    EVENT_CONFIG[event] ?? {
        icon: Activity,
        badgeClass: 'bg-slate-100 text-slate-700',
        label: event,
    };

const formatRelativeOrDate = (dateString: string): string => {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.round(diffMs / 60000);

    if (diffMins < 1) {
        return 'Just now';
    }

    if (diffMins < 60) {
        return `${diffMins}m ago`;
    }

    const diffHours = Math.round(diffMins / 60);

    if (diffHours < 24) {
        return `${diffHours}h ago`;
    }

    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

export interface ActivityLogListProps {
    title?: string;
    logs: ActivityLogItem[];
    emptyMessage?: string;
    limit?: number;
    maxVisible?: number; // item count before scrolling kicks in
}

export function ActivityLogList({
    title = 'Activity Log',
    logs,
    emptyMessage = 'No activity yet.',
    limit,
    maxVisible = 10,
}: ActivityLogListProps) {
    const visibleLogs = limit ? logs.slice(0, limit) : logs;
    const shouldScroll = visibleLogs.length > maxVisible;

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base font-semibold text-slate-900">
                    {title}
                </CardTitle>
            </CardHeader>
            <CardContent>
                {visibleLogs.length === 0 ? (
                    <p className="py-6 text-center text-sm text-slate-500">
                        {emptyMessage}
                    </p>
                ) : (
                    <ul
                        className={`space-y-4 ${shouldScroll ? 'max-h-96 overflow-y-auto pr-2' : ''}`}
                    >
                        {visibleLogs.map((log) => {
                            const {
                                icon: Icon,
                                badgeClass,
                                label,
                            } = getEventConfig(log.event);

                            return (
                                <li
                                    key={log.id}
                                    className="flex items-start gap-3"
                                >
                                    <span
                                        className={`mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full ${badgeClass}`}
                                    >
                                        <Icon className="h-4 w-4" />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Badge
                                                variant="secondary"
                                                className={badgeClass}
                                            >
                                                {label}
                                            </Badge>
                                            <span className="text-xs text-slate-400">
                                                {formatRelativeOrDate(
                                                    log.createdAt,
                                                )}
                                            </span>
                                        </div>
                                        <p className="mt-1 text-sm text-slate-700">
                                            {log.description}
                                        </p>
                                        {log.causerName && (
                                            <p className="mt-0.5 text-xs text-slate-400">
                                                by {log.causerName}
                                            </p>
                                        )}
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
