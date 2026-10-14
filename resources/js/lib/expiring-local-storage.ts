const DEFAULT_TTL_MS = 2 * 60 * 60 * 1000;

type ExpiringStorageValue<T> = {
    value: T;
    expiresAt: number;
};

export const setExpiringLocalStorage = <T>(
    key: string,
    value: T,
    ttlMs = DEFAULT_TTL_MS,
) => {
    if (typeof window === 'undefined') {
        return;
    }

    const payload: ExpiringStorageValue<T> = {
        value,
        expiresAt: Date.now() + ttlMs,
    };

    window.localStorage.setItem(key, JSON.stringify(payload));
};

export const getExpiringLocalStorage = <T>(key: string): T | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const saved = window.localStorage.getItem(key);

        if (!saved) {
            return null;
        }

        const parsed = JSON.parse(saved) as Partial<ExpiringStorageValue<T>>;

        if (
            !parsed ||
            typeof parsed !== 'object' ||
            typeof parsed.expiresAt !== 'number' ||
            !('value' in parsed)
        ) {
            window.localStorage.removeItem(key);

            return null;
        }

        if (Date.now() > parsed.expiresAt) {
            window.localStorage.removeItem(key);

            return null;
        }

        return parsed.value as T;
    } catch {
        window.localStorage.removeItem(key);

        return null;
    }
};
