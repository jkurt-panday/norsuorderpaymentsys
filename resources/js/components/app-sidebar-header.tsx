import { Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { ThemeToggle } from '@/components/theme-toggle';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItem[];
}) {
    const { auth } = usePage().props as unknown as {
        auth?: { user?: { name?: string } };
    };
    const userName = auth?.user?.name ?? '';

    return (
        <div className="flex h-16 items-center gap-4 border-b border-white/20 bg-primary px-6 text-primary-foreground dark:border-sidebar-border dark:bg-sidebar dark:text-sidebar-foreground">
            <SidebarTrigger className="text-primary-foreground hover:bg-white/10 hover:text-primary-foreground dark:text-sidebar-foreground dark:hover:bg-sidebar-accent dark:hover:text-sidebar-foreground" />
            {/* <Separator orientation="vertical" className="h-4 bg-white/20 dark:bg-sidebar-border" /> */}
            {breadcrumbs.length > 0 && (
                <nav className="flex items-center gap-2 text-sm">
                    {breadcrumbs.map((crumb, index) => (
                        <div key={index} className="flex items-center gap-2">
                            {index > 0 && (
                                <ChevronRight className="h-3 w-3 text-white/90 dark:text-sidebar-foreground/50" />
                            )}
                            {crumb.href ? (
                                <Link
                                    href={crumb.href}
                                    className="text-primary-foreground hover:text-primary-foreground/90 dark:text-sidebar-foreground/90 dark:hover:text-sidebar-foreground"
                                >
                                    {crumb.title}
                                </Link>
                            ) : (
                                <span className="text-primary-foreground/90 dark:text-sidebar-foreground/70">
                                    {crumb.title}
                                </span>
                            )}
                        </div>
                    ))}
                </nav>
            )}
            {userName && (
                <span
                    className="ml-auto hidden truncate text-sm font-medium text-white/90 dark:text-sidebar-foreground/80 sm:block"
                    aria-label={`Logged in as ${userName}`}
                >
                    Hello, {userName}!
                </span>
            )}
            <ThemeToggle
                className={`${userName ? 'ml-auto sm:ml-3' : 'ml-auto'} text-white hover:bg-white/15 hover:text-white dark:text-sidebar-foreground dark:hover:bg-sidebar-accent dark:hover:text-sidebar-foreground`}
            />
        </div>
    );
}
