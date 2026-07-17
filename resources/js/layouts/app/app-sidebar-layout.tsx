import { Link, usePage } from '@inertiajs/react';
import {
    GraduationCap,
    HandCoins,
    Receipt,
    Award,
    Settings,
    BookOpen,
    Scale,
    ChevronRight,
    FolderGit2
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
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
        icon: BookOpen,
        items: [
            { title: 'Ledger Overview', href: '#graduate-overview' },
            { title: 'Transactions List', href: '#graduate-transactions' },
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

const footerNavItems = [
    { title: 'Repository', href: 'https://github.com/laravel/react-starter-kit', icon: FolderGit2 },
    { title: 'Documentation', href: 'https://laravel.com/docs/starter-kits#react', icon: BookOpen },
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
                                                                isActive={url.startsWith(subItem.href)}
                                                                render={<Link href={subItem.href} prefetch />}
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

                        {/* 
                          * ==========================================
                          * COMMENT: ADD MORE NAVIGATION ITEMS HERE 
                          * ==========================================
                          * You can add a standard item:
                          * 
                          * <SidebarMenuItem>
                          *     <SidebarMenuButton tooltip="New Item" render={<Link href="#new-link" />}>
                          *         <IconComponent className="size-4" />
                          *         <span>New Item</span>
                          *     </SidebarMenuButton>
                          * </SidebarMenuItem>
                          * 
                          * Or a dropdown/collapsible item:
                          * 
                          * <Collapsible asChild className="group/collapsible">
                          *     <SidebarMenuItem>
                          *         <CollapsibleTrigger render={<SidebarMenuButton tooltip="New Dropdown" />}>
                          *             <IconComponent className="size-4" />
                          *             <span>New Dropdown</span>
                          *             <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                          *         </CollapsibleTrigger>
                          *         <CollapsibleContent>
                          *             <SidebarMenuSub>
                          *                 <SidebarMenuSubItem>
                          *                     <SidebarMenuSubButton render={<Link href="#sub-item-link" />}>
                          *                         <span>Sub Item Label</span>
                          *                     </SidebarMenuSubButton>
                          *                 </SidebarMenuSubItem>
                          *             </SidebarMenuSub>
                          *         </CollapsibleContent>
                          *     </SidebarMenuItem>
                          * </Collapsible>
                          * ==========================================
                          */}
                    </SidebarMenu>
                </SidebarContent>

                <SidebarFooter>
                    <SidebarMenu>
                        {footerNavItems.map((item) => (
                            <SidebarMenuItem key={item.title}>
                                <SidebarMenuButton
                                    tooltip={item.title}
                                    render={<a href={item.href} target="_blank" rel="noopener noreferrer" />}
                                >
                                    {item.icon && <item.icon />}
                                    <span>{item.title}</span>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        ))}
                    </SidebarMenu>
                    {/* NavUser dropdown goes here if you still want it */}
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