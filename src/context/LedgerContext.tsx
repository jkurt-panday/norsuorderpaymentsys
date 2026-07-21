import React, { createContext, useContext, ReactNode } from 'react';
import { useFilteredLedger } from '@/hooks/useFilteredLedger';
import type { StudentRecord, LedgerFilters, LedgerSummary } from '@/types';

interface LedgerContextValue {
  records: StudentRecord[];
  summary: LedgerSummary | null;
  loading: boolean;
  error: string | null;
  filters: LedgerFilters;
  setFilter: <K extends keyof LedgerFilters>(key: K, value: LedgerFilters[K]) => void;
  resetFilters: () => void;
  page: number;
  perPage: number;
  setPage: (page: number) => void;
  setPerPage: (perPage: number) => void;
  totalPages: number;
  totalRecords: number;
  refresh: () => void;
}

const LedgerContext = createContext<LedgerContextValue | undefined>(undefined);

export const LedgerProvider: React.FC<{ children: ReactNode }> = ({ children }) => {
  const ledger = useFilteredLedger();

  return <LedgerContext.Provider value={ledger}>{children}</LedgerContext.Provider>;
};

export const useLedger = (): LedgerContextValue => {
  const context = useContext(LedgerContext);
  if (!context) {
    throw new Error('useLedger must be used within a LedgerProvider');
  }
  return context;
};