import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

interface UseReloadCooldownOptions {
    only?: string[];
    durationSeconds?: number;
    /**
     * Optional custom action to run instead of router.reload. The hook still
     * owns the loading flag and cooldown — the callback just has to call
     * `finish()` once the request is done (or failed).
     */
    perform?: (finish: () => void) => void;
    /**
     * Whether the cooldown should survive a full page reload. Some resets
     * navigate to a clean URL which Inertia may turn into a full reload;
     * without persistence the countdown would vanish the instant the page
     * re-renders. Defaults to true, scoped to the current pathname so it
     * never leaks onto unrelated pages.
     */
    persist?: boolean;
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
    perform,
    persist = true,
}: UseReloadCooldownOptions = {}): UseReloadCooldownReturn {
    const [isReloading, setIsReloading] = useState(false);
    const [cooldown, setCooldown] = useState(0);
    const cooldownEndRef = useRef<number | null>(null);
    const intervalRef = useRef<NodeJS.Timeout | null>(null);

    const storageKey =
        persist && typeof window !== 'undefined'
            ? `reloadCooldownEnd:${window.location.pathname}`
            : null;

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

        if (storageKey && typeof window !== 'undefined') {
            window.sessionStorage.setItem(storageKey, String(endTime));
        }

        intervalRef.current = setInterval(() => {
            const remaining = Math.max(
                0,
                Math.ceil((endTime - Date.now()) / 1000),
            );
            setCooldown(remaining);

            if (remaining <= 0) {
                clearCooldownInterval();
                cooldownEndRef.current = null;

                if (storageKey && typeof window !== 'undefined') {
                    window.sessionStorage.removeItem(storageKey);
                }
            }
        }, 200);
    }, [durationSeconds, clearCooldownInterval, storageKey]);

    // Restore a cooldown that survived a full page reload.
    useEffect(() => {
        if (!storageKey || typeof window === 'undefined') {
            return;
        }

        const raw = window.sessionStorage.getItem(storageKey);

        if (!raw) {
            return;
        }

        const end = Number(raw);

        if (!Number.isFinite(end) || end <= Date.now()) {
            window.sessionStorage.removeItem(storageKey);

            return;
        }

        cooldownEndRef.current = end;
        clearCooldownInterval();

        const tick = () => {
            const remaining = Math.max(0, Math.ceil((end - Date.now()) / 1000));
            setCooldown(remaining);

            if (remaining <= 0) {
                clearCooldownInterval();
                cooldownEndRef.current = null;
                window.sessionStorage.removeItem(storageKey);
            }
        };

        tick();
        intervalRef.current = setInterval(tick, 200);

        return () => {
            clearCooldownInterval();
        };
    }, [storageKey, clearCooldownInterval]);

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

        // Start the cooldown immediately, before the request reports back.
        // router.get to a clean URL can be a no-op that never fires
        // onFinish/onError, which left the button permanently enabled.
        setIsReloading(true);
        startCooldown();

        const finish = () => {
            setIsReloading(false);
        };

        if (perform) {
            perform(finish);
        } else {
            router.reload({
                only,
                preserveScroll: true,
                preserveState: true,
                onStart: () => {},
                onFinish: finish,
                onError: finish,
            });
        }
    }, [canReload, only, perform, startCooldown]);

    return {
        reload,
        isReloading,
        cooldown,
        canReload,
    };
}