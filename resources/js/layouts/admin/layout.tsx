import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import React from 'react';
import { Separator } from '@/components/ui/separator';
import {
    SidebarInset,
    SidebarProvider,
    SidebarTrigger,
} from '@/components/ui/sidebar';
import UnifiedSidebar from '@/layouts/unified-sidebar';
import type { AppLayoutProps, BreadcrumbItem } from '@/types';

function AdminSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItem[];
}) {
    return (
        <div className="flex h-16 items-center gap-4 border-b border-white/20 bg-[#007FFF] px-6 text-white">
            <SidebarTrigger className="text-white hover:bg-white/15 hover:text-white" />
            {/* <Separator orientation="vertical" className="h-6" /> */}
            {breadcrumbs.length > 0 && (
                <nav className="flex items-center gap-2 text-sm">
                    {breadcrumbs.map((crumb, index) => (
                        <div key={index} className="flex items-center gap-2">
                            {index > 0 && (
                                <ChevronRight className="h-3 w-3 text-white/70" />
                            )}
                            {crumb.href ? (
                                <Link
                                    href={crumb.href}
                                    className="text-white hover:text-white/80"
                                >
                                    {crumb.title}
                                </Link>
                            ) : (
                                <span className="text-white/80">
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
                <UnifiedSidebar />
                <SidebarInset className="overflow-hidden bg-[#FAFAF5]">
                    <AdminSidebarHeader breadcrumbs={breadcrumbs} />
                    <main className="min-h-0 flex-1 overflow-auto bg-page-background p-4 md:p-8">
                        {children}
                    </main>
                </SidebarInset>
            </div>
        </SidebarProvider>
    );
}
