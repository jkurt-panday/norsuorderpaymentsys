import { StudentRecord, PaginationMeta } from './ledger';

export interface ApiResponse<T> {
  data: T;
  message?: string;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta: PaginationMeta;
}

export type LedgerListResponse = ApiResponse<PaginatedResponse<StudentRecord>>;