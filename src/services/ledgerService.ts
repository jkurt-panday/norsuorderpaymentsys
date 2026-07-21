import apiClient from './apiClient';
import type {
  LedgerFilters,
  UploadResponse,
  LedgerSummary,
  LedgerListResponse,
} from '@/types';

/**
 * Upload an Excel file with student ledger records.
 */
export async function uploadLedgerFile(file: File): Promise<UploadResponse> {
  const formData = new FormData();
  formData.append('file', file);

  const { data } = await apiClient.post<UploadResponse>('/ledger/upload', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return data;
}

/**
 * Fetch paginated, filtered ledger records.
 */
export async function fetchLedgerRecords(
  filters: Partial<LedgerFilters> & { page?: number; perPage?: number }
): Promise<LedgerListResponse> {
  const params = new URLSearchParams();

  if (filters.search) params.append('search', filters.search);
  if (filters.program) params.append('program', filters.program);
  if (filters.yearLevel) params.append('year_level', filters.yearLevel.toString());
  if (filters.status) params.append('status', filters.status);
  if (filters.dateFrom) params.append('date_from', filters.dateFrom);
  if (filters.dateTo) params.append('date_to', filters.dateTo);
  if (filters.page) params.append('page', filters.page.toString());
  if (filters.perPage) params.append('per_page', filters.perPage.toString());

  const { data } = await apiClient.get<LedgerListResponse>(`/ledger?${params.toString()}`);
  return data;
}

/**
 * Fetch aggregated summary statistics for the current filter set.
 */
export async function fetchLedgerSummary(
  filters: Partial<LedgerFilters>
): Promise<LedgerSummary> {
  const params = new URLSearchParams();

  if (filters.search) params.append('search', filters.search);
  if (filters.program) params.append('program', filters.program);
  if (filters.yearLevel) params.append('year_level', filters.yearLevel.toString());
  if (filters.status) params.append('status', filters.status);
  if (filters.dateFrom) params.append('date_from', filters.dateFrom);
  if (filters.dateTo) params.append('date_to', filters.dateTo);

  const { data } = await apiClient.get<{ data: LedgerSummary }>(`/ledger/summary?${params.toString()}`);
  return data.data;
}

/**
 * Delete a single ledger record.
 */
export async function deleteLedgerRecord(id: string): Promise<void> {
  await apiClient.delete(`/ledger/${id}`);
}

/**
 * Export filtered data (triggers file download).
 */
export async function exportLedger(
  filters: Partial<LedgerFilters>,
  format: 'csv' | 'pdf' = 'csv'
): Promise<void> {
  const params = new URLSearchParams();
  if (filters.search) params.append('search', filters.search);
  if (filters.program) params.append('program', filters.program);
  if (filters.yearLevel) params.append('year_level', filters.yearLevel.toString());
  if (filters.status) params.append('status', filters.status);
  if (filters.dateFrom) params.append('date_from', filters.dateFrom);
  if (filters.dateTo) params.append('date_to', filters.dateTo);
  params.append('format', format);

  const response = await apiClient.get(`/ledger/export?${params.toString()}`, {
    responseType: 'blob',
  });

  const url = window.URL.createObjectURL(new Blob([response.data]));
  const link = document.createElement('a');
  link.href = url;
  link.setAttribute('download', `ledger_export.${format}`);
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.URL.revokeObjectURL(url);
}