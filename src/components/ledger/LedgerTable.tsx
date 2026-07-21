import React, { useState } from 'react';
import { cn } from '@/utils/cn';
import { formatCurrency, formatDate } from '@/utils/formatters';
import { EmptyState } from '@/components/common/EmptyState';
import { Spinner } from '@/components/common/Spinner';
import { Card } from '@/components/common/Card';
import { Pagination } from '@/components/common/Pagination';
import { ChevronDown, ChevronRight, AlertCircle } from 'lucide-react';
import type { StudentRecord, PaymentStatus } from '@/types';

interface SortConfig {
  key: keyof StudentRecord;
  direction: 'asc' | 'desc';
}

interface LedgerTableProps {
  records: StudentRecord[];
  loading: boolean;
  error: string | null;
  currentPage: number;
  totalPages: number;
  totalRecords: number;
  perPage: number;
  onPageChange: (page: number) => void;
  onPerPageChange: (perPage: number) => void;
  onSort?: (sort: SortConfig) => void;
}

const statusBadgeClasses: Record<PaymentStatus, string> = {
  Paid: 'bg-green-100 text-green-700 border-green-200',
  Partial: 'bg-orange-100 text-orange-700 border-orange-200',
  Unpaid: 'bg-red-100 text-red-700 border-red-200',
};

