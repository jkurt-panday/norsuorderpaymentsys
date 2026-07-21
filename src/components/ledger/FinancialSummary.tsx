import React from 'react';
import { Card } from '@/components/common/Card';
import { formatCurrency } from '@/utils/formatters';
import { cn } from '@/utils/cn';

interface FinancialSummaryProps {
  totalBalance: number;
  loading?: boolean;
  className?: string;
}

export const FinancialSummary: React.FC<FinancialSummaryProps> = ({
  totalBalance,
  loading = false,
  className,
}) => {
  return (
    <Card
      className={cn(
        'bg-gradient-to-br from-primary-600 to-primary-800 text-white border-0 shadow-lg',
        className
      )}
      padding="md"
    >
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <p className="text-primary-100 text-sm font-medium">
            Total Remaining Balance (Filtered)
          </p>
          {loading ? (
            <div className="mt-2 h-9 w-48 bg-white/20 animate-pulse rounded" />
          ) : (
            <p className="mt-1 text-3xl sm:text-4xl font-bold tracking-tight">
              {formatCurrency(totalBalance)}
            </p>
          )}
          <p className="mt-2 text-primary-200 text-xs">
            Based on currently applied filters
          </p>
        </div>
        <div className="hidden sm:flex items-center justify-center h-16 w-16 rounded-full bg-white/15">
          <span className="text-2xl font-bold">₱</span>
        </div>
      </div>
    </Card>
  );
};