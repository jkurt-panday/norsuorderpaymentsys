import React from 'react';
import { Card } from '@/components/common/Card';
import type { LedgerSummary } from '@/types';
import { formatCurrency, formatNumber } from '@/utils/formatters';
import { cn } from '@/utils/cn';

interface SummaryCardsProps {
  summary: LedgerSummary | null;
  loading?: boolean;
}

const cardConfig = [
  {
    key: 'totalRecords',
    label: 'Total Records',
    format: (val: number) => formatNumber(val),
    color: 'text-primary-600',
    bg: 'bg-primary-50',
  },
  {
    key: 'totalBalance',
    label: 'Total Balance',
    format: (val: number) => formatCurrency(val),
    color: 'text-gold-600',
    bg: 'bg-gold-50',
  },
  {
    key: 'paidCount',
    label: 'Paid',
    format: (val: number) => formatNumber(val),
    color: 'text-green-600',
    bg: 'bg-green-50',
  },
  {
    key: 'partialCount',
    label: 'Partial',
    format: (val: number) => formatNumber(val),
    color: 'text-orange-600',
    bg: 'bg-orange-50',
  },
  {
    key: 'unpaidCount',
    label: 'Unpaid',
    format: (val: number) => formatNumber(val),
    color: 'text-red-600',
    bg: 'bg-red-50',
  },
] as const;

export const SummaryCards: React.FC<SummaryCardsProps> = ({ summary, loading }) => {
  return (
    <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
      {cardConfig.map((card) => {
        const value = summary ? summary[card.key as keyof LedgerSummary] : 0;
        return (
          <Card key={card.key} padding="sm" className="flex flex-col gap-1">
            <p className="text-xs font-medium text-neutral-500">{card.label}</p>
            {loading ? (
              <div className="h-6 w-24 bg-neutral-200 animate-pulse rounded" />
            ) : (
              <p className={cn('text-lg font-bold', card.color)}>
                {card.format(value as number)}
              </p>
            )}
          </Card>
        );
      })}
    </div>
  );
};