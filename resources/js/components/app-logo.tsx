import { Building2 } from 'lucide-react';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-indigo-600 text-white dark:bg-indigo-500">
                <Building2 className="size-5" />
            </div>
            <div className="ml-2 grid flex-1 text-left text-sm leading-tight group-data-[collapsible=icon]:hidden">
                <span className="font-semibold leading-tight whitespace-normal break-words">
                    NORSU Payment of Order System
                </span>
                <span className="truncate text-xs text-muted-foreground leading-none mt-1">
                    Institutional Portal
                </span>
            </div>
        </>
    );
}
