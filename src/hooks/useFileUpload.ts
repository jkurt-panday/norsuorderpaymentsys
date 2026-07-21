import { useCallback, useState } from 'react';
import { useDropzone } from 'react-dropzone';
import { validateLedgerFile } from '@/utils/validators';
import { UPLOAD_ACCEPTED_TYPES } from '@/utils/constants';

interface UseFileUploadReturn {
  file: File | null;
  isDragActive: boolean;
  validationError: string | null;
  getRootProps: () => any;
  getInputProps: () => any;
  clearFile: () => void;
  openFileDialog: () => void;
}

export function useFileUpload(): UseFileUploadReturn {
  const [file, setFile] = useState<File | null>(null);
  const [validationError, setValidationError] = useState<string | null>(null);

  const onDrop = useCallback((acceptedFiles: File[]) => {
    setValidationError(null);
    if (acceptedFiles.length === 0) return;

    const selectedFile = acceptedFiles[0];
    const error = validateLedgerFile(selectedFile);
    if (error) {
      setValidationError(error);
      setFile(null);
      return;
    }
    setFile(selectedFile);
  }, []);

  const { getRootProps, getInputProps, isDragActive, open } = useDropzone({
    onDrop,
    accept: UPLOAD_ACCEPTED_TYPES,
    maxFiles: 1,
    noClick: true,
    noKeyboard: true,
  });

  const clearFile = useCallback(() => {
    setFile(null);
    setValidationError(null);
  }, []);

  const openFileDialog = useCallback(() => {
    open();
  }, [open]);

  return {
    file,
    isDragActive,
    validationError,
    getRootProps,
    getInputProps,
    clearFile,
    openFileDialog,
  };
}