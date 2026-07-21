import React from 'react';
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import { ToastProvider } from '@/context/ToastContext';
import { LedgerProvider } from '@/context/LedgerContext';
import Dashboard from '@/pages/Dashboard';
import LedgerPage from '@/pages/LedgerPage';
import NotFound from '@/pages/NotFound';

const App: React.FC = () => {
  return (
    <BrowserRouter>
      <ToastProvider>
        <LedgerProvider>
          <Routes>
            <Route path="/" element={<Dashboard />} />
            <Route path="/ledger" element={<LedgerPage />} />
            {/* Additional routes can be added here */}
            <Route path="*" element={<NotFound />} />
          </Routes>
        </LedgerProvider>
      </ToastProvider>
    </BrowserRouter>
  );
};

export default App;