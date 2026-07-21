import React from 'react';
import { Calendar } from 'lucide-react';
import { cn } from '@/utils/cn';

interface DatePickerProps {
  label?: string;
  value: string;
  onChange: (value: string) => void;
  className?: string;
  id?: string;
}

export const DatePicker: React.FC<DatePickerProps> = ({
  label,
  value,
  onChange,
  className,
  id,
}) => {
  return (
    <div className={cn('relative', className)}>
      {label && (
        <label htmlFor={id} className="block text-xs font-medium text-neutral-600 mb-1">
          {label}
        </label>
      )}
      <div className="relative">
        <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
          <Calendar size={16} className="text-neutral-400" />
        </div>
        <input
          type="date"
          id={id}
          value={value}
          onChange={(e) => onChange(e.target.value)}
          className="w-full pl-10 pr-3 py-2 text-sm border border-neutral-300 rounded-lg bg-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none appearance-none"
        />
      </div>
    </div>
  );
};