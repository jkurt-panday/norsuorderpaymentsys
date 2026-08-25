import {
  columnFilteringFeature,
  columnVisibilityFeature,
  createFilteredRowModel,
  createPaginatedRowModel,
  createSortedRowModel,
  filterFn_includesString,
  rowPaginationFeature,
  rowSelectionFeature,
  rowSortingFeature,
  sortFn_alphanumeric,
  sortFn_text,
  tableFeatures,
} from "@tanstack/react-table"

/**
 * Bare-bones feature set: just the core row model (always included).
 * No sorting, filtering, pagination, or selection yet.
 *
 * When you're ready to add those later, register them here ONCE and
 * every table across your app that imports `features` picks them up
 * automatically — you won't touch DataTable.tsx or any columns.tsx file.
 *
 * Example (future):
 *
 * export const features = tableFeatures({
 *   rowSortingFeature,
 *   rowPaginationFeature,
 *   columnFilteringFeature,
 *   sortedRowModel: createSortedRowModel(),
 *   paginatedRowModel: createPaginatedRowModel(),
 *   filteredRowModel: createFilteredRowModel(),
 *   sortFns: { alphanumeric: sortFn_alphanumeric, text: sortFn_text },
 *   filterFns: { includesString: filterFn_includesString },
 * })
 */
 export const features = tableFeatures({
   columnFilteringFeature,
   columnVisibilityFeature,
   rowPaginationFeature,
   rowSelectionFeature,
   rowSortingFeature,
   filteredRowModel: createFilteredRowModel(),
   paginatedRowModel: createPaginatedRowModel(),
   sortedRowModel: createSortedRowModel(),
   filterFns: { includesString: filterFn_includesString },
   sortFns: { alphanumeric: sortFn_alphanumeric, text: sortFn_text },
 })

// Pass this as the first generic arg to ColumnDef/Column/Table/Row so every
// module (columns.tsx files) shares one feature contract.
export type DataTableFeatures = typeof features
