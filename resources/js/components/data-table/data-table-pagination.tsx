'use client';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';

interface DataTablePaginationProps {
    currentPage: number;
    totalPages: number;
    onPageChange: (page: number) => void;
}

/** Builds [1, "ellipsis", 4, 5, 6, "ellipsis", 12] style page windows. */
function getPageWindow(
    current: number,
    total: number,
): (number | 'ellipsis')[] {
    if (total <= 7) {
return Array.from({ length: total }, (_, i) => i + 1);
}

    const pages: (number | 'ellipsis')[] = [1];

    if (current > 3) {
pages.push('ellipsis');
}

    const start = Math.max(2, current - 1);
    const end = Math.min(total - 1, current + 1);

    for (let p = start; p <= end; p++) {
pages.push(p);
}

    if (current < total - 2) {
pages.push('ellipsis');
}

    pages.push(total);

    return pages;
}

export function DataTablePagination({
    currentPage,
    totalPages,
    onPageChange,
}: DataTablePaginationProps) {
    const [goToValue, setGoToValue] = useState('');

    function commitGoTo() {
        const parsed = Number(goToValue);

        if (Number.isInteger(parsed) && parsed >= 1 && parsed <= totalPages) {
            onPageChange(parsed);
        }

        setGoToValue('');
    }

    return (
        <div className="flex items-center gap-1.5">
            <button
                type="button"
                onClick={() => onPageChange(currentPage - 1)}
                disabled={currentPage <= 1}
                aria-label="Previous page"
                className="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
            >
                <ChevronLeft className="h-4 w-4" />
            </button>
            {getPageWindow(currentPage, totalPages).map((p, i) =>
                p === 'ellipsis' ? (
                    <span
                        key={`ellipsis-${i}`}
                        className="px-1 text-sm text-slate-400"
                    >
                        …
                    </span>
                ) : (
                    <button
                        key={p}
                        type="button"
                        onClick={() => onPageChange(p)}
                        className={
                            p === currentPage
                                ? 'flex h-9 w-9 items-center justify-center rounded-xl bg-blue-600 text-sm font-semibold text-white'
                                : 'flex h-9 w-9 items-center justify-center rounded-xl text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50'
                        }
                    >
                        {p}
                    </button>
                ),
            )}
            <button
                type="button"
                onClick={() => onPageChange(currentPage + 1)}
                disabled={currentPage >= totalPages}
                aria-label="Next page"
                className="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
            >
                <ChevronRight className="h-4 w-4" />
            </button>

            <div className="ml-1.5 flex items-center gap-1.5">
                <input
                    type="number"
                    inputMode="numeric"
                    min={1}
                    max={totalPages}
                    value={goToValue}
                    onChange={(e) => setGoToValue(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
commitGoTo();
}
                    }}
                    placeholder="Go to"
                    aria-label="Go to page"
                    className="h-9 w-16 rounded-xl border border-slate-200 bg-white px-2 text-sm text-slate-600 outline-none transition-colors focus:border-blue-400 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                />
                <button
                    type="button"
                    onClick={commitGoTo}
                    disabled={!goToValue}
                    className="h-9 rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    Go
                </button>
            </div>
        </div>
    );
}