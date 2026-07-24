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
            className="pt-5 text-white [&_[data-sidebar=sidebar]]:bg-transparent"
            style={{
                background: 'linear-gradient(180deg, #0d6efd 0%, #0a58ca 100%)',
            }}
        >
            <SidebarHeader className="mb-4 items-center bg-transparent text-center">
                {!isCollapsed ? (
                    <>
                        <h4 className="m-0 text-2xl font-extrabold text-white">OP System</h4>
                        <small className="text-sm text-white/50">Order of Payment</small>
                    </>
                ) : (
                    // Show only icon or short text when collapsed
                    <h4 className="m-0 text-2xl font-extrabold text-white">OP</h4>
                )}
            </SidebarHeader>

            <SidebarContent className="mt-0 bg-transparent">
                <NavMain items={mainNavItems} />
            </SidebarContent>
        </Sidebar>
    );
}