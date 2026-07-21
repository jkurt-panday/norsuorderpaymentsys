import React from 'react';
import { SearchBar } from '@/components/common/SearchBar';
import { Dropdown } from '@/components/common/Dropdown';
import { DatePicker } from '@/components/common/DatePicker';
import { Button } from '@/components/common/Button';
import { RotateCcw } from 'lucide-react';
import type { LedgerFilters as FiltersType } from '@/types';
import {
  PROGRAMS,
  YEAR_LEVELS,
  PAYMENT_STATUS_OPTIONS,
  QUICK_FILTERS,
} from '@/utils/constants';

interface LedgerFiltersProps {
  filters: FiltersType;
  onFilterChange: <K extends keyof FiltersType>(key: K, value: FiltersType[K]) => void;
  onReset: () => void;
}

export const LedgerFilters: React.FC<LedgerFiltersProps> = ({
  filters,
  onFilterChange,
  onReset,
}) => {
  return (
    <div className="space-y-4">
      {/* Search & quick actions row */}
      <div className="flex flex-col sm:flex-row gap-3 sm:items-center">
        <SearchBar
          value={filters.search}
          onChange={(val) => onFilterChange('search', val)}
          placeholder="Search by name, ID, or program..."
          className="w-full sm:max-w-md"
        />
        <div className="flex items-center gap-2 flex-wrap">
          {QUICK_FILTERS.map((qf) => (
            <button
              key={qf.label}
              type="button"
              onClick={() => {
                onFilterChange('dateFrom', qf.from());
                onFilterChange('dateTo', new Date().toISOString().slice(0, 10));
              }}
              className="px-3 py-1.5 text-xs font-medium rounded-full border border-neutral-300 bg-white text-neutral-600 hover:bg-primary-50 hover:border-primary-300 hover:text-primary-700 transition-colors"
            >
              {qf.label}
            </button>
          ))}
          <Button
            variant="ghost"
            size="sm"
            onClick={onReset}
            icon={<RotateCcw size={14} />}
          >
            Reset
          </Button>
        </div>
      </div>

      {/* Dropdown filters & date range */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <Dropdown
          label="Program"
          options={PROGRAMS.map((p) => ({ value: p, label: p }))}
          value={filters.program}
          onChange={(val) => onFilterChange('program', val)}
          placeholder="All Programs"
        />
        <Dropdown
          label="Year Level"
          options={YEAR_LEVELS.map((y) => ({ value: y, label: `Year ${y}` }))}
          value={filters.yearLevel}
          onChange={(val) => onFilterChange('yearLevel', val)}
          placeholder="All Years"
        />
        <Dropdown
          label="Status"
          options={PAYMENT_STATUS_OPTIONS}
          value={filters.status}
          onChange={(val) => onFilterChange('status', val as any)}
          placeholder="All Statuses"
        />
        <DatePicker
          label="From Date"
          value={filters.dateFrom}
          onChange={(val) => onFilterChange('dateFrom', val)}
        />
        <DatePicker
          label="To Date"
          value={filters.dateTo}
          onChange={(val) => onFilterChange('dateTo', val)}
        />
      </div>
    </div>
  );
};