export const LedgerTable: React.FC<LedgerTableProps> = ({
  records,
  loading,
  error,
  currentPage,
  totalPages,
  totalRecords,
  perPage,
  onPageChange,
  onPerPageChange,
  onSort,
}) => {
  const [expandedRows, setExpandedRows] = useState<Set<string>>(new Set());
  const [sortConfig, setSortConfig] = useState<SortConfig | null>(null);

  const toggleRow = (id: string) => {
    setExpandedRows((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const handleSort = (key: keyof StudentRecord) => {
    const newDirection =
      sortConfig?.key === key && sortConfig.direction === 'asc' ? 'desc' : 'asc';
    const newSort: SortConfig = { key, direction: newDirection };
    setSortConfig(newSort);
    onSort?.(newSort);
  };

  const renderSortIndicator = (key: keyof StudentRecord) => {
    if (sortConfig?.key !== key) return null;
    return (
      <span className="ml-1 text-primary-600">
        {sortConfig.direction === 'asc' ? '↑' : '↓'}
      </span>
    );
  };

  const columns: { key: keyof StudentRecord; label: string; sortable: boolean }[] = [
    { key: 'studentId', label: 'Student ID', sortable: true },
    { key: 'studentName', label: 'Student Name', sortable: true },
    { key: 'program', label: 'Program', sortable: true },
    { key: 'yearLevel', label: 'Year', sortable: true },
    { key: 'remainingBalance', label: 'Balance', sortable: true },
    { key: 'dueDate', label: 'Due Date', sortable: true },
    { key: 'status', label: 'Status', sortable: true },
  ];

  // Content based on state
  if (error) {
    return (
      <Card padding="none">
        <div className="flex flex-col items-center justify-center py-16 text-center">
          <AlertCircle size={48} className="text-red-400 mb-4" />
          <p className="text-neutral-600 font-medium">Failed to load records</p>
          <p className="text-sm text-neutral-500 mt-1">{error}</p>
        </div>
      </Card>
    );
  }

  if (!loading && records.length === 0) {
    return (
      <Card padding="none">
        <EmptyState
          title="No records found"
          description="Try adjusting your filters or upload a new ledger file."
        />
      </Card>
    );
  }

  return (
    <Card padding="none" className="overflow-hidden">
      <div className="overflow-x-auto table-scrollbar">
        <table className="w-full text-left">
          <thead>
            <tr className="bg-neutral-50 border-b border-neutral-200">
              {/* Expandable column */}
              <th className="sticky top-0 z-10 bg-neutral-50 w-10 px-3 py-3" />
              {columns.map((col) => (
                <th
                  key={col.key}
                  className={cn(
                    'sticky top-0 z-10 bg-neutral-50 px-4 py-3 text-xs font-semibold text-neutral-600 uppercase tracking-wider',
                    col.sortable && 'cursor-pointer select-none hover:text-neutral-900'
                  )}
                  onClick={() => col.sortable && handleSort(col.key)}
                >
                  <div className="flex items-center">
                    {col.label}
                    {renderSortIndicator(col.key)}
                  </div>
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {loading && records.length === 0 ? (
              <tr>
                <td colSpan={columns.length + 1} className="py-16">
                  <Spinner className="mx-auto" size="lg" />
                </td>
              </tr>
            ) : (
              records.map((record, idx) => (
                <React.Fragment key={record.id}>
                  {/* Main row */}
                  <tr
                    className={cn(
                      'transition-colors hover:bg-neutral-50',
                      idx % 2 === 0 ? 'bg-white' : 'bg-neutral-50/50'
                    )}
                  >
                    <td className="px-3 py-4">
                      <button
                        onClick={() => toggleRow(record.id)}
                        className="p-1 rounded hover:bg-neutral-200 transition-colors"
                        aria-label={expandedRows.has(record.id) ? 'Collapse row' : 'Expand row'}
                      >
                        {expandedRows.has(record.id) ? (
                          <ChevronDown size={18} className="text-neutral-600" />
                        ) : (
                          <ChevronRight size={18} className="text-neutral-400" />
                        )}
                      </button>
                    </td>
                    <td className="px-4 py-4 text-sm font-medium text-neutral-800">
                      {record.studentId}
                    </td>
                    <td className="px-4 py-4 text-sm text-neutral-700">
                      {record.studentName}
                    </td>
                    <td className="px-4 py-4 text-sm text-neutral-600">
                      {record.program}
                    </td>
                    <td className="px-4 py-4 text-sm">{record.yearLevel}</td>
                    <td className="px-4 py-4 text-sm font-mono font-medium text-neutral-800">
                      {formatCurrency(record.remainingBalance)}
                    </td>
                    <td className="px-4 py-4 text-sm text-neutral-600">
                      {formatDate(record.dueDate)}
                    </td>
                    <td className="px-4 py-4">
                      <span
                        className={cn(
                          'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border',
                          statusBadgeClasses[record.status]
                        )}
                      >
                        {record.status}
                      </span>
                    </td>
                  </tr>
                  {/* Expanded row details */}
                  {expandedRows.has(record.id) && (
                    <tr className="bg-neutral-50">
                      <td colSpan={columns.length + 1} className="px-10 py-4">
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                          {record.email && (
                            <div>
                              <p className="text-xs text-neutral-500 font-medium">Email</p>
                              <p className="text-neutral-800">{record.email}</p>
                            </div>
                          )}
                          {record.contactNumber && (
                            <div>
                              <p className="text-xs text-neutral-500 font-medium">Contact</p>
                              <p className="text-neutral-800">{record.contactNumber}</p>
                            </div>
                          )}
                          {record.lastPaymentDate && (
                            <div>
                              <p className="text-xs text-neutral-500 font-medium">Last Payment Date</p>
                              <p className="text-neutral-800">{formatDate(record.lastPaymentDate)}</p>
                            </div>
                          )}
                          {record.lastPaymentAmount !== undefined && (
                            <div>
                              <p className="text-xs text-neutral-500 font-medium">Last Payment</p>
                              <p className="text-neutral-800 font-mono">
                                {formatCurrency(record.lastPaymentAmount)}
                              </p>
                            </div>
                          )}
                        </div>
                      </td>
                    </tr>
                  )}
                </React.Fragment>
              ))
            )}
          </tbody>
        </table>
      </div>

      {/* Pagination – now includes per-page selector */}
      {!loading && totalRecords > 0 && (
        <Pagination
          currentPage={currentPage}
          totalPages={totalPages}
          onPageChange={onPageChange}
          totalItems={totalRecords}
          itemsPerPage={perPage}
          onPerPageChange={onPerPageChange}
        />
      )}
    </Card>
  );
};