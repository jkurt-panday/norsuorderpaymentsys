import { Link } from '@inertiajs/react';
import { Landmark, LockKeyhole } from 'lucide-react';
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
        <div className="min-h-svh flex flex-col bg-background text-foreground">
            <header className="w-full bg-white border-b border-border z-50">
                <div className="flex items-center justify-between px-6 md:px-10 py-4 max-w-[1280px] mx-auto">
                    <Link href={home()} className="flex items-center gap-2">
                        <Landmark className="text-[#0d51b5] w-7 h-7" />
                        <div className="h-8 w-px bg-border mx-2"></div>
                        <h1 className="text-2xl md:text-3xl font-semibold text-[#0d51b5] leading-none">
                            University Accounting Office
                        </h1>
                    </Link>
                </div>
            </header>

            <main className="flex-grow flex items-center justify-center relative py-12 px-4 md:px-10">
                <div className="absolute inset-0 z-0 overflow-hidden opacity-40 pointer-events-none">
                    <div className="absolute top-0 right-0 w-1/3 h-1/3 bg-[#0d51b5]/10 rounded-full blur-3xl -mr-20 -mt-20"></div>
                    <div className="absolute bottom-0 left-0 w-1/2 h-1/2 bg-[#0d51b5]/5 rounded-full blur-3xl -ml-40 -mb-40"></div>
                </div>

                <div className="z-10 w-full max-w-md">
                    <Card className="bg-white border border-border rounded-xl shadow-xl p-6 md:p-8">
                        <CardHeader className="text-center pb-6">
                            <div className="flex justify-center">
                                <LockKeyhole className="text-[#0d51b5] w-12 h-12 mb-3" />
                            </div>
                            <CardTitle className="text-2xl font-bold text-foreground">
                                {title}
                            </CardTitle>
                            <CardDescription className="text-muted-foreground text-sm mt-1">
                                {description}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            {children}
                        </CardContent>
                    </Card>
                </div>

                <div className="hidden lg:block absolute right-12 top-1/2 -translate-y-1/2 w-80 h-96 opacity-20 pointer-events-none">
                    <div className="w-full h-full border-2 border-[#0d51b5]/20 rounded-xl p-4 rotate-3">
                        <div className="w-full h-full border-2 border-[#0d51b5]/20 rounded-xl p-4 -rotate-6">
                            <div className="w-full h-full border-2 border-[#0d51b5]/20 rounded-xl"></div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    );
}
