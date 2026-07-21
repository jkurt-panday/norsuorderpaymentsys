import { UPLOAD_ACCEPTED_TYPES, MAX_FILE_SIZE } from './constants';

/**
 * Validates whether a file is an accepted Excel format and within size limits.
 * Returns an error message string, or null if valid.
 */
export function validateLedgerFile(file: File): string | null {
  // Check file type
  const isExcel = Object.keys(UPLOAD_ACCEPTED_TYPES).includes(file.type) ||
    file.name.endsWith('.xlsx') ||
    file.name.endsWith('.xls');
  if (!isExcel) {
    return 'Invalid file type. Please upload an Excel file (.xlsx or .xls).';
  }

  // Check file size
  if (file.size > MAX_FILE_SIZE) {
    return `File size exceeds the limit of ${MAX_FILE_SIZE / (1024 * 1024)}MB.`;
  }

  return null; // valid
}