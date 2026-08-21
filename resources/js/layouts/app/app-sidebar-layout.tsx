import { Link, usePage, router } from '@inertiajs/react';
import {
    Home,
    FileText,
    Users,
    CreditCard,
    Building,
    Layers,
    Scale,
    ChevronRight,
    LogOut,
    GraduationCap,
    Logs,
    School,
    FileSearchIcon
} from 'lucide-react';
import React from 'react';
import AppLogo from '@/components/app-logo';
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
import { Separator } from '@/components/ui/separator';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarInset,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarProvider,
    SidebarTrigger,
    SidebarMenuSub,
    SidebarMenuSubItem,
    SidebarMenuSubButton,
} from '@/components/ui/sidebar';
import type { AppLayoutProps } from '@/types';
import { Form } from '@base-ui/react';

interface SidebarItem {
    title: string;
    href?: string;
    icon?: React.ComponentType<any>;
    items?: {
        title: string;
        href: string;
    }[];
}

const mainNavItems: SidebarItem[] = [
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
    {
        title: 'Assessment', icon: FileSearchIcon, items: [
            { title: 'Dashboard', href: '' },
            { title: 'Assessments', href: '' },
    ]},
    {
        title: 'Graduate Ledger',
        icon: GraduationCap,
        items: [
            { title: 'Ledger Overview', href: '/graduate-ledger' },
            { title: 'Print Statement', href: '/graduate-ledger/print-select' },
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

// Main Sidebar Component
function AppSidebar() {
    const { url } = usePage();

    const isActive = (href?: string) => {
        if (!href) return false;
        const currentPath = url.split('?')[0];
        if (currentPath === href) return true;
        if (currentPath.startsWith(href + '/')) {
            const hasMoreSpecificSibling = mainNavItems.some(item =>
                item.items?.some(sub =>
                    sub.href !== href &&
                    sub.href.startsWith(href + '/') &&
                    (currentPath === sub.href || currentPath.startsWith(sub.href + '/'))
                )
            );
            return !hasMoreSpecificSibling;
        }
        return false;
    };

    return (
        <Sidebar className="border-r border-blue-900/40 bg-[#003f7d] text-white [&_[data-sidebar=sidebar]]:bg-transparent">
            {/* Header */}
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
                        <p className="text-xs text-white/70">
                            NORSU Staff Portal
                        </p>
                    </div>
                </div>
            </SidebarHeader>

            {/* Nav Items */}
            <SidebarContent className="mt-2 px-2">
                <SidebarMenu>
                    {mainNavItems.map((item, index) => (
                        <SidebarMenuItem key={index}>
                            {item.items ? (
                                <Collapsible
                                    defaultOpen
                                    className="group/collapsible"
                                >
                                    <CollapsibleTrigger
                                        render={
                                            <SidebarMenuButton className="my-0.5 rounded-lg px-3 py-2.5 text-[15px] font-medium text-white hover:bg-white/5 hover:text-white" />
                                        }
                                    >
                                        {item.icon && (
                                            <item.icon className="mr-2 h-4 w-4 shrink-0" />
                                        )}
                                        <span>{item.title}</span>
                                        <ChevronRight className="ml-auto h-4 w-4 text-white/60 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                                    </CollapsibleTrigger>
                                    <CollapsibleContent>
                                        <SidebarMenuSub className="ml-4 border-l border-blue-400/20 pl-3">
                                            {item.items.map(
                                                (subItem, subIndex) => (
                                                    <SidebarMenuSubItem
                                                        key={subIndex}
                                                    >
                                                        <SidebarMenuSubButton
                                                            render={
                                                                <Link
                                                                    href={
                                                                        subItem.href
                                                                    }
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
                                                ),
                                            )}
                                        </SidebarMenuSub>
                                    </CollapsibleContent>
                                </Collapsible>
                            ) : (
                                <SidebarMenuButton
                                    render={
                                        <Link
                                            href={item.href || '#'}
                                            className={`my-0.5 flex w-full items-center gap-2 rounded-lg px-3 py-2.5 text-[15px] transition-colors duration-200 ${
                                                isActive(item.href)
                                                    ? 'bg-[#0078d4] font-medium text-white shadow-sm'
                                                    : 'text-white/80 hover:bg-white/5 hover:text-white'
                                            }`}
                                        />
                                    }
                                >
                                    {item.icon && (
                                        <item.icon className="mr-2 h-4 w-4 shrink-0" />
                                    )}
                                    <span>{item.title}</span>
                                </SidebarMenuButton>
                            )}
                        </SidebarMenuItem>
                    ))}
                </SidebarMenu>
            </SidebarContent>

            {/* Footer */}
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

// Header Component
function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: { title: string; href?: string }[];
}) {
    return (
        <div className="flex h-16 items-center gap-4 border-b px-6">
            <SidebarTrigger />
            <Separator orientation="vertical" className="h-6" />
            {breadcrumbs.length > 0 && (
                <nav className="flex items-center gap-2 text-sm">
                    {breadcrumbs.map((crumb, index) => (
                        <div key={index} className="flex items-center gap-2">
                            {index > 0 && (
                                <ChevronRight className="h-3 w-3 text-muted-foreground" />
                            )}
                            {crumb.href ? (
                                <Link
                                    href={crumb.href}
                                    className="hover:text-primary"
                                >
                                    {crumb.title}
                                </Link>
                            ) : (
                                <span className="text-muted-foreground">
                                    {crumb.title}
                                </span>
                            )}
                        </div>
                    ))}
                </nav>
            )}
        </div>
    );
}

// Main Layout Component
export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <SidebarProvider>
            <div className="flex h-screen w-full bg-[#FAFAF5]">
                <AppSidebar />
                <SidebarInset className="overflow-hidden bg-[#FAFAF5]">
                    <AppSidebarHeader breadcrumbs={breadcrumbs} />
                    <main className="min-h-0 flex-1 overflow-auto bg-[#FAFAF5] p-4 md:p-8">
                        {children}
                    </main>
                </SidebarInset>
            </div>
        </SidebarProvider>
    );
}
