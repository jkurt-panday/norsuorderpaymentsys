import { usePage } from '@inertiajs/react';
import React from 'react';

interface AuthSplitLayoutProps {
    children: React.ReactNode;
}

export default function AuthSplitLayout({ children }: AuthSplitLayoutProps) {
    const { flash } = usePage().props as any;

    return (
        <div className="grid min-h-screen grid-cols-1 lg:grid-cols-12">

            {/* LEFT PANEL: Brand Panel — hardcoded NORSU azure so it stays on-brand regardless of theme */}
            <div className="relative hidden flex-col justify-between bg-[#007BFF] p-10 text-white lg:flex lg:col-span-5">
                <div className="absolute inset-0 bg-gradient-to-b from-[#007BFF] to-[#0F1E36]/60 pointer-events-none" />

                <div className="relative z-10 flex items-center gap-3">
                    <div className="h-10 w-10 rounded-full bg-white/20 flex items-center justify-center font-bold tracking-wider">
                        N
                    </div>
                    <span className="font-semibold text-lg tracking-tight">NORSU Order & Payment System</span>
                </div>

                <div className="relative z-10 mt-auto">
                    <blockquote className="space-y-2">
                        <p className="text-lg font-light leading-relaxed">
                            "Providing efficient, secure, and transparent digital ledger processing engines for the university community."
                        </p>
                        <footer className="text-sm opacity-80 font-mono">
                            Finance & Accounting Portal — 2026
                        </footer>
                    </blockquote>
                </div>
            </div>

            {/* RIGHT PANEL: Centered form, hardcoded lily-white so it stays on-brand regardless of theme */}
            <div className="flex flex-col items-center justify-center bg-[#F4F6FC] px-6 py-12 sm:px-8 lg:col-span-7 lg:px-16">

                <div className="w-full max-w-md space-y-6">

                    {flash?.success && (
                        <div className="rounded-md bg-emerald-50 p-4 text-sm text-emerald-800 border border-emerald-200 shadow-sm">
                            {flash.success}
                        </div>
                    )}
                    {flash?.error && (
                        <div className="rounded-md bg-destructive/10 p-4 text-sm text-destructive border border-destructive/20 shadow-sm">
                            {flash.error}
                        </div>
                    )}

                    {children}
                </div>
            </div>

        </div>
    );
}