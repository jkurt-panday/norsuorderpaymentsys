import { Link, router, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Users,
    Activity,
    Home,
    FileText,
    CreditCard,
    Building,
    Scale,
    GraduationCap,
    Logs,
    School,
    FileSearchIcon,
    ChevronRight,
    LogOut,
} from 'lucide-react';
import React from 'react';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import {
    Collapsible,
    CollapsibleTrigger,
    CollapsibleContent,
} from '@/components/ui/collapsible';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubItem,
    SidebarMenuSubButton,
} from '@/components/ui/sidebar';

interface SidebarItem {
    title: string;
    href: string;
    icon: React.ComponentType<any>;
}

const adminNavItems: SidebarItem[] = [
    { title: 'Admin Dashboard', href: '/admin/dashboard', icon: BarChart3 },
    { title: 'User Management', href: '/admin/users', icon: Users },
    { title: 'Activity Log', href: '/admin/activity-log', icon: Activity },
];

const staffNavItems: SidebarItem[] = [
    { title: 'Dashboard', href: '/staff/staffdashboard', icon: Home },
    { title: 'Requests', href: '/staff/requests', icon: FileText },
    { title: 'Bank Accounts', href: '/staff/bank-accounts', icon: Building },
    {
        title: 'Payment Options',
        href: '/staff/payment-options',
        icon: CreditCard,
    },
    { title: 'Memberships', href: '/staff/memberships', icon: Users },
    { title: 'UACS', href: '/staff/uacs', icon: Logs },
    { title: 'Course', href: '/staff/courses', icon: School },
];

const staffCollapsibleItems = [
    {
        title: 'Assessments',
        icon: FileSearchIcon,
        items: [
            { title: 'Dashboard', href: '/staff/assessments/dashboard' },
            { title: 'Assessments', href: '/staff/assessments/' },
        ],
    },
    {
        title: 'Graduate Ledger',
        icon: GraduationCap,
        items: [
            { title: 'Ledger Overview', href: '/graduate-ledger' },
            { title: 'Search Student', href: '/graduate-ledger/print-select' },
        ],
    },
    {
        title: 'Law Ledger',
        icon: Scale,
        items: [
            { title: 'Law Overview', href: '/law-ledger' },
            { title: 'Print Statement', href: '/law-ledger/print-select' },
        ],
    },
];

