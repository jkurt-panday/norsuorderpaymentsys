import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';

type Flash = {
    success?: string | null;
    error?: string | null;
    warning?: string | null;
};

export function useFlashToast(): void {
    const { props } = usePage();
    const flash = (props as { flash?: Flash }).flash;
    const lastFlash = useRef('');

    useEffect(() => {
        const key = `${flash?.success ?? ''}|${flash?.error ?? ''}|${flash?.warning ?? ''}`;

        if (!key || key === lastFlash.current) {
            return;
        }

        lastFlash.current = key;

        if (flash?.success) {
            toast.success(flash.success);
        }

        if (flash?.warning) {
            toast.warning(flash.warning);
        }

        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash?.success, flash?.error, flash?.warning]);
}
