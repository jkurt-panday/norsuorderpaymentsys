import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from "recharts"
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

interface BarChartCardProps {
  title: string
  description?: string
  data: Record<string, string | number>[]
  xAxisKey: string
  series: ChartSeries[]
  className?: string
  height?: string
  /** Renders bars horizontally — good for long category labels like course codes. */
  horizontal?: boolean
  showLegend?: boolean
  emptyMessage?: string
}

export function BarChartCard({
  title,
  description,
  data,
  xAxisKey,
  series,
  className,
  height = "min-h-[240px]",
  horizontal = false,
  showLegend = series.length > 1,
  emptyMessage = "No data yet.",
}: BarChartCardProps) {
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
            <BarChart accessibilityLayer data={data} layout={horizontal ? "vertical" : "horizontal"}>
              <CartesianGrid vertical={false} horizontal={!horizontal} />
              {horizontal ? (
                <>
                  <YAxis
                    dataKey={xAxisKey}
                    type="category"
                    tickLine={false}
                    axisLine={false}
                    tickMargin={8}
                    width={120}
                  />
                  <XAxis type="number" hide />
                </>
              ) : (
                <XAxis dataKey={xAxisKey} tickLine={false} axisLine={false} tickMargin={10} />
              )}
              <ChartTooltip content={<ChartTooltipContent />} />
              {showLegend && <ChartLegend content={<ChartLegendContent />} />}
              {series.map((s) => (
                <Bar key={s.key} dataKey={s.key} fill={`var(--color-${s.key})`} radius={4} />
              ))}
            </BarChart>
          </ChartContainer>
        )}
      </CardContent>
    </Card>
  )
}