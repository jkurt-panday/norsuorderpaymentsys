import { CalendarIcon } from 'lucide-react';
import * as React from 'react';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

interface DatePickerProps {
    value: string;
    onChange: (value: string) => void;
    invalid?: boolean;
    placeholder?: string;
    className?: string;
    maxDate?: string;
}

const ISO_DATE_PATTERN = /^\d{4}-\d{2}-\d{2}$/;

const toDisplayDate = (iso: string): string => {
    if (!ISO_DATE_PATTERN.test(iso)) {
        return '';
    }

    const [year, month, day] = iso.split('-');

    return `${day}/${month}/${year}`;
};

const toDateValue = (iso: string): Date | undefined => {
    if (!ISO_DATE_PATTERN.test(iso)) {
        return undefined;
    }

    const [year, month, day] = iso.split('-').map(Number);

    return new Date(year, month - 1, day);
};

const fromDateValue = (date: Date): string => {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
};

function DatePicker({
    value,
    onChange,
    invalid = false,
    placeholder = 'dd/mm/yyyy',
    className,
    maxDate,
}: DatePickerProps) {
    const [open, setOpen] = React.useState(false);
    const selected = toDateValue(value);
    const display = toDisplayDate(value);
    const maxDateValue = maxDate ? toDateValue(maxDate) : undefined;

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger
                render={
                    <button
                        type="button"
                        className={cn(
                            'flex w-full items-center justify-between gap-2 rounded-xl border px-4 py-2 text-sm text-slate-700 outline-none',
                            invalid ? 'border-rose-400' : 'border-slate-200',
                            className,
                        )}
                    >
                        <span className={display ? '' : 'text-slate-400'}>
                            {display || placeholder}
                        </span>
                        <CalendarIcon className="h-4 w-4 shrink-0 text-slate-400" />
                    </button>
                }
            />
            <PopoverContent
                className="w-auto overflow-hidden p-0"
                align="start"
            >
                <Calendar
                    mode="single"
                    selected={selected}
                    defaultMonth={selected}
                    captionLayout="dropdown"
                    disabled={maxDateValue ? [{ after: maxDateValue }] : undefined}
                    onSelect={(date) => {
                        onChange(date ? fromDateValue(date) : '');

                        setOpen(false);
                    }}
                />
            </PopoverContent>
        </Popover>
    );
}

export { DatePicker };
