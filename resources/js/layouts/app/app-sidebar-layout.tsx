import React from 'react';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import {
    SidebarInset,
    SidebarProvider,
} from '@/components/ui/sidebar';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <SidebarProvider>
            <div className="flex h-screen w-full bg-background">
                <AppSidebar />
                <SidebarInset className="overflow-hidden bg-background">
                    <AppSidebarHeader breadcrumbs={breadcrumbs} />
                    <main className="min-h-0 flex-1 overflow-auto bg-page-background p-4 md:p-8">
                        {children}
                    </main>
                </SidebarInset>
            </div>
        </SidebarProvider>
    );
}