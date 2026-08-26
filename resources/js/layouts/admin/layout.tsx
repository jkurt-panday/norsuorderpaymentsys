import { Link, usePage, router } from '@inertiajs/react';
import { BarChart3, Users, Activity, ChevronRight, LogOut } from 'lucide-react';
import React from 'react';
import { Separator } from '@/components/ui/separator';
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
} from '@/components/ui/sidebar';
import type { AppLayoutProps } from '@/types';

interface AdminSidebarItem {
    title: string;
    href: string;
    icon: React.ComponentType<any>;
}

const adminNavItems: AdminSidebarItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard', icon: BarChart3 },
    { title: 'User Management', href: '/admin/users', icon: Users },
    { title: 'Activity Log', href: '/admin/activity-log', icon: Activity },
];

function AdminSidebar() {
    const { url } = usePage();

    const isActive = (href: string) => {
        const currentPath = url.split('?')[0];
        return currentPath === href || currentPath.startsWith(href + '/');
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
                            (e.target as HTMLImageElement).style.display = 'none';
                        }}
                    />
                    <div className="text-center">
                        <p className="text-sm font-semibold text-white">
                            Order of Payment
                        </p>
                        <p className="text-xs text-white/70">
                            Admin Portal
                        </p>
                    </div>
                </div>
            </SidebarHeader>

            {/* Nav Items */}
            <SidebarContent className="mt-2 px-2">
                <SidebarMenu>
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

function AdminSidebarHeader({
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

export default function AdminSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <SidebarProvider>
            <div className="flex h-screen w-full bg-[#FAFAF5]">
                <AdminSidebar />
                <SidebarInset className="overflow-hidden bg-[#FAFAF5]">
                    <AdminSidebarHeader breadcrumbs={breadcrumbs} />
                    <main className="min-h-0 flex-1 overflow-auto bg-[#FAFAF5] p-4 md:p-8">
                        {children}
                    </main>
                </SidebarInset>
            </div>
        </SidebarProvider>
    );
}