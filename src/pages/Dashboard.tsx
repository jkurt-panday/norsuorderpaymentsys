import React from 'react';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import { SummaryCards } from '@/components/ledger/SummaryCards';
import { UploadSection } from '@/components/ledger/UploadSection';
import { FinancialSummary } from '@/components/ledger/FinancialSummary';
import { Card } from '@/components/common/Card';
import { useLedger } from '@/context/LedgerContext';
import { useNavigate } from 'react-router-dom';
import { FileSpreadsheet, ArrowRight } from 'lucide-react';
import { Button } from '@/components/common/Button';

const Dashboard: React.FC = () => {
  const { summary, loading, refresh } = useLedger();
  const navigate = useNavigate();

  const handleUploadSuccess = () => {
    refresh();
  };

  return (
    <DashboardLayout title="Dashboard">
      <div className="space-y-6 max-w-7xl mx-auto">
        {/* Summary Cards */}
        <SummaryCards summary={summary} loading={loading} />

        {/* Financial summary + upload section */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div className="lg:col-span-1">
            <FinancialSummary totalBalance={summary?.totalBalance ?? 0} loading={loading} />
          </div>
          <div className="lg:col-span-2">
            <UploadSection onUploadSuccess={handleUploadSuccess} />
          </div>
        </div>

        {/* Quick action: View Ledger */}
        <Card className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div className="flex items-center gap-4">
            <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary-100 text-primary-600">
              <FileSpreadsheet size={24} />
            </div>
            <div>
              <h3 className="text-lg font-semibold text-neutral-800">Full Ledger</h3>
              <p className="text-sm text-neutral-500">
                View, search, sort, and filter all student financial records.
              </p>
            </div>
          </div>
          <Button icon={<ArrowRight size={16} />} onClick={() => navigate('/ledger')}>
            Go to Ledger
          </Button>
        </Card>
      </div>
    </DashboardLayout>
  );
};

export default Dashboard;