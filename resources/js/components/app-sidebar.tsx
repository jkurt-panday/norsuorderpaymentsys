import {
    Home,
    FileText,
    Users,
    CreditCard,
    Landmark,
    ListTree,
    LogOut,
} from 'lucide-react';
import { Link } from '@inertiajs/react';
import { NavMain } from '@/components/nav-main';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    useSidebar,
} from '@/components/ui/sidebar';
import type { NavItem } from '@/types';
import staff from '@/routes/staff';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: staff.dashboard(),
        icon: Home,
    },
    {
        title: 'Requests',
        href: staff.requests.index(),
        icon: FileText,
    },
    {
        title: 'Memberships',
        href: staff.memberships.index(),
        icon: Users,
    },
    {
        title: 'Payment Options',
        href: staff.paymentOptions.index(),
        icon: CreditCard,
        separatorBefore: true,
    },
    {
        title: 'Bank Accounts',
        href: staff.bankAccounts.index(),
        icon: Landmark,
    },
    {
        title: 'UACS',
        href: staff.uacs.index(),
        icon: ListTree,
        separatorBefore: true,
    },
];

export function AppSidebar() {
    const { state } = useSidebar();
    const isCollapsed = state === 'collapsed';

    return (
        <Sidebar
            collapsible="icon"
            variant="inset"
            className="border-r border-slate-800 bg-slate-900 pt-5 text-white [&_[data-sidebar=sidebar]]:bg-transparent"
        >
            <SidebarHeader className="mb-2 items-center border-b border-slate-800/70 bg-transparent px-3 pb-4 text-center">
                {!isCollapsed ? (
                    <div className="flex flex-col items-center gap-2 py-2">
                        <img
                            src="/norsu.png"
                            alt="NORSU Order of Payment System"
                            className="h-12 w-12 rounded-md object-contain"
                        />
                        <div>
                            <h4 className="m-0 text-base font-semibold text-white">
                                Order of Payment
                            </h4>
                            <p className="text-xs text-slate-400">NORSU Staff Portal</p>
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

            <SidebarContent className="mt-0 bg-transparent">
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter className="border-t border-slate-800/70 bg-transparent px-3 py-3">
                {!isCollapsed ? (
                    <div className="flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-slate-800/60">
                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-900 text-sm font-semibold text-white">
                            {/* Replace with actual user initials */}
                            JD
                        </div>
                        <div className="flex min-w-0 flex-1 flex-col text-left">
                            <span className="truncate text-sm font-medium text-white">
                                {/* Replace with actual user name */}
                                Juan Dela Cruz
                            </span>
                            <span className="truncate text-xs text-slate-400">
                                {/* Replace with actual role/department */}
                                Staff · Accounting
                            </span>
                        </div>
                        <Link
                            href={staff.logout ? staff.logout() : '/logout'}
                            method="post"
                            as="button"
                            className="shrink-0 rounded-md p-1.5 text-slate-400 hover:bg-slate-700 hover:text-white"
                            aria-label="Log out"
                        >
                            <LogOut size={16} />
                        </Link>
                    </div>
                ) : (
                    <div className="flex justify-center">
                        <div className="flex h-8 w-8 items-center justify-center rounded-full bg-blue-900 text-xs font-semibold text-white">
                            JD
                        </div>
                    </div>
                )}
            </SidebarFooter>
        </Sidebar>
    );
}