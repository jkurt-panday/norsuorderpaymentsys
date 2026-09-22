// resources/js/Components/AddCollegeOfficeModal.tsx

import { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from './ui/dialog';
import { Button } from './ui/button';
import { Input } from './ui/input';
import { Field, FieldLabel } from './ui/field';

type CollegeOffice = { id: number; name: string };

interface AddCollegeOfficeModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onCreated: (item: CollegeOffice) => void;
}

export default function AddCollegeOfficeModal({
    open,
    onOpenChange,
    onCreated,
}: AddCollegeOfficeModalProps) {
    const [name, setName] = useState('');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState('');

    const handleSubmit = async () => {
        if (!name.trim()) {
            setError('This field is required.');
            return;
        }

        setProcessing(true);
        setError('');

        try {
            const token = document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content');

            const res = await fetch('/public/college-offices', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token || '',
                },
                body: JSON.stringify({ name }),
            });

            if (!res.ok) {
                const data = await res.json();
                setError(
                    data?.errors?.name?.[0] ||
                        data?.message ||
                        'Something went wrong.',
                );
                setProcessing(false);
                return;
            }

            const created: CollegeOffice = await res.json();
            onCreated(created);
            setName('');
            onOpenChange(false);
        } catch (e) {
            setError('Something went wrong. Please try again.');
        } finally {
            setProcessing(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add Office / College</DialogTitle>
                </DialogHeader>

                <Field>
                    <FieldLabel
                        htmlFor="new-college-office-name"
                        className="font-medium text-slate-700"
                    >
                        Name
                    </FieldLabel>
                    <Input
                        id="new-college-office-name"
                        type="text"
                        placeholder="College of Arts and Sciences"
                        value={name}
                        onChange={(e) => {
                            setName(e.target.value);
                            if (error) setError('');
                        }}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                handleSubmit();
                            }
                        }}
                    />
                    {error && (
                        <p className="mt-1 text-sm text-red-500">{error}</p>
                    )}
                </Field>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={processing}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        onClick={handleSubmit}
                        disabled={processing}
                    >
                        {processing ? 'Adding...' : 'Add'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}