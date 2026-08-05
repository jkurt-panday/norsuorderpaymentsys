import logo from '@/assets/norsu.png';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md">
                <img
                    src={logo}
                    alt="NORSU Logo"
                    className="h-full w-full object-contain"
                />
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