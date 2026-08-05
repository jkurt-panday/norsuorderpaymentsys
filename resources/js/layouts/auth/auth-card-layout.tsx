import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';

export default function AuthCardLayout({
    children,
    title,
    description,
}: PropsWithChildren<{
    name?: string;
    title?: string;
    description?: string;
}>) {
    return (
        <div className="min-h-svh flex flex-col bg-[#f7f9ff] text-[#091d2e]">
            {/* TopAppBar */}
            <header className="w-full bg-white border-b border-[#c1c6d7] z-50">
                <div className="flex items-center justify-between px-6 md:px-10 py-4 max-w-[1280px] mx-auto">
                    <Link href={home()} className="flex items-center gap-2">
                        <span className="material-symbols-outlined text-[#005ab7] text-3xl" style={{ fontVariationSettings: "'FILL' 1" }}>
                            account_balance
                        </span>
                        <div className="h-8 w-px bg-[#c1c6d7] mx-2"></div>
                        <h1 className="text-2xl md:text-3xl font-semibold text-[#005ab7] leading-none" style={{ fontFamily: "'Source Serif 4', serif" }}>
                            University Accounting Office
                        </h1>
                    </Link>
                </div>
            </header>

            {/* Main Content */}
            <main className="flex-grow flex items-center justify-center relative py-12 px-4 md:px-10">
                {/* Abstract background pattern */}
                <div className="absolute inset-0 z-0 overflow-hidden opacity-40 pointer-events-none">
                    <div className="absolute top-0 right-0 w-1/3 h-1/3 bg-[#0072e5]/10 rounded-full blur-3xl -mr-20 -mt-20"></div>
                    <div className="absolute bottom-0 left-0 w-1/2 h-1/2 bg-[#d9eaff]/20 rounded-full blur-3xl -ml-40 -mb-40"></div>
                </div>

                <div className="z-10 w-full max-w-md">
                    <Card className="bg-white border border-[#c1c6d7] rounded-xl shadow-xl p-6 md:p-8">
                        <CardHeader className="text-center pb-6">
                            <div className="flex justify-center">
                                <span className="material-symbols-outlined text-[#005ab7] text-5xl mb-3" style={{ fontVariationSettings: "'FILL' 1" }}>
                                    lock_person
                                </span>
                            </div>
                            <CardTitle className="text-2xl font-bold text-[#091d2e]" style={{ fontFamily: "'Source Serif 4', serif" }}>
                                {title}
                            </CardTitle>
                            <CardDescription className="text-[#414754] text-sm mt-1">
                                {description}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            {children}
                        </CardContent>
                    </Card>
                </div>

                {/* Visual Side Decoration */}
                <div className="hidden lg:block absolute right-12 top-1/2 -translate-y-1/2 w-80 h-96 opacity-20 pointer-events-none">
                    <div className="w-full h-full border-2 border-[#005ab7]/20 rounded-xl p-4 rotate-3">
                        <div className="w-full h-full border-2 border-[#005ab7]/20 rounded-xl p-4 -rotate-6">
                            <div className="w-full h-full border-2 border-[#005ab7]/20 rounded-xl"></div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    );
}