import {
    Home,
    FileText,
    Users,
    CreditCard,
    Landmark,
    Code,
} from 'lucide-react';
import { NavMain } from '@/components/nav-main';
import {
    Sidebar,
    SidebarContent,
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
    },
    {
        title: 'Bank Accounts',
        href: staff.bankAccounts.index(),
        icon: Landmark,
    },
    {
        title: 'UACS',
        href: staff.uacs.index(),
        icon: Code,
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
            <SidebarHeader className="mb-4 items-center bg-transparent px-3 text-center">
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
        </Sidebar>
    );
}