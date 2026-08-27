import { type ChartConfig } from "@/components/ui/chart"

export interface ChartSeries {
  key: string
  label: string
  color?: string
}

export interface CategoryDatum {
  category: string
    value: number
    color?: string
}

function slugifyKey(value: string): string {
  return (
    value
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9]+/g, "_")
      .replace(/^_+|_+$/g, "") || "value"
  )
}

const PALETTE = [
  "var(--chart-1)",
  "var(--chart-2)",
  "var(--chart-3)",
  "var(--chart-4)",
  "var(--chart-5)",
]

/**
 * Turns arbitrary {category, value} rows into the {key, fill} shape Recharts/
 * shadcn's chart config expects, since — unlike "desktop"/"mobile" — our
 * category names come from the database and aren't known ahead of time.
 */
 export function buildCategoryChartData(data: CategoryDatum[]) {
   const config: ChartConfig = {}
   const chartData = data.map((d, i) => {
     const key = slugifyKey(d.category)
     config[key] = { label: d.category, color: d.color ?? PALETTE[i % PALETTE.length] }
     return { ...d, key, fill: `var(--color-${key})` }
   })
   return { chartData, config }
 }

export function buildChartConfig(series: ChartSeries[]): ChartConfig {
  return series.reduce((config, s, i) => {
    config[s.key] = {
      label: s.label,
      color: s.color ?? PALETTE[i % PALETTE.length],
    }
    return config
  }, {} as ChartConfig)
}