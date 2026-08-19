import React, { createContext, useCallback, useContext, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export interface ConfirmOptions {
    title?: string;
    description?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    /** 'destructive' renders the confirm button red — use for delete actions. */
    variant?: 'default' | 'destructive';
}

type ConfirmFn = (options: ConfirmOptions) => Promise<boolean>;

const ConfirmContext = createContext<ConfirmFn | null>(null);

interface ConfirmState {
    open: boolean;
    options: ConfirmOptions;
    resolve: ((value: boolean) => void) | null;
}

/**
 * Mount this once near the root of the app (see app.tsx) — every call to
 * useConfirm() anywhere in the tree reuses this single dialog instance,
 * instead of each component owning its own delete/edit/process modal.
 */
export function ConfirmProvider({ children }: { children: React.ReactNode }) {
    const [state, setState] = useState<ConfirmState>({
        open: false,
        options: {},
        resolve: null,
    });

    const confirm = useCallback<ConfirmFn>((options) => {
        return new Promise<boolean>((resolve) => {
            setState({ open: true, options, resolve });
        });
    }, []);

    const handleClose = (result: boolean) => {
        state.resolve?.(result);
        setState((prev) => ({ ...prev, open: false }));
    };

    return (
        <ConfirmContext.Provider value={confirm}>
            {children}
            <Dialog open={state.open} onOpenChange={(open) => !open && handleClose(false)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{state.options.title ?? 'Are you sure?'}</DialogTitle>
                        {state.options.description && (
                            <DialogDescription>{state.options.description}</DialogDescription>
                        )}
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => handleClose(false)}>
                            {state.options.cancelLabel ?? 'Cancel'}
                        </Button>
                        <Button
                            variant={state.options.variant === 'destructive' ? 'destructive' : 'default'}
                            onClick={() => handleClose(true)}
                        >
                            {state.options.confirmLabel ?? 'Confirm'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </ConfirmContext.Provider>
    );
}

/**
 * Usage from any component:
 *
 *   const confirm = useConfirm();
 *
 *   const handleDelete = async (id: number) => {
 *     const ok = await confirm({
 *       title: 'Delete this document?',
 *       description: 'This action cannot be undone.',
 *       confirmLabel: 'Delete',
 *       variant: 'destructive',
 *     });
 *     if (ok) router.delete(staff.documents.destroy.url(id));
 *   };
 */
export function useConfirm(): ConfirmFn {
    const ctx = useContext(ConfirmContext);

    if (!ctx) {
        throw new Error('useConfirm must be used within a <ConfirmProvider>');
    }

    return ctx;
}