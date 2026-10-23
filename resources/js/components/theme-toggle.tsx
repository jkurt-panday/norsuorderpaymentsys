import { Moon, Sun } from 'lucide-react';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

interface ThemeToggleProps {
    className?: string;
}

export function ThemeToggle({ className }: ThemeToggleProps) {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const isDark = resolvedAppearance === 'dark';

    const toggleTheme = () => {
        updateAppearance(isDark ? 'light' : 'dark');
    };

    return (
        <button
            type="button"
            onClick={toggleTheme}
            aria-label="Toggle dark mode"
            className={cn(
                'relative inline-flex h-7 w-14 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2',
                isDark ? 'bg-primary' : 'bg-slate-300 dark:bg-slate-700',
                className
            )}
        >
            <span className="sr-only">Toggle dark mode</span>
            <span
                className={cn(
                    'pointer-events-none flex h-6 w-6 transform items-center justify-center rounded-full bg-white shadow-lg ring-0 transition duration-200 ease-in-out',
                    isDark ? 'translate-x-7' : 'translate-x-0'
                )}
            >
                {isDark ? (
                    <Moon className="h-3.5 w-3.5 text-slate-900" />
                ) : (
                    <Sun className="h-3.5 w-3.5 text-amber-500" />
                )}
            </span>
        </button>
    );
}
