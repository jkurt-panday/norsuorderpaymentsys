import * as React from "react";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { cn } from "@/lib/utils";
import { Search, Filter, ChevronLeft, ChevronRight } from "lucide-react";
import type { LucideIcon } from "lucide-react";

interface PaginationLink {
  url: string | null;
  label: string;
  active: boolean;
}

/** Shape of a Laravel paginator's JSON */
export interface PaginatedData<T> {
  data: T[];
  links: PaginationLink[];
  current_page: number;
  last_page: number;
  from: number | null;
  to: number | null;
  total: number;
}

export interface ColumnDef<T> {
  header: string;
  width?: string;
  render: (row: T) => React.ReactNode;
  className?: string;
  align?: "left" | "right" | "center";
}

/**
 * Preset status dot colors — pass one of these keys as `StatusOption.color`,
 * or fall back to any raw Tailwind bg-* class if you need something custom.
 *   green  -> approved / active / paid
 *   red    -> rejected / inactive / overdue
 *   orange -> pending / in review
 *   grey   -> default / unknown / draft
 */
export const STATUS_COLORS = {
  green: "bg-emerald-500",
  red: "bg-red-500",
  orange: "bg-amber-500",
  grey: "bg-slate-400",
} as const;

export interface StatusOption {
  value: string;
  label: string;
  /** "green" | "red" | "orange" | "grey", or a raw Tailwind bg-* class */
  color?: keyof typeof STATUS_COLORS | string;
}

const resolveStatusColor = (color?: string) =>
  (color && STATUS_COLORS[color as keyof typeof STATUS_COLORS]) || color || STATUS_COLORS.grey;

interface RequestTableProps<T> {
  title?: string;
  columns: ColumnDef<T>[];
  resource: PaginatedData<T>;

  /** Required — this component assumes bespoke per-row actions (View/Process/Edit, etc.) */
  renderActions: (row: T) => React.ReactNode;
  actionsWidth?: string;

  emptyIcon?: LucideIcon;
  emptyMessage?: string;
  onPageChange?: (url: string) => void;

  // ---- Filter bar (search + status + date range), all controlled from the page ----
  search: string;
  onSearchChange: (value: string) => void;
  searchPlaceholder?: string;

  status: string;
  onStatusChange: (value: string) => void;
  statusOptions: StatusOption[];
  statusPlaceholder?: string;

  dateFrom: string;
  onDateFromChange: (value: string) => void;
  dateTo: string;
  onDateToChange: (value: string) => void;

  onFilterSubmit: (e: React.FormEvent) => void;
  onFilterReset: () => void;
}

const alignClass: Record<NonNullable<ColumnDef<unknown>["align"]>, string> = {
  left: "text-left",
  right: "text-right",
  center: "text-center",
};

