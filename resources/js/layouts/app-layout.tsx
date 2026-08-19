import { usePage } from '@inertiajs/react';
import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import ClientLayoutTemplate from '@/layouts/client/layout';
import type { BreadcrumbItem } from '@/types';

export default function AppLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    const { auth } = usePage<{ auth?: { user?: { role?: string } | null } }>().props;
    const isClient = auth?.user?.role === 'client';

    const LayoutComponent = isClient ? ClientLayoutTemplate : AppLayoutTemplate;

    return (
        <LayoutComponent breadcrumbs={breadcrumbs}>
            {children}
        </LayoutComponent>
    );
}
