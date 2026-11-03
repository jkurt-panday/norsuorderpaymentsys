import { Link, router, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Users,
    Activity,
    Home,
    FileText,
    CreditCard,
    Building,
    Scale,
    GraduationCap,
    Logs,
    Shield,
    School,
    ChevronRight,
    LogOut,
    FileSpreadsheet,
    FileSearchIcon,
} from 'lucide-react';
import React from 'react';
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
    Collapsible,
    CollapsibleTrigger,
    CollapsibleContent,
} from '@/components/ui/collapsible';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubItem,
    SidebarMenuSubButton,
} from '@/components/ui/sidebar';

interface SidebarItem {
    title: string;
    href: string;
    icon: React.ComponentType<any>;
}

const adminNavItems: SidebarItem[] = [
    { title: 'Admin Dashboard', href: '/admin/dashboard', icon: BarChart3 },
    { title: 'User Management', href: '/admin/users', icon: Users },
    { title: 'Activity Log', href: '/admin/activity-log', icon: Activity },
];

const staffNavItems: SidebarItem[] = [
    { title: 'Dashboard', href: '/staff/staffdashboard', icon: Home },
    { title: 'Activity Log', href: '/staff/activity-log', icon: Activity },
];

const cashierNavItems: SidebarItem[] = [
    { title: 'Requests', href: '/cashier/requests', icon: FileText },
];

const clientNavItems: SidebarItem[] = [
    { title: 'My Dashboard', href: '/client/dashboard', icon: Home },
    { title: 'Submit Payment Request', href: '/public/opform', icon: FileText },
];

const staffCollapsibleItems = [
    {
        title: 'Order of Payment',
        icon: FileSearchIcon,
        items: [
            { title: 'Requests', href: '/staff/requests', icon: FileText },
            { title: 'Bank Accounts', href: '/staff/bank-accounts', icon: Building },
            { title: 'Payment Options', href: '/staff/payment-options', icon: CreditCard },
            { title: 'Memberships', href: '/staff/memberships', icon: Users },
            { title: 'Authorized Officials', href: '/staff/authorized-officials', icon: Shield },
            { title: 'UACS', href: '/staff/uacs', icon: Logs },
            { title: 'Course', href: '/staff/courses', icon: School },
        ],
    },
    {
        title: 'Assessments',
        icon: FileSpreadsheet,
        items: [
            { title: 'Dashboard', href: '/staff/assessments/dashboard' },
            { title: 'Assessments', href: '/staff/assessments' },
        ],
    },
    {
        title: 'Graduate Ledger',
        icon: GraduationCap,
        items: [
            { title: 'Ledger Overview', href: '/graduate-ledger' },
            { title: 'Print Statement', href: '/graduate-ledger/print-select' },
        ],
    },
    {
        title: 'Law Ledger',
        icon: Scale,
        items: [
            { title: 'Law Overview', href: '/law-ledger' },
            { title: 'Print Statement', href: '/law-ledger/print-select' },
        ],
    },
];

const navigationHrefs = [
    ...adminNavItems.map((item) => item.href),
    ...staffNavItems.map((item) => item.href),
    ...cashierNavItems.map((item) => item.href),
    ...clientNavItems.map((item) => item.href),
    ...staffCollapsibleItems.flatMap((item) =>
        item.items.map((subItem) => subItem.href),
    ),
];

const SIDEBAR_COLLAPSIBLE_STORAGE_KEY = 'norsu.sidebar.collapsible.open';

const readStoredCollapsibleState = (): Record<string, boolean> => {
    if (typeof window === 'undefined') {
        return {};
    }

    try {
        const stored = window.localStorage.getItem(
            SIDEBAR_COLLAPSIBLE_STORAGE_KEY,
        );

        if (!stored) {
            return {};
        }

        const parsed = JSON.parse(stored);

        return parsed && typeof parsed === 'object'
            ? (parsed as Record<string, boolean>)
            : {};
    } catch {
        return {};
    }
};

