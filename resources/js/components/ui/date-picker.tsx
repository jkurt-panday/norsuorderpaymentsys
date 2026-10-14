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
    valueFormat?: 'display' | 'iso' | 'auto';
}

const DISPLAY_DATE_PATTERN = /^\d{2}\/\d{2}\/\d{4}$/;
const ISO_DATE_PATTERN = /^\d{4}-\d{2}-\d{2}$/;

const toDisplayDate = (iso: string): string => {
    if (!ISO_DATE_PATTERN.test(iso)) return '';
    const [year, month, day] = iso.split('-');
    return `${day}/${month}/${year}`;
};

const toIsoDate = (display: string): string => {
    if (!DISPLAY_DATE_PATTERN.test(display)) return '';
    const [day, month, year] = display.split('/');
    const date = new Date(Number(year), Number(month) - 1, Number(day));
    if (date.getFullYear() !== Number(year) || date.getMonth() !== Number(month) - 1 || date.getDate() !== Number(day)) return '';
    return `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
};

const normalizeToDisplay = (value: string, format: 'display' | 'iso' | 'auto'): string => {
    if (!value) return '';
    if (format === 'display') return DISPLAY_DATE_PATTERN.test(value) ? value : '';
    if (format === 'iso') return toDisplayDate(value);
    if (ISO_DATE_PATTERN.test(value)) return toDisplayDate(value);
    if (DISPLAY_DATE_PATTERN.test(value)) return value;
    return '';
};

const toDateValue = (display: string): Date | undefined => {
    const iso = toIsoDate(display);
    if (!iso) return undefined;
    const [year, month, day] = iso.split('-').map(Number);
    return new Date(year, month - 1, day);
};

const fromDateValue = (date: Date): string => {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${day}/${month}/${date.getFullYear()}`;
};

const formatForOnChange = (display: string, format: 'display' | 'iso' | 'auto'): string => {
    if (format === 'iso') return toIsoDate(display);
    return display;
};

function DatePicker({
    value,
    onChange,
    invalid = false,
    placeholder = 'dd/mm/yyyy',
    className,
    maxDate,
    valueFormat = 'display',
}: DatePickerProps) {
    const [open, setOpen] = React.useState(false);
    const displayValue = normalizeToDisplay(value, valueFormat);
    const selected = toDateValue(displayValue);
    const maxDateValue = maxDate ? toDateValue(normalizeToDisplay(maxDate, valueFormat)) : undefined;

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
                        <span className={displayValue ? '' : 'text-slate-400'}>
                            {displayValue || placeholder}
                        </span>
                        <CalendarIcon className="h-4 w-4 shrink-0 text-slate-400" />
                    </button>
                }
            />
            <PopoverContent className="w-auto overflow-hidden p-0" align="start">
                <Calendar
                    mode="single"
                    selected={selected}
                    defaultMonth={selected}
                    captionLayout="dropdown"
                    disabled={maxDateValue ? [{ after: maxDateValue }] : undefined}
                    onSelect={(date) => {
                        const display = date ? fromDateValue(date) : '';
                        onChange(formatForOnChange(display, valueFormat));
                        setOpen(false);
                    }}
                />
            </PopoverContent>
        </Popover>
    );
}

export { DatePicker, toIsoDate, toDisplayDate, toDateValue, fromDateValue };