import React from 'react';
import { ThemeToggle } from '@/components/theme-toggle';

interface AuthSplitLayoutProps {
    children: React.ReactNode;
}

export default function AuthSplitLayout({ children }: AuthSplitLayoutProps) {
    return (
        <div className="grid min-h-screen grid-cols-1 lg:grid-cols-[1.05fr_1fr]">
            {/* Left Brand Panel */}
            <aside className="relative hidden overflow-hidden bg-[#0B4F9C] p-10 lg:flex lg:flex-col lg:justify-between lg:p-14 dark:bg-[#0A2447]">
                {/* Background image */}
                <img
                    src="/images/norsu-campus.jpg"
                    alt=""
                    aria-hidden="true"
                    className="absolute inset-0 h-full w-full object-cover"
                />

                {/* Overlays matching mockup .panel::after */}
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-0 [background:linear-gradient(90deg,rgba(4,22,64,0.66)_0%,rgba(4,22,64,0.38)_18%,rgba(4,22,64,0.12)_42%,transparent_62%),radial-gradient(ellipse_at_62%_36%,transparent_40%,rgba(4,22,64,0.5)_100%),linear-gradient(180deg,rgba(11,79,156,0.3)_0%,rgba(11,79,156,0.14)_36%,rgba(6,40,78,0.74)_68%,rgba(4,24,48,0.96)_100%)] dark:[background:linear-gradient(90deg,rgba(4,22,64,0.75)_0%,rgba(4,22,64,0.45)_18%,rgba(4,22,64,0.18)_42%,transparent_62%),radial-gradient(ellipse_at_62%_36%,transparent_40%,rgba(4,22,64,0.65)_100%),linear-gradient(180deg,rgba(10,36,71,0.4)_0%,rgba(10,36,71,0.2)_36%,rgba(5,18,36,0.85)_68%,rgba(3,10,22,0.98)_100%)]"
                />

                {/* Brand row */}
                <div className="relative z-10 flex items-center gap-4">
                    <img
                        src="/norsu.png"
                        alt="NORSU seal"
                        className="h-[52px] w-auto"
                    />
                    <div className="h-[34px] w-0.5 bg-[#E5A93A]" />
                    <span className="font-heading font-semibold tracking-[0.14em] text-white">
                        NORSU FINANCE
                    </span>
                </div>

                {/* Hero copy */}
                <div className="relative z-10 my-auto py-8">
                    <h1 className="font-heading text-[clamp(28px,3vw,42px)] font-bold leading-[1.15] text-white">
                        Order of Payment System
                    </h1>
                    <span className="my-5 block h-1 w-14 rounded-sm bg-[#E5A93A]" />
                    <p className="max-w-md text-base leading-relaxed text-white/90 lg:text-[19px]">
                        Manage university payment orders in one place.
                    </p>
                </div>

                {/* Bottom note */}
                <div className="relative z-10 text-xs font-mono tracking-wider text-white/70">
                    Finance & Accounting Portal — 2026
                </div>
            </aside>

            {/* Right Panel */}
            <main className="relative flex flex-col items-center justify-center bg-[#F6F7F1] px-6 py-12 dark:bg-[#08111F]">
                <div className="absolute top-4 right-4">
                    <ThemeToggle />
                </div>

                <div className="w-full max-w-[410px]">
                    {children}
                </div>
            </main>
        </div>
    );
}