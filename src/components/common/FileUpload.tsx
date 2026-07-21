import React, { useState } from 'react';
import { Upload, FileSpreadsheet, XCircle, AlertTriangle, CheckCircle } from 'lucide-react';
import { cn } from '@/utils/cn';
import { useFileUpload } from '@/hooks/useFileUpload';
import { useLedgerApi } from '@/hooks/useLedgerApi';
import { Button } from './Button';
import { Spinner } from './Spinner';

interface FileUploadProps {
  onUploadSuccess?: () => void;
}

export const FileUpload: React.FC<FileUploadProps> = ({ onUploadSuccess }) => {
  const { file, isDragActive, validationError, getRootProps, getInputProps, clearFile, openFileDialog } = useFileUpload();
  const { uploadFile, loading: uploading } = useLedgerApi();
  const [uploadError, setUploadError] = useState<string | null>(null);
  const [uploadSuccess, setUploadSuccess] = useState(false);

  const handleUpload = async () => {
    if (!file) return;
    setUploadError(null);
    setUploadSuccess(false);

    const result = await uploadFile(file);
    if (result && result.success) {
      setUploadSuccess(true);
      clearFile();
      onUploadSuccess?.();
    } else if (result && !result.success) {
      setUploadError(result.message || 'Upload failed. Please try again.');
    }
  };

  const fileSizeMB = file ? (file.size / (1024 * 1024)).toFixed(2) : '0';

  return (
    <div className="space-y-4">
      {/* Dropzone */}
      <div
        {...getRootProps()}
        className={cn(
          'relative border-2 border-dashed rounded-xl p-8 transition-colors cursor-pointer flex flex-col items-center justify-center gap-3',
          isDragActive
            ? 'border-primary-500 bg-primary-50'
            : 'border-neutral-300 bg-neutral-50 hover:border-primary-400 hover:bg-primary-50/50',
          file && 'border-green-500 bg-green-50'
        )}
      >
        <input {...getInputProps()} />
        {!file ? (
          <>
            <div className="flex h-14 w-14 items-center justify-center rounded-full bg-primary-100 text-primary-600">
              <Upload size={28} />
            </div>
            <div className="text-center">
              <p className="text-sm font-medium text-neutral-700">
                <button type="button" onClick={openFileDialog} className="text-primary-600 hover:underline font-semibold">
                  Click to upload
                </button>{' '}
                or drag and drop
              </p>
              <p className="mt-1 text-xs text-neutral-500">
                Excel files only (.xlsx, .xls) up to 5MB
              </p>
            </div>
          </>
        ) : (
          <div className="flex items-center gap-4 w-full max-w-md">
            <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-green-100 text-green-600">
              <FileSpreadsheet size={24} />
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium text-neutral-800 truncate">{file.name}</p>
              <p className="text-xs text-neutral-500">{fileSizeMB} MB</p>
            </div>
            <button
              onClick={(e) => { e.stopPropagation(); clearFile(); }}
              className="p-1 text-neutral-400 hover:text-red-500 rounded-full"
              aria-label="Remove file"
            >
              <XCircle size={20} />
            </button>
          </div>
        )}
      </div>

      {/* Validation error from dropzone */}
      {validationError && (
        <div className="flex items-center gap-2 text-sm text-red-600 bg-red-50 p-3 rounded-lg">
          <AlertTriangle size={18} />
          <span>{validationError}</span>
        </div>
      )}

      {/* Upload error from server */}
      {uploadError && (
        <div className="flex items-center gap-2 text-sm text-red-600 bg-red-50 p-3 rounded-lg">
          <XCircle size={18} />
          <span>{uploadError}</span>
        </div>
      )}

      {/* Success message */}
      {uploadSuccess && (
        <div className="flex items-center gap-2 text-sm text-green-700 bg-green-50 p-3 rounded-lg">
          <CheckCircle size={18} />
          <span>File uploaded and records imported successfully!</span>
        </div>
      )}

      {/* Upload button */}
      <div className="flex justify-end">
        <Button
          variant="primary"
          size="md"
          disabled={!file || uploading}
          isLoading={uploading}
          onClick={handleUpload}
          icon={<Upload size={16} />}
        >
          {uploading ? 'Uploading...' : 'Upload File'}
        </Button>
      </div>
    </div>
  );
};