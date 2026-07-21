import { useState, useCallback } from 'react';
import * as ledgerService from '@/services/ledgerService';
import type {
  StudentRecord,
  LedgerFilters,
  UploadResponse,
  LedgerSummary,
} from '@/types';
import { useToast } from './useToast';

interface UseLedgerApiReturn {
  loading: boolean;
  error: string | null;
  uploadFile: (file: File) => Promise<UploadResponse | null>;
  getRecords: (
    filters: Partial<LedgerFilters> & { page?: number; perPage?: number }
  ) => Promise<{ data: StudentRecord[]; meta: any } | null>;
  getSummary: (filters: Partial<LedgerFilters>) => Promise<LedgerSummary | null>;
  deleteRecord: (id: string) => Promise<boolean>;
  exportData: (filters: Partial<LedgerFilters>, format?: 'csv' | 'pdf') => Promise<void>;
}

export function useLedgerApi(): UseLedgerApiReturn {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const { showToast } = useToast();

  const handleRequest = useCallback(
    async <T>(request: () => Promise<T>): Promise<T | null> => {
      setLoading(true);
      setError(null);
      try {
        const result = await request();
        return result;
      } catch (err: any) {
        const message =
          err?.response?.data?.message || err?.message || 'An unexpected error occurred.';
        setError(message);
        showToast(message, 'error');
        return null;
      } finally {
        setLoading(false);
      }
    },
    [showToast]
  );

  const uploadFile = useCallback(
    async (file: File) => {
      const result = await handleRequest(() => ledgerService.uploadLedgerFile(file));
      if (result) {
        showToast(result.message || 'File uploaded successfully!', 'success');
      }
      return result;
    },
    [handleRequest, showToast]
  );

  const getRecords = useCallback(
    async (filters: Partial<LedgerFilters> & { page?: number; perPage?: number }) => {
      const response = await handleRequest(() => ledgerService.fetchLedgerRecords(filters));
      return response ? response.data : null;
    },
    [handleRequest]
  );

  const getSummary = useCallback(
    async (filters: Partial<LedgerFilters>) => {
      return handleRequest(() => ledgerService.fetchLedgerSummary(filters));
    },
    [handleRequest]
  );

  const deleteRecord = useCallback(
    async (id: string) => {
      const result = await handleRequest(() => ledgerService.deleteLedgerRecord(id));
      if (result !== null) {
        showToast('Record deleted successfully.', 'success');
        return true;
      }
      return false;
    },
    [handleRequest, showToast]
  );

  const exportData = useCallback(
    async (filters: Partial<LedgerFilters>, format: 'csv' | 'pdf' = 'csv') => {
      await handleRequest(() => ledgerService.exportLedger(filters, format));
    },
    [handleRequest]
  );

  return {
    loading,
    error,
    uploadFile,
    getRecords,
    getSummary,
    deleteRecord,
    exportData,
  };
}