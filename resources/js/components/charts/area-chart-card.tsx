import { useId } from "react"
import { TrendingDown, TrendingUp } from "lucide-react"
import { Area, AreaChart, CartesianGrid, XAxis, YAxis } from "recharts"
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { ChartContainer, ChartTooltip, ChartTooltipContent } from "@/components/ui/chart"
import { buildChartConfig, type ChartSeries } from "./types"

interface AreaChartCardProps {
  title: string
  description?: string
  data: Record<string, string | number>[]
  xAxisKey: string
  series: ChartSeries[]
  className?: string
  height?: string
  /** Stack series on top of each other (like desktop/mobile in the shadcn demo). Set false for a single series. */
  stacked?: boolean
  xAxisTickFormatter?: (value: string) => string
  /** Skip labels so a dense 30-day x-axis doesn't overlap. e.g. 4 shows every 4th tick. */
  xAxisInterval?: number
  trend?: { value: number; label: string }
  footer?: string
  emptyMessage?: string
}

export function AreaChartCard({
  title,
  description,
  data,
  xAxisKey,
  series,
  className,
  height = "min-h-[100px]",
  stacked = true,
  xAxisTickFormatter,
  xAxisInterval,
  trend,
  footer,
  emptyMessage = "No data yet.",
}: AreaChartCardProps) {
  const config = buildChartConfig(series)
  const uid = useId().replace(/[^a-zA-Z0-9]/g, "")

  return (
    <Card className={`rounded-2xl border border-slate-200/70 bg-white shadow-sm ${className ?? ""}`}>
      <CardHeader>
        <CardTitle className="text-base font-semibold text-slate-900">{title}</CardTitle>
        {description && (
          <CardDescription className="text-sm text-slate-500">{description}</CardDescription>
        )}
      </CardHeader>
      <CardContent>
        {data.length === 0 ? (
          <div className={`flex ${height} items-center justify-center text-sm text-slate-400`}>
            {emptyMessage}
          </div>
        ) : (
          <ChartContainer config={config} className={`${height} w-full`}>
              <AreaChart accessibilityLayer data={data} margin={{ left: 12, right: 12 }}>
                <CartesianGrid vertical={false} />
                <XAxis
                  dataKey={xAxisKey}
                  tickLine={false}
                  axisLine={false}
                  tickMargin={8}
                  interval={xAxisInterval}
                  tickFormatter={xAxisTickFormatter}
                />
                <YAxis domain={[0, (dataMax: number) => dataMax + 3]} />
                <ChartTooltip cursor={false} content={<ChartTooltipContent />} />
                <defs>
                  {series.map((s) => (
                    <linearGradient key={s.key} id={`fill-${uid}-${s.key}`} x1="0" y1="0" x2="0" y2="1">
                      <stop offset="5%" stopColor={`var(--color-${s.key})`} stopOpacity={0.8} />
                      <stop offset="95%" stopColor={`var(--color-${s.key})`} stopOpacity={0.1} />
                    </linearGradient>
                  ))}
                </defs>
                {series.map((s) => (
                  <Area
                    key={s.key}
                    dataKey={s.key}
                    type="monotone"
                    fill={`url(#fill-${uid}-${s.key})`}
                    fillOpacity={0.4}
                    stroke={`var(--color-${s.key})`}
                    stackId={stacked ? "a" : s.key}
                  />
                ))}
              </AreaChart>
          </ChartContainer>
        )}
      </CardContent>
      {(trend || footer) && (
        <CardFooter>
          <div className="flex w-full items-start gap-2 text-sm">
            <div className="grid gap-2">
              {/*{trend && (
                <div className="flex items-center gap-2 leading-none font-medium">
                  {trend.label}
                  {trend.value >= 0 ? (
                    <TrendingUp className="h-4 w-4 text-emerald-600" />
                  ) : (
                    <TrendingDown className="h-4 w-4 text-red-600" />
                  )}
                </div>
              )}*/}
              {footer && (
                <div className="flex items-center gap-2 leading-none text-muted-foreground">
                  {footer}
                </div>
              )}
            </div>
          </div>
        </CardFooter>
      )}
    </Card>
  )
}