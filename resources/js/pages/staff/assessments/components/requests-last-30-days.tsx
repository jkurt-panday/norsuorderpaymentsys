'use client';

import { CartesianGrid, Line, LineChart, XAxis } from 'recharts';

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
    ChartTooltip,
    ChartTooltipContent,
    type ChartConfig,
} from '@/components/ui/chart';

interface RequestData {
    date: string;
    requests: number;
}

interface RequestsLast30DaysProps {
    data: RequestData[];
}

const chartConfig = {
    requests: {
        label: 'Requests',
        color: 'var(--chart-1)',
    },
} satisfies ChartConfig;

export function RequestsLast30Days({ data }: RequestsLast30DaysProps) {
    const totalRequests = data.reduce(
        (total, item) => total + item.requests,
        0,
    );

    return (
        <Card>
            <CardHeader>
                <CardTitle>Assessment Requests</CardTitle>

                <CardDescription>
                    Requests submitted over the past 30 days
                </CardDescription>
            </CardHeader>

            <CardContent>
                <ChartContainer
                    config={chartConfig}
                    className="min-h-62.5 w-full"
                >
                    <LineChart
                        accessibilityLayer
                        data={data}
                        margin={{
                            left: 12,
                            right: 12,
                        }}
                    >
                        <CartesianGrid vertical={false} />

                        <XAxis
                            dataKey="date"
                            tickLine={false}
                            axisLine={false}
                            tickMargin={8}
                            tickFormatter={(value) => {
                                const date = new Date(`${value}T00:00:00`);

                                return date.toLocaleDateString('en-PH', {
                                    month: 'short',
                                    day: 'numeric',
                                });
                            }}
                        />

                        <ChartTooltip
                            cursor={false}
                            content={<ChartTooltipContent hideLabel />}
                        />

                        <Line
                            dataKey="requests"
                            type="natural"
                            stroke="var(--color-requests)"
                            strokeWidth={2}
                            dot={{
                                fill: 'var(--color-requests)',
                            }}
                            activeDot={{
                                r: 6,
                            }}
                        />
                    </LineChart>
                </ChartContainer>
            </CardContent>

            <CardFooter className="flex-col items-start gap-2 text-sm">
                <div className="leading-none font-medium">
                    {totalRequests} total requests
                </div>

                <div className="leading-none text-muted-foreground">
                    Showing assessment requests from the past 30 days
                </div>
            </CardFooter>
        </Card>
    );
}
