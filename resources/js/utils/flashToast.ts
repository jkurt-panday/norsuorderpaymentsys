import { toast } from 'sonner';
import type { ReactNode } from 'react';

export type FlashToastType = 'success' | 'info' | 'warning' | 'error';

const toastStyles: Record<FlashToastType, React.CSSProperties> = {
    success: {
        backgroundColor: '#003f7d',
        borderColor: '#002d5b',
        color: '#f8fafc',
    },
    error: {
        backgroundColor: '#dc2626',
        borderColor: '#b91c1c',
        color: '#f8fafc',
    },
    warning: {
        backgroundColor: '#f59e0b',
        borderColor: '#d97706',
        color: '#0f172a',
    },
    info: {
        backgroundColor: '#0f172a',
        borderColor: '#334155',
        color: '#f8fafc',
    },
};

export function flashToast(type: FlashToastType, message?: string | ReactNode) {
    if (!message) {
        return;
    }

    const id = `${type}:${typeof message === 'string' ? message : 'flash'}`;

    toast[type](message, {
        id,
        className: `cn-toast cn-toast-${type}`,
        style: toastStyles[type],
    });
}
