import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

interface UseReloadCooldownOptions {
    only?: string[];
    durationSeconds?: number;
}

interface UseReloadCooldownReturn {
    reload: () => void;
    isReloading: boolean;
    cooldown: number;
    canReload: boolean;
}

export function useReloadCooldown({
    only,
    durationSeconds = 10,
}: UseReloadCooldownOptions = {}): UseReloadCooldownReturn {
    const [isReloading, setIsReloading] = useState(false);
    const [cooldown, setCooldown] = useState(0);
    const cooldownEndRef = useRef<number | null>(null);
    const intervalRef = useRef<NodeJS.Timeout | null>(null);

    const clearCooldownInterval = useCallback(() => {
        if (intervalRef.current) {
            clearInterval(intervalRef.current);
            intervalRef.current = null;
        }
    }, []);

    const startCooldown = useCallback(() => {
        clearCooldownInterval();
        const endTime = Date.now() + durationSeconds * 1000;
        cooldownEndRef.current = endTime;
        setCooldown(durationSeconds);

        intervalRef.current = setInterval(() => {
            const remaining = Math.max(0, Math.ceil((endTime - Date.now()) / 1000));
            setCooldown(remaining);
            if (remaining <= 0) {
                clearCooldownInterval();
                cooldownEndRef.current = null;
            }
        }, 200);
    }, [durationSeconds, clearCooldownInterval]);

    useEffect(() => {
        return () => {
            clearCooldownInterval();
        };
    }, [clearCooldownInterval]);

    const canReload = !isReloading && cooldown <= 0;

    const reload = useCallback(() => {
        if (!canReload) {
            return;
        }

        router.reload({
            only,
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                setIsReloading(true);
            },
            onFinish: () => {
                setIsReloading(false);
                startCooldown();
            },
            onError: () => {
                setIsReloading(false);
                startCooldown();
            },
        });
    }, [canReload, only, startCooldown]);

    return {
        reload,
        isReloading,
        cooldown,
        canReload,
    };
}
