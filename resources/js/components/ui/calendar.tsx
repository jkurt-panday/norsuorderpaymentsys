"use client"

import { format } from "date-fns"
import { CalendarDaysIcon } from "lucide-react"
import * as React from "react"
import { DayPicker } from "react-day-picker"

import { buttonVariants, Button } from "@/components/ui/button"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import { cn } from "@/lib/utils"

function Calendar({
  className,
  classNames,
  showOutsideDays = true,
  ...props
}: React.ComponentProps<typeof DayPicker>) {
  return (
    <DayPicker
      showOutsideDays={showOutsideDays}
      className={cn("p-3", className)}
      classNames={{
        root: "w-fit",
        months: "flex flex-col sm:flex-row gap-4",
        month: "flex flex-col gap-4",
        month_caption: "flex justify-between items-center px-1",
        caption_label: "text-sm font-medium",
        nav: "flex items-center gap-1",
        button_previous:
          "inline-flex items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground size-7",
        button_next:
          "inline-flex items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground size-7",
        month_grid: "w-full border-collapse space-x-1",
        weekdays: "flex",
        weekday: "text-muted-foreground w-9 text-xs font-medium",
        week: "flex w-full mt-2",
        day: "w-9 h-9 text-sm p-0 rounded-md transition-colors",
        day_button: cn(
          buttonVariants({ variant: "ghost" }),
          "size-9 rounded-md p-0 font-normal aria-selected:opacity-100",
        ),
        ...classNames,
      }}
      modifiersClassNames={{
        selected:
          "bg-primary text-primary-foreground hover:bg-primary hover:text-primary-foreground focus:bg-primary focus:text-primary-foreground",
        today: "bg-accent text-accent-foreground",
        outside: "text-muted-foreground opacity-50",
        disabled: "text-muted-foreground opacity-50",
        range_middle:
          "aria-selected:bg-accent aria-selected:text-accent-foreground",
        range_end:
          "bg-primary text-primary-foreground hover:bg-primary hover:text-primary-foreground",
      }}
      {...props}
    />
  )
}

Calendar.displayName = "Calendar"

function CalendarField({
  value,
  onSelect,
  label,
  placeholder = "Pick a date",
  disabled,
  className,
}: {
  value?: Date | null
  onSelect?: (date?: Date) => void
  label?: string
  placeholder?: string
  disabled?: boolean
  className?: string
}) {
  const [open, setOpen] = React.useState(false)

  const formatted = value ? format(value, "MMM d, yyyy") : null

  return (
    <div className={cn("flex flex-col gap-1", className)}>
      {label && (
        <span className="text-xs font-medium text-muted-foreground">
          {label}
        </span>
      )}
      <Popover open={open} onOpenChange={setOpen}>
        <PopoverTrigger
          render={
            <Button
              variant="outline"
              size="sm"
              disabled={disabled}
              className={cn(
                "justify-start gap-2 font-normal",
                !formatted && "text-muted-foreground",
              )}
            />
          }
        >
          <CalendarDaysIcon className="size-4 text-muted-foreground" />
          {formatted || placeholder}
        </PopoverTrigger>
        <PopoverContent className="w-auto overflow-hidden p-0" align="start">
          <Calendar
            mode="single"
            selected={value ?? undefined}
            onSelect={(date) => {
              onSelect?.(date)
              setOpen(false)
            }}
          />
        </PopoverContent>
      </Popover>
    </div>
  )
}

CalendarField.displayName = "CalendarField"

export { Calendar, CalendarField }
