import { useEffect } from 'react';

export default function PublicLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    useEffect(() => {
        document.documentElement.classList.remove('dark');
        document.documentElement.style.colorScheme = 'light';
    }, []);

    return <main className="min-h-screen w-full">{children}</main>;
}
