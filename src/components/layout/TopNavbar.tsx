import React from 'react';
import { Bell, Settings, LogOut, Menu } from 'lucide-react';
interface TopNavbarProps {
  title: string;
  onMenuClick?: () => void;
}

export const TopNavbar: React.FC<TopNavbarProps> = ({ title, onMenuClick }) => {
  return (
    <header className="sticky top-0 z-20 flex items-center justify-between h-16 px-4 sm:px-6 bg-white border-b border-neutral-200 shadow-sm">
      {/* Left side: mobile menu toggle + page title */}
      <div className="flex items-center gap-4">
        <button
          onClick={onMenuClick}
          className="lg:hidden p-2 -ml-2 text-neutral-600 hover:text-neutral-900 rounded-lg hover:bg-neutral-100"
          aria-label="Open sidebar"
        >
          <Menu size={20} />
        </button>
        <div>
          <h1 className="text-lg font-semibold text-neutral-800">{title}</h1>
          <p className="hidden sm:block text-xs text-neutral-500">Law School Ledger System</p>
        </div>
      </div>

      {/* Right side: actions */}
      <div className="flex items-center gap-2 sm:gap-3">
        <button
          className="relative p-2 text-neutral-500 hover:text-neutral-700 rounded-lg hover:bg-neutral-100 transition-colors"
          aria-label="Notifications"
        >
          <Bell size={20} />
          <span className="absolute top-1 right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white">
            3
          </span>
        </button>

        <button
          className="p-2 text-neutral-500 hover:text-neutral-700 rounded-lg hover:bg-neutral-100 transition-colors"
          aria-label="Settings"
        >
          <Settings size={20} />
        </button>

        <div className="h-6 w-px bg-neutral-200 hidden sm:block" />

        <button className="flex items-center gap-2 p-1.5 rounded-lg hover:bg-neutral-100 transition-colors">
          <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary-100 text-primary-700 text-sm font-semibold">
            AD
          </div>
          <div className="hidden sm:block text-left">
            <p className="text-sm font-medium text-neutral-700">Admin</p>
            <p className="text-xs text-neutral-500">admin@law.edu</p>
          </div>
        </button>

        <button
          className="p-2 text-neutral-500 hover:text-red-600 rounded-lg hover:bg-red-50 transition-colors"
          aria-label="Logout"
        >
          <LogOut size={20} />
        </button>
      </div>
    </header>
  );
};