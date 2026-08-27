"use client"

import { Button } from "@/components/ui/button"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuLabel,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"

interface ColumnFilterOption {
  label: string
  value: string
}

interface ColumnFilterDropdownProps {
  /** Shown on the trigger button, e.g. "Course", "Semester". */
  label: string
  /** Currently selected value — pass the STAGED value from ServerDataTable's `values`. */
  value: string | undefined
  /** Called with the new value, or undefined when "All" is chosen. */
  onChange: (value: string | undefined) => void
  /** The choices for this one column. */
  options: ColumnFilterOption[]
  /** Label for the "no filter" option. Defaults to "All". */
  allLabel?: string
}

const ALL = "__all__" // sentinel — Base UI radio items can't use an empty string

/**
 * A single, compact filter dropdown for ONE column.
 *
 * This replaces the earlier version where all four Assessments filters
 * (course, enrolled_under, sy_last_attended, semester) were grouped into
 * one large DropdownMenu with multiple DropdownMenuGroup sections. That
 * approach didn't scale visually — the combined dropdown grew tall and wide
 * as more filterable columns were added.
 *
 * This component is intentionally generic (no Assessments-specific naming
 * or types) so it lives in components/data-table/ rather than a page
 * folder — ANY column, on ANY ServerDataTable-based page, can render one
 * of these just by passing its own label/value/options/onChange. Multiple
 * instances sit side by side as small buttons instead of one large menu.
 */
export function ColumnFilterDropdown({
  label,
  value,
  onChange,
  options,
  allLabel = "All",
}: ColumnFilterDropdownProps) {
  const selected = options.find((option) => option.value === value)

  return (
    <DropdownMenu>
      <DropdownMenuTrigger
        render={
          <Button variant="outline" size="sm" className="h-10 rounded-xl font-normal">
            {label}
            {selected ? ` (1)` : ""}
          </Button>
        }
      />
      <DropdownMenuContent align="start" className="w-48">
        <DropdownMenuGroup>
          <DropdownMenuLabel>{label}</DropdownMenuLabel>
          <DropdownMenuRadioGroup
            value={value ?? ALL}
            onValueChange={(next) => onChange(next === ALL ? undefined : next)}
          >
            <DropdownMenuRadioItem value={ALL}>{allLabel}</DropdownMenuRadioItem>
            {options.map((option) => (
              <DropdownMenuRadioItem key={option.value} value={option.value}>
                {option.label}
              </DropdownMenuRadioItem>
            ))}
          </DropdownMenuRadioGroup>
        </DropdownMenuGroup>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}