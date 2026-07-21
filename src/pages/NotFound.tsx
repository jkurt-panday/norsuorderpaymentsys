import React from 'react';
import { Link } from 'react-router-dom';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import { FileQuestion } from 'lucide-react';
import { Button } from '@/components/common/Button';

const NotFound: React.FC = () => {
  return (
    <DashboardLayout title="404">
      <div className="flex flex-col items-center justify-center min-h-[60vh] text-center">
        <FileQuestion size={64} className="text-neutral-300 mb-6" />
        <h1 className="text-6xl font-bold text-neutral-200 mb-2">404</h1>
        <h2 className="text-xl font-semibold text-neutral-700 mb-2">Page not found</h2>
        <p className="text-neutral-500 mb-8 max-w-md">
          The page you are looking for doesn't exist or has been moved.
        </p>
        <Link to="/">
          <Button variant="primary">Back to Dashboard</Button>
        </Link>
      </div>
    </DashboardLayout>
  );
};

export default NotFound;