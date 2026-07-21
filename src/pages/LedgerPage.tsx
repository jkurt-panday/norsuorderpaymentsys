import React from 'react';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import { LedgerFilters } from '@/components/ledger/LedgerFilters';
import { LedgerTable } from '@/components/ledger/LedgerTable';
import { FinancialSummary } from '@/components/ledger/FinancialSummary';
import { useLedger } from '@/context/LedgerContext';

const LedgerPage: React.FC = () => {
  const {
    records,
    summary,
    loading,
    error,
    filters,
    setFilter,
    resetFilters,
    page,
    perPage,
    setPage,
    setPerPage,
    totalPages,
    totalRecords,
  } = useLedger();

  const handleSort = () => {
    // Sorting is handled by the backend through API params; for now, no-op
    // Can be extended to send sort params to API
  };

  return (
    <DashboardLayout title="Ledger">
      <div className="space-y-6 max-w-7xl mx-auto">
        {/* Financial Summary Bar */}
        <FinancialSummary totalBalance={summary?.totalBalance ?? 0} loading={loading} />

        {/* Filters */}
        <LedgerFilters filters={filters} onFilterChange={setFilter} onReset={resetFilters} />

        {/* Ledger Table */}
        <LedgerTable
          records={records}
          loading={loading}
          error={error}
          currentPage={page}
          totalPages={totalPages}
          totalRecords={totalRecords}
          perPage={perPage}
          onPageChange={setPage}
          onPerPageChange={setPerPage}
          onSort={handleSort}
        />
      </div>
    </DashboardLayout>
  );
};

export default LedgerPage;