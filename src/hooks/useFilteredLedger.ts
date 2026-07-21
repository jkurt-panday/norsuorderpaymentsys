import { useState, useEffect, useCallback } from 'react';
import { useLedgerApi } from './useLedgerApi';
import type { StudentRecord, LedgerFilters, LedgerSummary } from '@/types';
import { PAGINATION_PAGE_SIZES } from '@/utils/constants';

const initialFilters: LedgerFilters = {
  search: '',
  program: '',
  yearLevel: '',
  status: '',
  dateFrom: '',
  dateTo: '',
};

interface UseFilteredLedgerReturn {
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

export function useFilteredLedger(): UseFilteredLedgerReturn {
  const { loading, error, getRecords, getSummary } = useLedgerApi();

  const [filters, setFilters] = useState<LedgerFilters>(initialFilters);
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(PAGINATION_PAGE_SIZES[0]);
  const [records, setRecords] = useState<StudentRecord[]>([]);
  const [summary, setSummary] = useState<LedgerSummary | null>(null);
  const [totalPages, setTotalPages] = useState(1);
  const [totalRecords, setTotalRecords] = useState(0);

  // Fetch data whenever filters, page, or perPage change
  useEffect(() => {
    const fetchData = async () => {
      const params = { ...filters, page, perPage };
      const result = await getRecords(params);
      if (result) {
        setRecords(result.data);
        setTotalPages(result.meta.lastPage);
        setTotalRecords(result.meta.total);
      } else {
        setRecords([]);
        setTotalPages(1);
        setTotalRecords(0);
      }

      const summaryResult = await getSummary(filters);
      if (summaryResult) {
        setSummary(summaryResult);
      } else {
        setSummary(null);
      }
    };

    fetchData();
  }, [filters, page, perPage, getRecords, getSummary]);

  // Reset page to 1 when filters or perPage change
  const setFilter = useCallback(
    <K extends keyof LedgerFilters>(key: K, value: LedgerFilters[K]) => {
      setFilters((prev) => ({ ...prev, [key]: value }));
      setPage(1);
    },
    []
  );

  const handlePerPageChange = useCallback(
    (newPerPage: number) => {
      setPerPage(newPerPage);
      setPage(1);
    },
    []
  );

  const resetFilters = useCallback(() => {
    setFilters(initialFilters);
    setPage(1);
  }, []);

  const refresh = useCallback(() => {
    // Force re-fetch by updating a dependency (page stays same, but we can increment a counter)
    // Simple approach: re-run effect by updating a dummy state; but we can just call getRecords again.
    // We'll use a separate state `refreshKey` to trigger effect.
    // For simplicity, we'll just re-invoke the fetch inside refresh.
    const fetch = async () => {
      const params = { ...filters, page, perPage };
      const result = await getRecords(params);
      if (result) {
        setRecords(result.data);
        setTotalPages(result.meta.lastPage);
        setTotalRecords(result.meta.total);
      }
      const summaryResult = await getSummary(filters);
      if (summaryResult) setSummary(summaryResult);
    };
    fetch();
  }, [filters, page, perPage, getRecords, getSummary]);

  return {
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
    setPerPage: handlePerPageChange,
    totalPages,
    totalRecords,
    refresh,
  };
}