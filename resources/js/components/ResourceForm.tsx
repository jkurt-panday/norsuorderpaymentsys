import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import React from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

// ============ TYPE DEFINITIONS ============
export interface FieldDef {
    /** Form field name (also the key in initialData) */
    name: string;
    /** Label shown above the input */
    label: string;
    /** Whether the field is required */
    required?: boolean;
    /** Small helper text shown below the input */
    helpText?: string;
    /** Input type, defaults to 'text' */
    type?: string;
    /** Grid span — 'half' (default, side-by-side on md+) or 'full' (spans both columns) */
    colSpan?: 'half' | 'full';
    /** Placeholder text shown inside the input */
    placeholder?: string;
}

export interface ResourceFormProps {
    /** Page <title> and heading */
    title: string;
    /** href for the "Back" button (from a Wayfinder route function, e.g. index()) */
    backHref: string | { url: string; method?: string };
    /** Field definitions, in display order */
    fields: FieldDef[];
    /** Initial values, keyed by field name (empty strings for create, existing values for edit) */
    initialData: Record<string, string>;
    /** URL to submit to (from a Wayfinder route function, e.g. store().url or update(id).url) */
    submitUrl: string;
    /** HTTP method for the submission */
    method: 'post' | 'put';
    /** Label on the submit button while idle (defaults based on method) */
    submitLabel?: string;
    /** Label on the submit button while submitting (defaults based on method) */
    processingLabel?: string;
    /** Toast message shown after a successful save (defaults based on method) */
    successMessage?: string;
}

// ============ COMPONENT ============
export default function ResourceForm({
    title,
    backHref,
    fields,
    initialData,
    submitUrl,
    method,
    submitLabel,
    processingLabel,
    successMessage,
}: ResourceFormProps) {
    const { data, setData, post, put, processing, errors } =
        useForm<Record<string, string>>(initialData);

    const resolvedSubmitLabel =
        submitLabel ?? (method === 'post' ? 'Create' : 'Update');
    const resolvedProcessingLabel =
        processingLabel ?? (method === 'post' ? 'Creating...' : 'Updating...');

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const options = {
            // No toast.success here on purpose. The backend already flashes a
            // specific success message on redirect (e.g. "Membership created
            // successfully."), and the destination index page listens for it.
            // Showing a generic toast here too would duplicate that message.
            onError: () => {
                toast.error('Please check the form for errors.');
            },
        };

        if (method === 'post') {
            post(submitUrl, options);
        } else {
            put(submitUrl, options);
        }
    };

    return (
        <div className="mx-auto w-full max-w-3xl min-w-0 space-y-4 p-3 sm:p-6">
            <Head title={title} />

            <div className="flex items-center justify-between">
                <h2 className="text-lg font-semibold text-slate-900">
                    {title}
                </h2>
                <Link
                    href={backHref}
                    className="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back
                </Link>
            </div>

            <Card className="w-full">
                <CardContent className="p-6">
                    <form onSubmit={handleSubmit}>
                        <div className="grid grid-cols-1 gap-x-6 gap-y-5 md:grid-cols-2">
                            {fields.map((field) => (
                                <div
                                    key={field.name}
                                    className={
                                        field.colSpan === 'full'
                                            ? 'md:col-span-2'
                                            : undefined
                                    }
                                >
                                    <Label
                                        htmlFor={field.name}
                                        className="mb-1.5 text-sm font-medium text-slate-800"
                                    >
                                        {field.label}
                                        {field.required && (
                                            <span className="text-red-600">
                                                {' '}
                                                *
                                            </span>
                                        )}
                                    </Label>
                                    <Input
                                        id={field.name}
                                        type={field.type ?? 'text'}
                                        name={field.name}
                                        value={data[field.name] ?? ''}
                                        onChange={(e) =>
                                            setData(field.name, e.target.value)
                                        }
                                        required={field.required}
                                        placeholder={field.placeholder}
                                        className={cn(
                                            'rounded-md',
                                            errors[field.name] &&
                                                'border-red-500 focus-visible:ring-red-500/40',
                                        )}
                                    />
                                    {errors[field.name] && (
                                        <p className="mt-1 text-xs text-red-600">
                                            {errors[field.name]}
                                        </p>
                                    )}
                                    {field.helpText && (
                                        <p className="mt-1 text-xs text-slate-400">
                                            {field.helpText}
                                        </p>
                                    )}
                                </div>
                            ))}
                        </div>

                        <div className="mt-6 flex justify-end gap-2 border-t border-slate-200 pt-4">
                            <Button
                                type="submit"
                                disabled={processing}
                                className="rounded-md bg-blue-900 text-white hover:bg-blue-950"
                            >
                                <Save className="h-4 w-4" />
                                {processing
                                    ? resolvedProcessingLabel
                                    : resolvedSubmitLabel}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}
