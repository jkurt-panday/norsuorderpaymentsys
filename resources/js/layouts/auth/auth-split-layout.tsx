import React from 'react';
import { usePage } from '@inertiajs/react';

interface AuthSplitLayoutProps {
    children: React.ReactNode;
}

export default function AuthSplitLayout({ children }: AuthSplitLayoutProps) {
    const { flash } = usePage().props as any;

    return (
        <div className="grid min-h-screen grid-cols-1 lg:grid-cols-12">
            
            {/* LEFT PANEL: Brand Panel (Hidden on small screens, spans 5 cols on large screens) */}
            <div className="relative hidden flex-col justify-between bg-primary p-10 text-white lg:flex lg:col-span-5">
                {/* Background overlay patterns can go here */}
                <div className="absolute inset-0 bg-gradient-to-b from-primary/50 to-primary-foreground/30 pointer-events-none" />
                
                <div className="relative z-10 flex items-center gap-3">
                    {/* Placeholder for NORSU Logo graphic */}
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

            {/* RIGHT PANEL: Dynamic Action Content (Spans full width on mobile, 7 cols on large screens) */}
            <div className="flex flex-col justify-center bg-background px-6 py-12 sm:px-8 lg:col-span-7 lg:px-16">
                
                {/* Global Notification Pipeline Area */}
                {flash?.success && (
                    <div className="mx-auto w-full max-w-md mb-6 rounded-md bg-emerald-50 p-4 text-sm text-emerald-800 border border-emerald-200 shadow-sm animate-in fade-in-50 duration-200">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="mx-auto w-full max-w-md mb-6 rounded-md bg-destructive/10 p-4 text-sm text-destructive border border-destructive/20 shadow-sm animate-in fade-in-50 duration-200">
                        {flash.error}
                    </div>
                )}

                {/* Form Injection Port */}
                <div className="mx-auto w-full max-w-md space-y-6">
                    {children}
                </div>
            </div>
            
        </div>
    );
}