export default function RequestTable<T extends { id: number | string }>({
  title,
  columns,
  resource,
  renderActions,
  actionsWidth = "110px",
  emptyIcon: EmptyIcon,
  emptyMessage = "No records found",
  onPageChange,
  search,
  onSearchChange,
  searchPlaceholder = "Search...",
  status,
  onStatusChange,
  statusOptions,
  statusPlaceholder = "All Status",
  dateFrom,
  onDateFromChange,
  dateTo,
  onDateToChange,
  onFilterSubmit,
  onFilterReset,
}: RequestTableProps<T>) {
  const rows = resource.data;
  // Show the summary/pagination bar whenever there's at least one row —
  // a single-page result (e.g. 2 records) still shows
  // "Showing 1 to 2 of 2 results" this way.
  const showPagination = rows.length > 0;

  const selectedStatus = statusOptions.find((opt) => opt.value === status);

  return (
    <div className="mx-auto max-w-7xl space-y-4 p-4 sm:p-6">
      {/* ---- Title, sits above the search/filter section ---- */}
      {(title || resource.total !== undefined) && (
        <div className="flex items-center justify-between">
          {title && <h2 className="text-lg font-semibold text-slate-900">{title}</h2>}
          {resource.total !== undefined && (
            <span className="text-sm text-slate-500">
              {resource.total.toLocaleString()} total
            </span>
          )}
        </div>
      )}

      {/* ---- Filter bar: search + status + date range ---- */}
      <Card>
        <CardContent className="p-4">
          <form
            onSubmit={onFilterSubmit}
            className="flex flex-nowrap items-end gap-3 overflow-x-auto pb-1"
          >
            <div className="flex min-w-[240px] flex-1 flex-col gap-1.5">
              <label htmlFor="rt-search" className="text-xs font-medium text-slate-600">
                Search
              </label>
              <div className="relative">
                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <Input
                  id="rt-search"
                  placeholder={searchPlaceholder}
                  value={search}
                  onChange={(e) => onSearchChange(e.target.value)}
                  className="rounded-full pl-9"
                />
              </div>
            </div>

            <div className="flex shrink-0 flex-col gap-1.5">
              <label className="text-xs font-medium text-slate-600">Status</label>
              <Select
                value={status || "all"}
                onValueChange={(v) => onStatusChange(v === "all" ? "" : v)}
              >
                <SelectTrigger className="w-[160px] shrink-0 rounded-full">
                  <SelectValue placeholder={statusPlaceholder}>
                    {status && selectedStatus ? (
                      <span className="flex items-center gap-2">
                        <span
                          className={cn(
                            "h-2 w-2 shrink-0 rounded-full",
                            resolveStatusColor(selectedStatus.color)
                          )}
                        />
                        {selectedStatus.label}
                      </span>
                    ) : (
                      statusPlaceholder
                    )}
                  </SelectValue>
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">{statusPlaceholder}</SelectItem>
                  {statusOptions.map((opt) => (
                    <SelectItem key={opt.value} value={opt.value}>
                      <span className="flex items-center gap-2">
                        <span
                          className={cn(
                            "h-2 w-2 shrink-0 rounded-full",
                            resolveStatusColor(opt.color)
                          )}
                        />
                        {opt.label}
                      </span>
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="flex shrink-0 flex-col gap-1.5">
              <label htmlFor="rt-date-from" className="text-xs font-medium text-slate-600">
                Date From
              </label>
              <Input
                id="rt-date-from"
                type="date"
                value={dateFrom}
                onChange={(e) => onDateFromChange(e.target.value)}
                className="w-[160px] shrink-0 rounded-full"
              />
            </div>

            <div className="flex shrink-0 flex-col gap-1.5">
              <label htmlFor="rt-date-to" className="text-xs font-medium text-slate-600">
                Date To
              </label>
              <Input
                id="rt-date-to"
                type="date"
                value={dateTo}
                onChange={(e) => onDateToChange(e.target.value)}
                className="w-[160px] shrink-0 rounded-full"
              />
            </div>

            <Button type="submit" className="shrink-0 rounded-full bg-blue-900 text-white hover:bg-blue-950">
              <Filter className="h-4 w-4" />
              Filter
            </Button>

            <Button
              type="button"
              variant="outline"
              className="shrink-0 rounded-full"
              onClick={onFilterReset}
            >
              Reset
            </Button>
          </form>
        </CardContent>
      </Card>

      {/* ---- Table ---- */}
      <Card className="overflow-hidden py-0">
        <CardContent className="overflow-x-auto p-0">
          <Table>
            <TableHeader>
              <TableRow className="border-b border-slate-200 hover:bg-transparent">
                {columns.map((col, i) => (
                  <TableHead
                    key={i}
                    style={col.width ? { width: col.width } : undefined}
                    className={cn(
                      "h-11 whitespace-nowrap bg-slate-50/80 text-xs font-semibold uppercase tracking-wide text-slate-500",
                      i === 0 && "pl-6",
                      col.align && alignClass[col.align],
                      col.className
                    )}
                  >
                    {col.header}
                  </TableHead>
                ))}
                <TableHead
                  style={{ width: actionsWidth }}
                  className="h-11 bg-slate-50/80 pr-6 text-right text-xs font-semibold uppercase tracking-wide text-slate-500"
                >
                  Actions
                </TableHead>
              </TableRow>
            </TableHeader>

            <TableBody>
              {rows.length === 0 ? (
                <TableRow className="hover:bg-transparent">
                  <TableCell colSpan={columns.length + 1} className="h-56">
                    <div className="flex flex-col items-center justify-center gap-2 text-slate-400">
                      {EmptyIcon && <EmptyIcon className="h-8 w-8" strokeWidth={1.5} />}
                      <p className="text-sm font-medium text-slate-500">{emptyMessage}</p>
                    </div>
                  </TableCell>
                </TableRow>
              ) : (
                rows.map((row, i) => (
                  <TableRow
                    key={row.id}
                    className={cn(
                      "border-b border-slate-100 transition-colors last:border-0 hover:bg-slate-50/70",
                      i % 2 === 1 && "bg-slate-50/30"
                    )}
                  >
                    {columns.map((col, j) => (
                      <TableCell
                        key={j}
                        style={col.width ? { width: col.width } : undefined}
                        className={cn(
                          "py-3 text-sm text-slate-700",
                          j === 0 && "pl-6",
                          col.align && alignClass[col.align],
                          col.className
                        )}
                      >
                        {col.render(row)}
                      </TableCell>
                    ))}
                    <TableCell style={{ width: actionsWidth }} className="py-3 pr-6 text-right">
                      {renderActions(row)}
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </CardContent>

        {showPagination && (
          <div className="flex items-center justify-between border-t border-slate-200 px-5 py-3">
            <p className="text-sm text-slate-500">
              Showing {resource.from ?? 0} to {resource.to ?? 0} of {resource.total} results
            </p>
            <div className="flex items-center gap-1">
              {resource.links.map((link, i) => {
                const rawLabel = link.label.replace(/&laquo;|&raquo;/g, "").trim();
                const isPrev = rawLabel.toLowerCase() === "previous";
                const isNext = rawLabel.toLowerCase() === "next";

                return (
                  <Button
                    key={i}
                    type="button"
                    size="icon"
                    variant={link.active ? "default" : "outline"}
                    disabled={!link.url}
                    onClick={() => link.url && onPageChange?.(link.url)}
                    aria-label={isPrev ? "Previous page" : isNext ? "Next page" : rawLabel}
                    className={cn(
                      "h-8 w-8 rounded-md text-sm",
                      link.active && "bg-blue-900 text-white hover:bg-blue-950"
                    )}
                  >
                    {isPrev ? (
                      <ChevronLeft className="h-4 w-4" />
                    ) : isNext ? (
                      <ChevronRight className="h-4 w-4" />
                    ) : (
                      rawLabel
                    )}
                  </Button>
                );
              })}
            </div>
          </div>
        )}
      </Card>
    </div>
  );
}