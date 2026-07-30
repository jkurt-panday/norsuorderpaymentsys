import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';

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
        resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
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
        return (
            <TooltipProvider delayDuration={0}>
                <ConfirmProvider>
                    {app}
                    <Toaster />
                </ConfirmProvider>
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
