export const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api';

export const UPLOAD_ACCEPTED_TYPES = {
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': ['.xlsx'],
  'application/vnd.ms-excel': ['.xls'],
};

export const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB

export const PROGRAMS = [
  'Juris Doctor',
  'Master of Laws',
  'Doctor of Civil Law',
] as const;

export const YEAR_LEVELS = ['1', '2', '3', '4'] as const;

export const PAYMENT_STATUS_OPTIONS = [
  { value: '', label: 'All Statuses' },
  { value: 'Paid', label: 'Paid' },
  { value: 'Partial', label: 'Partial' },
  { value: 'Unpaid', label: 'Unpaid' },
] as const;

export const QUICK_FILTERS = [
  { label: 'Today', from: () => new Date().toISOString().slice(0, 10) },
  { label: 'This Week', from: () => {
      const now = new Date();
      const day = now.getDay();
      const diff = now.getDate() - day + (day === 0 ? -6 : 1);
      const monday = new Date(now.setDate(diff));
      return monday.toISOString().slice(0, 10);
    }
  },
  { label: 'This Month', from: () => {
      const now = new Date();
      return new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10);
    }
  },
];

export const PAGINATION_PAGE_SIZES = [10, 25, 50, 100];