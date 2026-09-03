'use client';

import type { ReactNode } from 'react';
import { toast } from 'sonner';

export type SoftFlashToastType = 'success' | 'info' | 'warning' | 'error';

/**
 * Soft color-mix styles, taken directly from the individual
 * Soft{Success,Info,Warning,Destructive}SonnerDemo components.
 */
const softToastVars: Record<SoftFlashToastType, React.CSSProperties> = {
    success: {
        '--normal-bg':
            'color-mix(in oklab, light-dark(var(--color-green-600), var(--color-green-400)) 10%, var(--background))',
        '--normal-text': 'light-dark(var(--color-green-600), var(--color-green-400))',
        '--normal-border': 'light-dark(var(--color-green-600), var(--color-green-400))',
    } as React.CSSProperties,
    info: {
        '--normal-bg':
            'color-mix(in oklab, light-dark(var(--color-sky-600), var(--color-sky-400)) 10%, var(--background))',
        '--normal-text': 'light-dark(var(--color-sky-600), var(--color-sky-400))',
        '--normal-border': 'light-dark(var(--color-sky-600), var(--color-sky-400))',
    } as React.CSSProperties,
    warning: {
        '--normal-bg':
            'color-mix(in oklab, light-dark(var(--color-amber-600), var(--color-amber-400)) 10%, var(--background))',
        '--normal-text': 'light-dark(var(--color-amber-600), var(--color-amber-400))',
        '--normal-border': 'light-dark(var(--color-amber-600), var(--color-amber-400))',
    } as React.CSSProperties,
    error: {
        '--normal-bg': 'color-mix(in oklab, var(--destructive) 10%, var(--background))',
        '--normal-text': 'var(--destructive)',
        '--normal-border': 'var(--destructive)',
    } as React.CSSProperties,
};

/** Same messages as the four demo components, used as fallbacks. */
const defaultMessages: Record<SoftFlashToastType, string> = {
    success: 'Action completed successfully!',
    info: 'This is for your information, please note.',
    warning: 'Warning: Please check the entered data.',
    error: 'Oops, there was an error processing your request.',
};

/**
 * Universal "soft" toast — uses the color-mix / light-dark pattern
 * from the Soft*SonnerDemo components, driven by a `type` string
 * instead of four separate hardcoded components.
 */
export function softFlashToast(type: SoftFlashToastType, message?: string | ReactNode) {
    const resolvedMessage = message ?? defaultMessages[type];

    const id = `soft-${type}:${typeof resolvedMessage === 'string' ? resolvedMessage : 'flash'}`;

    toast[type](resolvedMessage, {
        id,
        style: softToastVars[type],
    });
}