export function AppSidebar() {
    const { url, props } = usePage<{
        auth?: { user?: { role?: string } | null };
    }>();
    const role = props?.auth?.user?.role;
    const isAdmin = role === 'admin';
    const isCashier = role === 'cashier';
    const isClient = role === 'client';
    const portalLabel = isAdmin
        ? 'Admin Portal'
        : isCashier
          ? 'Cashier Portal'
          : isClient
            ? 'Client Portal'
            : 'Staff Portal';

    const currentPath = url.split('?')[0].replace(/\/$/, '') || '/';
    const activeHref = navigationHrefs
        .filter(
            (href) =>
                currentPath === href || currentPath.startsWith(`${href}/`),
        )
        .sort((left, right) => right.length - left.length)[0];
    const isActive = (href: string) => activeHref === href;
    const activeCollapsibleTitles = React.useMemo(
        () =>
            new Set(
                staffCollapsibleItems
                    .filter((item) =>
                        item.items.some(
                            (subItem) =>
                                currentPath === subItem.href ||
                                currentPath.startsWith(`${subItem.href}/`),
                        ),
                    )
                    .map((item) => item.title),
            ),
        [currentPath],
    );
    const [openCollapsibles, setOpenCollapsibles] = React.useState<
        Record<string, boolean>
    >(() => ({
        ...readStoredCollapsibleState(),
        ...Object.fromEntries(
            [...activeCollapsibleTitles].map((title) => [title, true]),
        ),
    }));

    const setCollapsibleOpen = React.useCallback(
        (title: string, open: boolean) => {
            setOpenCollapsibles((previous) => {
                const next = {
                    ...previous,
                    [title]: open,
                };

                if (typeof window !== 'undefined') {
                    window.localStorage.setItem(
                        SIDEBAR_COLLAPSIBLE_STORAGE_KEY,
                        JSON.stringify(next),
                    );
                }

                return next;
            });
        },
        [],
    );

    React.useEffect(() => {
        if (activeCollapsibleTitles.size === 0) {
            return;
        }

        setOpenCollapsibles((previous) => {
            const next = {
                ...previous,
            };
            let changed = false;

            for (const title of activeCollapsibleTitles) {
                if (!next[title]) {
                    next[title] = true;
                    changed = true;
                }
            }

            if (changed && typeof window !== 'undefined') {
                window.localStorage.setItem(
                    SIDEBAR_COLLAPSIBLE_STORAGE_KEY,
                    JSON.stringify(next),
                );
            }

            return changed ? next : previous;
        });
    }, [activeCollapsibleTitles]);

    return (
        <Sidebar className="border-r border-white/20 bg-sidebar-primary text-sidebar-primary-foreground dark:border-sidebar-border dark:bg-sidebar dark:text-sidebar-foreground **:data-[sidebar=sidebar]:bg-transparent">
            <SidebarHeader className="border-b border-blue-400/20 px-4 py-5 dark:border-sidebar-border">
                <div className="flex flex-col items-center gap-2">
                    <img
                        src="/finance_logo1.png"
                        alt="NORSU Order of Payment System"
                        className="w-40 rounded-md object-contain"
                        onError={(e) => {
                            (e.target as HTMLImageElement).style.display =
                                'none';
                        }}
                    />
                    <div className="text-center">
                        <p className="text-sm font-semibold text-white dark:text-sidebar-foreground">
                            Order of Payment
                        </p>
                        <p className="text-xs text-white/70 dark:text-sidebar-foreground/70">{portalLabel}</p>
                    </div>
                </div>
            </SidebarHeader>

            <SidebarContent className="mt-2 px-2">
                <SidebarMenu>
                    {isClient ? (
                        clientNavItems.map((item, index) => (
                            <SidebarMenuItem key={index}>
                                <SidebarMenuButton
                                    render={
                                        <Link
                                            href={item.href}
                                            className={`my-0.5 flex w-full items-center gap-2 rounded-lg px-3 py-2.5 text-[15px] transition-colors duration-200 ${
                                                isActive(item.href)
                                                    ? 'bg-white font-medium text-blue-500 shadow-sm dark:bg-sky-400/20 dark:text-sky-300 dark:shadow-none'
                                                    : 'text-white hover:bg-white/15 hover:text-white dark:text-sidebar-foreground/80 dark:hover:bg-sidebar-accent dark:hover:text-sidebar-foreground'
                                            }`}
                                        />
                                    }
                                >
                                    <item.icon className="mr-2 h-4 w-4 shrink-0" />
                                    <span className="text-[1rem]">
                                        {item.title}
                                    </span>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        ))
                    ) : (
                        <>
                            {isAdmin && (
                                <>
                                    <div className="mb-2 px-2 py-1 text-xl font-semibold tracking-wider text-white/90 uppercase dark:text-sidebar-foreground/70">
                                        Admin
                                    </div>
                                    {adminNavItems.map((item, index) => (
                                        <SidebarMenuItem key={index}>
                                            <SidebarMenuButton
                                                render={
                                                    <Link
                                                        href={item.href}
                                                        className={`my-0.5 flex w-full items-center gap-2 rounded-lg px-3 py-2.5 text-[15px] transition-colors duration-200 ${
                                                            isActive(item.href)
                                                                ? 'bg-white font-medium text-blue-500 shadow-sm dark:bg-sky-400/20 dark:text-sky-300 dark:shadow-none'
                                                                : 'text-white hover:bg-white/15 hover:text-white dark:text-sidebar-foreground/80 dark:hover:bg-sidebar-accent dark:hover:text-sidebar-foreground'
                                                        }`}
                                                    />
                                                }
                                            >
                                                <item.icon className="mr-2 h-4 w-4 shrink-0" />
                                                <span className="text-[1rem]">
                                                    {item.title}
                                                </span>
                                            </SidebarMenuButton>
                                        </SidebarMenuItem>
                                    ))}
                                </>
                            )}

                            <div
                                className={`mb-2 px-2 py-1 text-xl font-semibold tracking-wider text-white/90 uppercase dark:text-sidebar-foreground/70 ${
                                    isAdmin ? 'mt-4' : ''
                                }`}
                            >
                                {isCashier ? 'Cashier' : 'Staff'}
                            </div>
                            {(isCashier ? cashierNavItems : staffNavItems)
                                .filter(
                                    (item) =>
                                        !isAdmin ||
                                        item.href !== '/staff/activity-log',
                                )
                                .map((item, index) => (
                                    <SidebarMenuItem key={index}>
                                        <SidebarMenuButton
                                            render={
                                                <Link
                                                    href={item.href}
                                                    className={`my-0.5 flex w-full items-center gap-2 rounded-lg px-3 py-2.5 text-[15px] transition-colors duration-200 ${
                                                        isActive(item.href)
                                                            ? 'bg-white font-medium text-blue-500 shadow-sm dark:bg-sky-400/20 dark:text-sky-300 dark:shadow-none'
                                                            : 'text-white hover:bg-white/15 hover:text-white dark:text-sidebar-foreground/80 dark:hover:bg-sidebar-accent dark:hover:text-sidebar-foreground'
                                                    }`}
                                                />
                                            }
                                        >
                                            <item.icon className="mr-2 h-4 w-4 shrink-0" />
                                            <span className="text-[1rem]">
                                                {item.title}
                                            </span>
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                ))}

                            {!isCashier &&
                                staffCollapsibleItems.map((item, index) => (
                                    <SidebarMenuItem key={index}>
                                        <Collapsible
                                            className="group/collapsible"
                                            open={Boolean(
                                                openCollapsibles[item.title],
                                            )}
                                            onOpenChange={(open) =>
                                                setCollapsibleOpen(
                                                    item.title,
                                                    open,
                                                )
                                            }
                                        >
                                            <CollapsibleTrigger
                                                render={
                                                    <SidebarMenuButton className="my-0.5 rounded-lg px-3 py-2.5 text-[15px] font-medium text-white hover:bg-white/5 hover:text-white dark:text-sidebar-foreground dark:hover:bg-sidebar-accent dark:hover:text-sidebar-foreground" />
                                                }
                                            >
                                                <item.icon className="mr-2 h-4 w-4 shrink-0" />
                                                <span className="text-[1rem]">
                                                    {item.title}
                                                </span>
                                                <ChevronRight className="ml-auto h-4 w-4 text-white/65 transition-transform duration-200 group-data-open/collapsible:rotate-90 dark:text-sidebar-foreground/50" />
                                            </CollapsibleTrigger>
                                            <CollapsibleContent>
                                                <SidebarMenuSub className="ml-4 border-l border-blue-400/20 pl-3 dark:border-sidebar-border">
                                                    {item.items.map(
                                                        (subItem, subIndex) => (
                                                            <SidebarMenuSubItem
                                                                key={subIndex}
                                                            >
                                                                <SidebarMenuSubButton
                                                                    render={
                                                                        <Link
                                                                            href={
                                                                                subItem.href
                                                                            }
                                                                            className={`my-0.5 flex w-full items-center rounded-md px-3 py-2 text-[14px] transition-colors duration-200 ${
                                                                                isActive(
                                                                                    subItem.href,
                                                                                )
                                                                                    ? 'bg-white font-medium text-blue-500 dark:bg-sky-400/20 dark:text-sky-300 dark:shadow-none'
                                                                                    : 'text-white/90 hover:bg-white/15 hover:text-white dark:text-sidebar-foreground/80 dark:hover:bg-sidebar-accent dark:hover:text-sidebar-foreground'
                                                                            }`}
                                                                        />
                                                                    }
                                                                >
                                                                    {subItem.title}
                                                                </SidebarMenuSubButton>
                                                            </SidebarMenuSubItem>
                                                        ),
                                                    )}
                                                </SidebarMenuSub>
                                            </CollapsibleContent>
                                        </Collapsible>
                                    </SidebarMenuItem>
                                ))}
                        </>
                    )}
                </SidebarMenu>
            </SidebarContent>

            <SidebarFooter className="border-t border-blue-400/20 px-3 py-3 dark:border-sidebar-border">
                <AlertDialog>
                    <AlertDialogTrigger
                        render={
                            <button className="flex w-full items-center gap-2 rounded-lg bg-blue-600 px-3 py-2.5 text-sm text-red-300 transition-colors hover:bg-red-50 hover:text-red-400" />
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
