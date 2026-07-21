import React, { createContext, useContext } from 'react';
import { Toaster } from 'react-hot-toast';
import { useToast as useToastHook } from '@/hooks/useToast';

interface ToastContextValue {
  showToast: ReturnType<typeof useToastHook>['showToast'];
  dismissToast: ReturnType<typeof useToastHook>['dismissToast'];
}

const ToastContext = createContext<ToastContextValue | undefined>(undefined);

export const ToastProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const { showToast, dismissToast } = useToastHook();

  return (
    <ToastContext.Provider value={{ showToast, dismissToast }}>
      {children}
      <Toaster
        position="top-right"
        gutter={12}
        toastOptions={{
          duration: 4000,
          style: {
            background: '#ffffff',
            color: '#1e293b',
            border: '1px solid #e2e8f0',
            borderRadius: '0.75rem',
            boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)',
            fontSize: '0.875rem',
          },
          success: {
            iconTheme: { primary: '#2563eb', secondary: '#ffffff' },
          },
          error: {
            iconTheme: { primary: '#ef4444', secondary: '#ffffff' },
          },
        }}
      />
    </ToastContext.Provider>
  );
};

export const useToast = (): ToastContextValue => {
  const context = useContext(ToastContext);
  if (!context) {
    throw new Error('useToast must be used within a ToastProvider');
  }
  return context;
};