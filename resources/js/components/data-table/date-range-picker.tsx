'use client';

import * as React from 'react';
import { CalendarIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

interface SingleDatePickerProps {
    label: string;
    value: Date | undefined;
    onChange: (date: Date | undefined) => void;
}

/** One controlled single-date picker — used for both "From" and "To". */
function SingleDatePicker({ label, value, onChange }: SingleDatePickerProps) {
    const [open, setOpen] = React.useState(false);

    return (
        <div className="flex items-center gap-2">
            <span className="text-sm font-medium text-slate-600 whitespace-nowrap">{label}:</span>
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger
                    render={
                        <Button
                            variant="outline"
                            className="justify-start px-2.5 font-normal"
                        >
                            <CalendarIcon className="mr-2 h-4 w-4" />
                            {value ? value.toLocaleDateString() : 'Select date'}
                        </Button>
                    }
                />
                <PopoverContent
                    className="w-auto overflow-hidden p-0"
                    align="start"
                >
                    <Calendar
                        mode="single"
                        selected={value}
                        defaultMonth={value}
                        captionLayout="dropdown"
                        onSelect={(date) => {
                            onChange(date);
                            setOpen(false);
                        }}
                    />
                </PopoverContent>
            </Popover>
        </div>
    );
}

interface DateRangeFilterProps {
    from: Date | undefined;
    to: Date | undefined;
    onFromChange: (date: Date | undefined) => void;
    onToChange: (date: Date | undefined) => void;
    /** Fires when either date changes — the caller decides when to actually apply/fetch. */
    // onApply?: (range: { from: Date | undefined; to: Date | undefined }) => void
}

/**
 * Two independent single-date pickers rather than one two-month range
 * calendar. Better suited to wide, multi-year ranges (e.g. Aug 2020 to
 * Aug 2025) where a range calendar would force navigating two adjacent
 * months that are never both visible at once.
 */
export function DateRangeFilter({
    from,
    to,
    onFromChange,
    onToChange,
}: DateRangeFilterProps) {
    // const handleFromChange = (date: Date | undefined) => {
    //   onFromChange(date)
    //   // Only fire the request once BOTH ends are picked — selecting "From"
    //   // alone would otherwise send a half-finished range to Laravel.
    //   if (date && to) onApply?.({ from: date, to })
    // }

    // const handleToChange = (date: Date | undefined) => {
    //   onToChange(date)
    //   // Same rule in the other direction, in case "To" is picked first.
    //   if (from && date) onApply?.({ from, to: date })
    // }

    return (
        <div className="flex flex-wrap items-center gap-4">
            <SingleDatePicker
                label="From"
                value={from}
                onChange={onFromChange}
            />
            <SingleDatePicker label="To" value={to} onChange={onToChange} />
        </div>
    );
}
