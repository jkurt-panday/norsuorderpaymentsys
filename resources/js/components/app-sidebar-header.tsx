import { Link } from '@inertiajs/react';
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
    return (
        <div className="flex h-16 items-center gap-4 border-b border-white/20 bg-[#007FFF] px-6 text-white dark:border-sidebar-border dark:bg-sidebar dark:text-sidebar-foreground">
            <SidebarTrigger className="text-white hover:bg-white/15 hover:text-white dark:text-sidebar-foreground dark:hover:bg-sidebar-accent dark:hover:text-sidebar-foreground" />
            {/* <Separator orientation="vertical" className="h-4 bg-white/20 dark:bg-sidebar-border" /> */}
            {breadcrumbs.length > 0 && (
                <nav className="flex items-center gap-2 text-sm">
                    {breadcrumbs.map((crumb, index) => (
                        <div key={index} className="flex items-center gap-2">
                            {index > 0 && (
                                <ChevronRight className="h-3 w-3 text-white/70 dark:text-sidebar-foreground/50" />
                            )}
                            {crumb.href ? (
                                <Link
                                    href={crumb.href}
                                    className="text-white hover:text-white/80 dark:text-sidebar-foreground/90 dark:hover:text-sidebar-foreground"
                                >
                                    {crumb.title}
                                </Link>
                            ) : (
                                <span className="text-white/80 dark:text-sidebar-foreground/70">
                                    {crumb.title}
                                </span>
                            )}
                        </div>
                    ))}
                </nav>
            )}
            <ThemeToggle className="ml-auto text-white hover:bg-white/15 hover:text-white dark:text-sidebar-foreground dark:hover:bg-sidebar-accent dark:hover:text-sidebar-foreground" />
        </div>
    );
}
