export type PaymentStatus = 'Paid' | 'Partial' | 'Unpaid';

export interface StudentRecord {
  id: string;
  studentId: string;       // e.g., "LS-2024-001"
  studentName: string;
  program: string;         // e.g., "Juris Doctor"
  yearLevel: number;       // 1 - 4
  remainingBalance: number;
  dueDate: string;         // ISO date string
  status: PaymentStatus;
  email?: string;
  contactNumber?: string;
  lastPaymentDate?: string;
  lastPaymentAmount?: number;
}

export interface LedgerFilters {
  search: string;
  program: string;
  yearLevel: string;
  status: PaymentStatus | '';
  dateFrom: string;
  dateTo: string;
}

export interface UploadResponse {
  success: boolean;
  message: string;
  totalImported: number;
  errors?: string[];
}

export interface LedgerSummary {
  totalRecords: number;
  totalBalance: number;
  paidCount: number;
  partialCount: number;
  unpaidCount: number;
}

export interface PaginationMeta {
  currentPage: number;
  lastPage: number;
  perPage: number;
  total: number;
}