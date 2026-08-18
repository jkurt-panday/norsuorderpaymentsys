import ClientLayoutTemplate from '@/layouts/client/layout';
import type { BreadcrumbItem } from '@/types';

export default function ClientLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    return (
        <ClientLayoutTemplate breadcrumbs={breadcrumbs}>
            {children}
        </ClientLayoutTemplate>
    );
}