export default function UnifiedSidebar() {
    const { url, props } = usePage<{
        auth?: { user?: { role?: string } | null };
    }>();
    const isAdmin = props?.auth?.user?.role === 'admin';
    const portalLabel = isAdmin ? 'Admin Portal' : 'Staff Portal';

    const isActive = (href: string) => {
        const currentPath = url.split('?')[0];
        return currentPath === href || currentPath.startsWith(href + '/');
    };

    return (
        <Sidebar className="border-r border-blue-900/40 bg-[#003f7d] text-white [&_[data-sidebar=sidebar]]:bg-transparent">
            <SidebarHeader className="border-b border-blue-400/20 px-4 py-5">
                <div className="flex flex-col items-center gap-2">
                    <img
                        src="/finance_logo1.png"
                        alt="NORSU Order of Payment System"
                        className="w-40 rounded-md object-contain"
                        onError={(e) => {
                            (e.target as HTMLImageElement).style.display =
                                'none';
                        }}
                    />
                    <div className="text-center">
                        <p className="text-sm font-semibold text-white">
                            Order of Payment
                        </p>
                        <p className="text-xs text-white/70">{portalLabel}</p>
                    </div>
                </div>
            </SidebarHeader>

            <SidebarContent className="mt-2 px-2">
                <SidebarMenu>
                    {isAdmin && (
                        <>
                            <div className="mb-2 px-2 py-1 text-xs font-semibold tracking-wider text-blue-200/80 uppercase">
                                Admin
                            </div>
                            {adminNavItems.map((item, index) => (
                                <SidebarMenuItem key={index}>
                                    <SidebarMenuButton
                                        render={
                                            <Link
                                                href={item.href}
                                                className={`my-0.5 flex w-full items-center gap-2 rounded-lg px-3 py-2.5 text-[15px] transition-colors duration-200 ${
                                                    isActive(item.href)
                                                        ? 'bg-[#0078d4] font-medium text-white shadow-sm'
                                                        : 'text-white/80 hover:bg-white/5 hover:text-white'
                                                }`}
                                            />
                                        }
                                    >
                                        <item.icon className="mr-2 h-4 w-4 shrink-0" />
                                        <span>{item.title}</span>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            ))}
                        </>
                    )}

                    <div
                        className={`mb-2 px-2 py-1 text-xs font-semibold tracking-wider text-blue-200/80 uppercase ${
                            isAdmin ? 'mt-4' : ''
                        }`}
                    >
                        Staff
                    </div>
                    {staffNavItems.map((item, index) => (
                        <SidebarMenuItem key={index}>
                            <SidebarMenuButton
                                render={
                                    <Link
                                        href={item.href}
                                        className={`my-0.5 flex w-full items-center gap-2 rounded-lg px-3 py-2.5 text-[15px] transition-colors duration-200 ${
                                            isActive(item.href)
                                                ? 'bg-[#0078d4] font-medium text-white shadow-sm'
                                                : 'text-white/80 hover:bg-white/5 hover:text-white'
                                        }`}
                                    />
                                }
                            >
                                <item.icon className="mr-2 h-4 w-4 shrink-0" />
                                <span>{item.title}</span>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    ))}

                    {staffCollapsibleItems.map((item, index) => (
                        <SidebarMenuItem key={index}>
                            <Collapsible
                                defaultOpen
                                className="group/collapsible"
                            >
                                <CollapsibleTrigger
                                    render={
                                        <SidebarMenuButton className="my-0.5 rounded-lg px-3 py-2.5 text-[15px] font-medium text-white hover:bg-white/5 hover:text-white" />
                                    }
                                >
                                    <item.icon className="mr-2 h-4 w-4 shrink-0" />
                                    <span>{item.title}</span>
                                    <ChevronRight className="ml-auto h-4 w-4 text-white/60 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <SidebarMenuSub className="ml-4 border-l border-blue-400/20 pl-3">
                                        {item.items.map((subItem, subIndex) => (
                                            <SidebarMenuSubItem key={subIndex}>
                                                <SidebarMenuSubButton
                                                    render={
                                                        <Link
                                                            href={subItem.href}
                                                            className={`my-0.5 flex w-full items-center rounded-md px-3 py-2 text-[14px] transition-colors duration-200 ${
                                                                isActive(
                                                                    subItem.href,
                                                                )
                                                                    ? 'bg-[#0078d4] font-medium text-white shadow-sm'
                                                                    : 'text-white/70 hover:bg-white/5 hover:text-white'
                                                            }`}
                                                        />
                                                    }
                                                >
                                                    {subItem.title}
                                                </SidebarMenuSubButton>
                                            </SidebarMenuSubItem>
                                        ))}
                                    </SidebarMenuSub>
                                </CollapsibleContent>
                            </Collapsible>
                        </SidebarMenuItem>
                    ))}
                </SidebarMenu>
            </SidebarContent>

            <SidebarFooter className="border-t border-blue-400/20 px-3 py-3">
                <AlertDialog>
                    <AlertDialogTrigger
                        render={
                            <button className="flex w-full items-center gap-2 rounded-lg px-3 py-2.5 text-sm text-red-300 transition-colors hover:bg-red-500/20 hover:text-red-200" />
                        }
                    >
                        <LogOut className="h-4 w-4" />
                        Logout
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Are you sure?</AlertDialogTitle>
                            <AlertDialogDescription>
                                You will be logged out of your account.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                            <AlertDialogAction
                                onClick={() => router.post('/logout')}
                            >
                                Logout
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </SidebarFooter>
        </Sidebar>
    );
}
