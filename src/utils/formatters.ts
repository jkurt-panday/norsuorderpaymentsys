import { format, parseISO } from 'date-fns';

/**
 * Format a number as Philippine Peso currency string.
 */
export function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 2,
  }).format(amount);
}

/**
 * Format an ISO date string to a readable format (e.g., "Jan 15, 2026").
 */
export function formatDate(dateString: string): string {
  try {
    return format(parseISO(dateString), 'MMM d, yyyy');
  } catch {
    return dateString;
  }
}

/**
 * Format a number with commas for display (e.g., 1,234).
 */
export function formatNumber(num: number): string {
  return new Intl.NumberFormat('en-PH').format(num);
}