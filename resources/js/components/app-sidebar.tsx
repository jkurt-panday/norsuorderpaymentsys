import { Link } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import { NavMain } from '@/components/nav-main';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    useSidebar,
} from '@/components/ui/sidebar';
import graduateLedger from '@/routes/graduate-ledger';
import lawLedger from '@/routes/law-ledger';
import staff from '@/routes/staff';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: staff.dashboard(),
    },
    {
        title: 'Requests',
        href: staff.requests.index(),
    },
    {
        title: 'Memberships',
        href: staff.memberships.index(),
    },
    {
        title: 'Payment Options',
        href: staff.paymentOptions.index(),
        separatorBefore: true,
    },
    {
        title: 'Bank Accounts',
        href: staff.bankAccounts.index(),
    },
    {
        title: 'UACS',
        href: staff.uacs.index(),
        separatorBefore: true,
    },
    {
        title: 'Law Ledger',
        href: lawLedger.index(),
    },
    {
        title: 'Activity Log',
        href: staff.activityLog(),
    },
    {
        title: 'Graduate Ledger',
        href: graduateLedger.index(),
    },
];

export function AppSidebar() {
    const { state } = useSidebar();
    const isCollapsed = state === 'collapsed';

    return (
        <Sidebar
            collapsible="icon"
            variant="inset"
            className="border-r border-blue-900/40 bg-[#003f7d] pt-5 text-white [&_[data-sidebar=sidebar]]:bg-transparent"
        >
            <SidebarHeader className="mb-2 items-center border-b border-blue-400/20 bg-transparent px-3 pb-4 text-center">
                {!isCollapsed ? (
                    <div className="flex flex-col items-center gap-2 py-2">
                        <img
                            src="/finance_logo1.png"
                            alt="NORSU Order of Payment System"
                            width={250}
                            height={250}
                            className="rounded-md object-contain"
                        />
                        <div>
                            <h4 className="m-0 text-base font-semibold text-white">
                                Order of Payment
                            </h4>
                            <p className="text-xs text-slate-400">
                                NORSU Staff Portal
                            </p>
                        </div>
                    </div>
                ) : (
                    <img
                        src="/norsu.png"
                        alt="NORSU Order of Payment System"
                        className="h-8 w-8 rounded-md object-contain"
                    />
                )}
            </SidebarHeader>

            <SidebarContent className="mt-0 scrollbar-thin scrollbar-thumb-blue-700 scrollbar-track-transparent bg-transparent">
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter className="border-t border-blue-400/20 bg-transparent px-3 py-3">
                {!isCollapsed ? (
                    <div className="flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-blue-600/40">
                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-400/30 text-sm font-semibold text-white ring-2 ring-blue-300/40">
                            {/* Replace with actual user initials */}
                            JD
                        </div>
                        <div className="flex min-w-0 flex-1 flex-col text-left">
                            <span className="truncate text-sm font-medium text-white">
                                {/* Replace with actual user name */}
                                Juan Dela Cruz
                            </span>
                            <span className="truncate text-xs text-blue-200/70">
                                {/* Replace with actual role/department */}
                                Staff · Accounting
                            </span>
                        </div>
                        <Link
                            href={staff.logout ? staff.logout() : '/logout'}
                            method="post"
                            as="button"
                            className="shrink-0 rounded-md p-1.5 text-blue-200/70 hover:bg-blue-500/40 hover:text-white"
                            aria-label="Log out"
                        >
                            <LogOut size={16} />
                        </Link>
                    </div>
                ) : (
                    <div className="flex justify-center">
                        <div className="flex h-8 w-8 items-center justify-center rounded-full bg-blue-400/30 text-xs font-semibold text-white ring-2 ring-blue-300/40">
                            JD
                        </div>
                    </div>
                )}
            </SidebarFooter>
        </Sidebar>
    );
}
