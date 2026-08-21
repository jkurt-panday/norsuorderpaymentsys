import { useState } from "react"
import { router } from "@inertiajs/react"
import { useTable, type ColumnDef, type RowData } from "@tanstack/react-table"
import { ArrowUpDown, ArrowUp, ArrowDown, Search } from "lucide-react"

import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { Input } from "@/components/ui/input"
import { Button } from "@/components/ui/button"

import { features, type DataTableFeatures } from "./data-table-features"

interface ServerFilters {
  search: string
  sort: string
  direction: "asc" | "desc"
}

interface ServerPagination {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

interface ServerDataTableProps<TData extends RowData> {
  columns: ColumnDef<DataTableFeatures, TData>[]
  data: TData[]
  /** The route search/sort/pagination requests are sent to, e.g. "/staff/courses". */
  route: string
  /** Whatever Laravel sent back as `filters` — reflects the CURRENT state of the query. */
  filters: ServerFilters
  /** The Laravel paginator's meta fields (courses.current_page, .last_page, etc). */
  pagination: ServerPagination
  /** Column ids that are allowed to be clicked for sorting — should mirror your backend whitelist. */
  sortableColumns?: string[]
  emptyMessage?: string
}

/**
 * For large datasets. `data` is only ever the CURRENT PAGE — search, sort,
 * and pagination all round-trip to Laravel via router.get, which is the
 * single source of truth for ordering/filtering/paging.
 */
export function ServerDataTable<TData extends RowData>({
  columns,
  data,
  route,
  filters,
  pagination,
  sortableColumns = [],
  emptyMessage = "No results.",
}: ServerDataTableProps<TData>) {
  const [search, setSearch] = useState(filters.search)

  const visit = (params: Record<string, string | number | undefined>) => {
    router.get(
      route,
      { search: filters.search, sort: filters.sort, direction: filters.direction, ...params },
      { preserveState: true, preserveScroll: true, replace: true }
    )
  }

  const runSearch = () => visit({ search, page: 1 }) // reset to page 1 on a new search

  const toggleSort = (columnId: string) => {
    const isSameColumn = filters.sort === columnId
    const nextDirection = isSameColumn && filters.direction === "asc" ? "desc" : "asc"
    visit({ sort: columnId, direction: nextDirection })
  }

  const table = useTable({
    features,
    data,
    columns,
    manualSorting: true,
    manualPagination: true,
  })

  return (
    <div>
      <div className="flex items-center gap-2 py-4">
        <Input
          placeholder="Search..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          onKeyDown={(e) => e.key === "Enter" && runSearch()}
          className="max-w-sm"
        />
        <Button onClick={runSearch} variant="outline" size="icon">
          <Search className="h-4 w-4" />
        </Button>
      </div>

      <div className="overflow-hidden rounded-md border">
        <Table>
          <TableHeader>
            {table.getHeaderGroups().map((headerGroup) => (
              <TableRow key={headerGroup.id}>
                {headerGroup.headers.map((header) => {
                  const canSort = sortableColumns.includes(header.column.id)
                  const isActive = filters.sort === header.column.id

                  return (
                    <TableHead key={header.id}>
                      {header.isPlaceholder ? null : canSort ? (
                        <Button
                          variant="ghost"
                          size="sm"
                          className="-ml-3"
                          onClick={() => toggleSort(header.column.id)}
                        >
                          <table.FlexRender header={header} />
                          {isActive && filters.direction === "asc" ? (
                            <ArrowUp className="ml-2 h-4 w-4" />
                          ) : isActive && filters.direction === "desc" ? (
                            <ArrowDown className="ml-2 h-4 w-4" />
                          ) : (
                            <ArrowUpDown className="ml-2 h-4 w-4 opacity-40" />
                          )}
                        </Button>
                      ) : (
                        <table.FlexRender header={header} />
                      )}
                    </TableHead>
                  )
                })}
              </TableRow>
            ))}
          </TableHeader>
          <TableBody>
            {table.getRowModel().rows?.length ? (
              table.getRowModel().rows.map((row) => (
                <TableRow key={row.id}>
                  {row.getVisibleCells().map((cell) => (
                    <TableCell key={cell.id}>
                      <table.FlexRender cell={cell} />
                    </TableCell>
                  ))}
                </TableRow>
              ))
            ) : (
              <TableRow>
                <TableCell colSpan={columns.length} className="h-24 text-center">
                  {emptyMessage}
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </div>

      <div className="flex items-center justify-between py-4">
        <div className="text-sm text-muted-foreground">
          Page {pagination.current_page} of {pagination.last_page} ({pagination.total} total)
        </div>
        <div className="flex gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={() => visit({ page: pagination.current_page - 1 })}
            disabled={pagination.current_page <= 1}
          >
            Previous
          </Button>
          <Button
            variant="outline"
            size="sm"
            onClick={() => visit({ page: pagination.current_page + 1 })}
            disabled={pagination.current_page >= pagination.last_page}
          >
            Next
          </Button>
        </div>
      </div>
    </div>
  )
}
