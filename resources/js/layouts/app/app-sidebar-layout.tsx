import { Link, usePage, router } from '@inertiajs/react';
import {
    HandCoins,
    Settings,
    Scale,
    ChevronRight,
    LogOut,
} from 'lucide-react';
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
import { Collapsible, CollapsibleTrigger, CollapsibleContent } from '@/components/ui/collapsible';
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
import { dashboard } from '@/routes';
import type { AppLayoutProps } from '@/types';

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
    { title: 'Profile Settings', href: '#profile-settings', icon: Settings },
    {
        title: 'Graduate Ledger',
        icon: HandCoins,
        items: [
            { title: 'Ledger Overview', href: '/graduate-ledger' },
            { title: 'Print Statement', href: '/graduate-ledger/print-select' },
        ],
    },
    {
        title: 'Law Ledger',
        icon: Scale,
        items: [
            { title: 'Law Overview', href: '#law-overview' },
            { title: 'Law Transactions', href: '#law-transactions' },
        ],
    },
];

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const { url } = usePage();

    return (
        <SidebarProvider>
            <Sidebar collapsible="icon" variant="inset">
                <SidebarHeader>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton size="lg" className="h-auto py-2" render={<Link href={dashboard.url()} prefetch />}>
                                <AppLogo />
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarHeader>

                <SidebarContent>
                    <SidebarMenu>
                        {mainNavItems.map((item) => {
                            const hasSubItems = item.items && item.items.length > 0;
                            
                            if (hasSubItems) {
                                return (
                                    <Collapsible key={item.title} className="group/collapsible">
                                        <SidebarMenuItem>
                                            <CollapsibleTrigger render={<SidebarMenuButton tooltip={item.title} />}>
                                                {item.icon && <item.icon />}
                                                <span>{item.title}</span>
                                                <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                                            </CollapsibleTrigger>
                                            <CollapsibleContent>
                                                <SidebarMenuSub>
                                                    {item.items?.map((subItem) => (
                                                        <SidebarMenuSubItem key={subItem.title}>
                                                            <SidebarMenuSubButton
                                                                isActive={url === subItem.href}
                                                                render={<Link href={subItem.href} />}
                                                            >
                                                                <span>{subItem.title}</span>
                                                            </SidebarMenuSubButton>
                                                        </SidebarMenuSubItem>
                                                    ))}
                                                </SidebarMenuSub>
                                            </CollapsibleContent>
                                        </SidebarMenuItem>
                                    </Collapsible>
                                );
                            }

                            return (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton
                                        isActive={url.startsWith(item.href || '')}
                                        tooltip={item.title}
                                        render={<Link href={item.href || '#'} prefetch />}
                                    >
                                        {item.icon && <item.icon />}
                                        <span>{item.title}</span>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            );
                        })}
                    </SidebarMenu>
                </SidebarContent>

                <SidebarFooter>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <AlertDialog>
                                <AlertDialogTrigger
                                    render={
                                        <SidebarMenuButton
                                            tooltip="Logout"
                                            className="group/logout flex w-full items-center gap-2 px-3 py-2 transition-all duration-200 hover:bg-red-600 focus-visible:bg-red-600"
                                        >
                                            <LogOut className="size-4 shrink-0 transition-all duration-200 group-hover/logout:scale-125 group-hover/logout:text-white" />
                                            <span className="text-sm font-medium transition-all duration-200 group-hover/logout:scale-105 group-hover/logout:text-white group-data-[collapsible=icon]:hidden">
                                                Logout
                                            </span>
                                        </SidebarMenuButton>
                                    }
                                />
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>Are you sure you want to log out?</AlertDialogTitle>
                                        <AlertDialogDescription>
                                            You will need to sign back in to access your dashboard and manage ledgers.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                                        <AlertDialogAction
                                            onClick={() => router.post('/logout')}
                                            className="bg-red-600 text-white hover:bg-red-700 transition-all duration-200 hover:scale-105"
                                        >
                                            Log Out
                                        </AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarFooter>
            </Sidebar>

            <SidebarInset className="overflow-x-hidden">
                <header className="flex h-14 shrink-0 items-center gap-2 border-b px-4">
                    <SidebarTrigger className="-ml-1" />
                    <Separator orientation="vertical" className="mr-2 h-4" />
                    {breadcrumbs.length > 0 && (
                        <nav className="flex items-center gap-1.5 text-sm text-muted-foreground">
                            {breadcrumbs.map((crumb, i) => (
                                <span key={i} className="flex items-center gap-1.5">
                                    {i > 0 && <span>/</span>}
                                    {crumb.href ? (
                                        <Link href={crumb.href} className="hover:text-foreground">
                                            {crumb.title}
                                        </Link>
                                    ) : (
                                        <span className="text-foreground">{crumb.title}</span>
                                    )}
                                </span>
                            ))}
                        </nav>
                    )}
                </header>
                {children}
            </SidebarInset>
        </SidebarProvider>
    );
}