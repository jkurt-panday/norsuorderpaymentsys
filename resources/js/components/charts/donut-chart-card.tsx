import { useMemo } from 'react';
import { Label, Pie, PieChart } from 'recharts';
import { buildCategoryChartData } from './types';
import type { CategoryDatum } from './types';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';

interface DonutChartCardProps {
    title: string;
    description?: string;
    data: CategoryDatum[];
    /** Label under the total in the center, e.g. "Assessments". */
    centerLabel?: string;
    className?: string;
    size?: string;
    showLegend?: boolean;
    footer?: string;
    emptyMessage?: string;
}

export function DonutChartCard({
    title,
    description,
    data,
    centerLabel = 'Total',
    className,
    size = 'max-h-[250px]',
    showLegend = true,
    footer,
    emptyMessage = 'No data yet.',
}: DonutChartCardProps) {
    const { chartData, config } = useMemo(
        () => buildCategoryChartData(data),
        [data],
    );
    const total = useMemo(
        () => data.reduce((sum, d) => sum + d.value, 0),
        [data],
    );

    return (
        <Card
            className={`flex flex-col rounded-2xl border border-border bg-card shadow-sm ${className ?? ''}`}
        >
            <CardHeader className="items-center pb-0">
                <CardTitle className="text-base font-semibold text-card-foreground">
                    {title}
                </CardTitle>
                {description && (
                    <CardDescription className="text-sm text-muted-foreground">
                        {description}
                    </CardDescription>
                )}
            </CardHeader>
            <CardContent className="flex min-h-[320px] flex-1 items-center justify-center pb-0">
                {data.length === 0 ? (
                    <div
                        className={`flex ${size} items-center justify-center text-sm text-muted-foreground`}
                    >
                        {emptyMessage}
                    </div>
                ) : (
                    <ChartContainer
                        config={config}
                        className={`mx-auto aspect-square h-full w-full ${size}`}
                    >
                        <PieChart width={320} height={320}>
                            <ChartTooltip
                                cursor={false}
                                content={
                                    <ChartTooltipContent
                                        hideLabel
                                        nameKey="key"
                                    />
                                }
                            />
                            <Pie
                                data={chartData}
                                dataKey="value"
                                nameKey="key"
                                innerRadius="50%"
                                outerRadius="90%"
                                strokeWidth={5}
                            >
                                <Label
                                    content={({ viewBox }) => {
                                        if (
                                            viewBox &&
                                            'cx' in viewBox &&
                                            'cy' in viewBox
                                        ) {
                                            return (
                                                <text
                                                    x={viewBox.cx}
                                                    y={viewBox.cy}
                                                    textAnchor="middle"
                                                    dominantBaseline="middle"
                                                >
                                                    <tspan
                                                        x={viewBox.cx}
                                                        dy="-0.1em"
                                                        className="fill-foreground text-2xl font-bold"
                                                    >
                                                        {total.toLocaleString()}
                                                    </tspan>
                                                    <tspan
                                                        x={viewBox.cx}
                                                        dy="1.6em"
                                                        className="fill-muted-foreground text-xs"
                                                    >
                                                        {centerLabel}
                                                    </tspan>
                                                </text>
                                            );
                                        }

                                        return null;
                                    }}
                                />
                            </Pie>
                            {showLegend && (
                                <ChartLegend
                                    content={
                                        <ChartLegendContent nameKey="key" />
                                    }
                                />
                            )}
                        </PieChart>
                    </ChartContainer>
                )}
            </CardContent>
            {footer && (
                <CardFooter className="flex-col gap-2 text-sm">
                    <div className="leading-none text-muted-foreground">
                        {footer}
                    </div>
                </CardFooter>
            )}
        </Card>
    );
}
