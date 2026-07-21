import React from 'react';
import { Card } from '@/components/common/Card';
import { FileUpload } from '@/components/common/FileUpload';

interface UploadSectionProps {
  onUploadSuccess: () => void;
}

export const UploadSection: React.FC<UploadSectionProps> = ({ onUploadSuccess }) => {
  return (
    <Card padding="lg">
      <div className="mb-6">
        <h2 className="text-lg font-semibold text-neutral-800">Upload Ledger File</h2>
        <p className="text-sm text-neutral-500 mt-1">
          Import student financial records from an Excel spreadsheet. Accepted formats: .xlsx, .xls (max 5MB).
        </p>
      </div>
      <FileUpload onUploadSuccess={onUploadSuccess} />
    </Card>
  );
};