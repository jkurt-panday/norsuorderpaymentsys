import { CartesianGrid, Line, LineChart, XAxis } from "recharts"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import {
  ChartContainer,
  ChartLegend,
  ChartLegendContent,
  ChartTooltip,
  ChartTooltipContent,
} from "@/components/ui/chart"
import { buildChartConfig, type ChartSeries } from "./types"

interface LineChartCardProps {
  title: string
  description?: string
  data: Record<string, string | number>[]
  xAxisKey: string
  series: ChartSeries[]
  className?: string
  height?: string
  showLegend?: boolean
  emptyMessage?: string
}

export function LineChartCard({
  title,
  description,
  data,
  xAxisKey,
  series,
  className,
  height = "min-h-[240px]",
  showLegend = series.length > 1,
  emptyMessage = "No data yet.",
}: LineChartCardProps) {
  const config = buildChartConfig(series)

  return (
    <Card className={`rounded-2xl border border-slate-200/70 bg-white shadow-sm ${className ?? ""}`}>
      <CardHeader className="pb-2">
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
            <LineChart accessibilityLayer data={data}>
              <CartesianGrid vertical={false} />
              <XAxis dataKey={xAxisKey} tickLine={false} axisLine={false} tickMargin={10} />
              <ChartTooltip content={<ChartTooltipContent />} />
              {showLegend && <ChartLegend content={<ChartLegendContent />} />}
              {series.map((s) => (
                <Line
                  key={s.key}
                  type="monotone"
                  dataKey={s.key}
                  stroke={`var(--color-${s.key})`}
                  strokeWidth={2}
                  dot={false}
                />
              ))}
            </LineChart>
          </ChartContainer>
        )}
      </CardContent>
    </Card>
  )
}