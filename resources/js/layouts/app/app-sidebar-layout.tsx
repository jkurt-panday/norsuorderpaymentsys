import { Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import React from 'react';
import ClientLayoutTemplate from '@/layouts/client/layout';
import { Separator } from '@/components/ui/separator';
import {
    SidebarInset,
    SidebarProvider,
    SidebarTrigger,
} from '@/components/ui/sidebar';
import type { AppLayoutProps, BreadcrumbItem } from '@/types';
import UnifiedSidebar from '@/layouts/unified-sidebar';

function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItem[];
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

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const { auth } = usePage<{ auth?: { user?: { role?: string } | null } }>().props;
    const isClient = auth?.user?.role === 'client';

    if (isClient) {
        return (
            <SidebarProvider>
                <div className="flex h-screen w-full bg-[#FAFAF5]">
                    <ClientLayoutTemplate>{children}</ClientLayoutTemplate>
                </div>
            </SidebarProvider>
        );
    }

    return (
        <SidebarProvider>
            <div className="flex h-screen w-full bg-[#FAFAF5]">
                <UnifiedSidebar />
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
