import React, { useState } from 'react';
import { cn } from '@/utils/cn';
import { Sidebar } from './Sidebar';
import { TopNavbar } from './TopNavbar';

interface DashboardLayoutProps {
  children: React.ReactNode;
  title: string;
}

export const DashboardLayout: React.FC<DashboardLayoutProps> = ({ children, title }) => {
  const [mobileSidebarOpen, setMobileSidebarOpen] = useState(false);
  const [collapsed, setCollapsed] = useState(false);
  const SidebarComponent = Sidebar as React.ComponentType<{
    collapsed: boolean;
    onToggle: () => void;
  }>;

  return (
    <div className="min-h-screen bg-neutral-50">
      {/* Sidebar: permanent on desktop, overlay on mobile */}
      <div className="hidden lg:block">
        <SidebarComponent collapsed={collapsed} onToggle={() => setCollapsed(!collapsed)} />
      </div>

      {/* Mobile sidebar overlay */}
      {mobileSidebarOpen && (
        <div className="fixed inset-0 z-40 lg:hidden">
          <div
            className="fixed inset-0 bg-black/50 backdrop-blur-sm"
            onClick={() => setMobileSidebarOpen(false)}
          />
          <div className="relative z-50">
            <SidebarComponent collapsed={false} onToggle={() => {}} />
          </div>
        </div>
      )}

      {/* Main content area – margin adjusts when sidebar collapses */}
      <main
        className={cn(
          'transition-all duration-300',
          collapsed ? 'lg:ml-20' : 'lg:ml-64'
        )}
      >
        <TopNavbar title={title} onMenuClick={() => setMobileSidebarOpen(true)} />
        <div className="p-4 sm:p-6 lg:p-8">{children}</div>
      </main>
    </div>
  );
};