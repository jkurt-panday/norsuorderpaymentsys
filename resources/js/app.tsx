import { createInertiaApp, router } from '@inertiajs/react';
import { useEffect } from 'react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { flashToast } from '@/utils/flashToast';

import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { ConfirmProvider } from '@/components/confirm-dialog';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Radix (used by shadcn's Dialog/Select/Dropdown/etc) sets
// `pointer-events: none` on <body> while a component is open, and removes
// it on close. If an Inertia navigation happens before that cleanup runs
// (e.g. a dialog triggers a redirect), the style can get stuck on <body>,
// making the next page completely unclickable. Clearing it on every
// navigation guarantees it never lingers.
router.on('navigate', () => {
    document.body.style.pointerEvents = '';
});

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob('./pages/**/*.tsx'),
        ),
    layout: (name) => {
        switch (true) {
            case name === 'welcome' ||
                name === 'auth/login' ||
                name === 'auth/forgot-password' ||
                name === 'auth/reset-password':
                return null;
            case name === 'SubmitForm':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        const FlashToastBridge = () => {
            useEffect(() => {
                const showFlash = () => {
                    const page = (window as any).__inertia?.page;
                    const flash = page?.props?.flash as
                        { success?: string; error?: string } | undefined;
                    if (!flash) return;

                    if (flash.success) flashToast('success', flash.success);
                    if (flash.error) flashToast('error', flash.error);
                };

                // Show any flash present on initial load
                showFlash();

                // Also show flashes after Inertia navigations
                router.on('navigate', () => setTimeout(showFlash, 0));

                // no cleanup required for router.on in this environment
            }, []);

            return null;
        };

        return (
            <TooltipProvider delayDuration={0}>
                <ConfirmProvider>
                    <FlashToastBridge />
                    {app}
                    <Toaster />
                </ConfirmProvider>
            </TooltipProvider>
        );
    },
    progress: {
        color: '#2563EB',
    },
});

// This will set light / dark mode on load...
initializeTheme();
