import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';
import { cn } from '@/lib/utils';

export function NavMain({ items = [] }: { items: NavItem[] }) {
    const { isCurrentUrl } = useCurrentUrl();
    const { state } = useSidebar();
    const isCollapsed = state === 'collapsed';

    return (
        <SidebarGroup className="px-0 py-0">
            <SidebarMenu>
                {items.map((item) => {
                    const isActive = isCurrentUrl(item.href);
                    return (
                        <div key={item.title}>
                            {item.separatorBefore && (
                                <hr className="my-2 mx-2.5 border-blue-400/20" />
                            )}
                                <SidebarMenuItem>
                                    <SidebarMenuButton
                                        render={<Link href={item.href} prefetch />}
                                        isActive={isActive}
                                        className={cn(
                                            "mx-2.5 my-1 rounded-lg px-5 py-2.5 text-[17px] transition-colors duration-300 text-blue-100/80 hover:bg-white/10 hover:text-white data-[active=true]:bg-[#0078d4] data-[active=true]:text-white data-[active=true]:shadow-sm",
                                            isCollapsed && "justify-center px-2"
                                        )}
                                    >
                                        {item.icon && (
                                            <span className={cn(
                                                "inline-flex w-5 justify-center",
                                                isCollapsed ? "mr-0" : "mr-2.5"
                                            )}>
                                                <item.icon size={18} />
                                            </span>
                                        )}
                                        {!isCollapsed && <span>{item.title}</span>}
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                        </div>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}