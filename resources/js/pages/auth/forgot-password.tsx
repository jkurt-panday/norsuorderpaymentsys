import { Head, Link, useForm } from '@inertiajs/react';
import React from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthSplitLayout from '@/layouts/auth/auth-split-layout';

const sharedInputClass =
    'min-h-[54px] w-full rounded-[9px] border-[1.5px] border-[#D3DCE6] bg-white px-4 py-3 text-[#0F1E33] placeholder:text-[#55667D] placeholder:opacity-70 focus-visible:outline-[3px] focus-visible:outline-offset-2 focus-visible:outline-[#0B7BE0] aria-invalid:border-[#B3261E] dark:border-[#243652] dark:bg-[#101D31] dark:text-[#E6EEF8] dark:focus-visible:outline-[#5AA9F5] dark:aria-invalid:border-[#FF8A80]';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post('/forgot-password');
    };

    return (
        <AuthSplitLayout>
            <Head title="Order of Payment System – Forgot Password" />

            <h2 className="font-heading text-[30px] leading-tight font-bold text-[#0F1E33] dark:text-[#E6EEF8]">
                Forgot password
            </h2>
            <p className="mt-1 mb-6 text-[#55667D] dark:text-[#8FA4BF]">
                Enter your email address and we'll send you a password reset link.
            </p>

            {status && (
                <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-600 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-400">
                    {status}
                </div>
            )}

            {errors.email && (
                <div
                    role="alert"
                    aria-live="polite"
                    className="mb-3.5 min-h-[22px] text-sm text-destructive"
                >
                    {errors.email}
                </div>
            )}

            <form onSubmit={handleSubmit}>
                <div>
                    <Label htmlFor="email" className="mb-2">
                        Email address
                    </Label>
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        autoComplete="email"
                        inputMode="email"
                        aria-invalid={errors.email ? 'true' : undefined}
                        placeholder="e.g. cashier@university.edu"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                        autoFocus
                        className={sharedInputClass}
                    />
                </div>

                <div className="mt-6">
                    <button
                        type="submit"
                        disabled={processing}
                        className="min-h-[50px] w-full rounded-[9px] bg-[#0B5CAD] text-base font-semibold text-white hover:brightness-110 disabled:opacity-70 dark:bg-[#5AA9F5] dark:text-[#04152B]"
                    >
                        {processing ? 'Sending link…' : 'Email password reset link'}
                    </button>
                </div>
            </form>

            <p className="mt-6 border-t border-[#D3DCE6] pt-[18px] text-center text-[13.5px] text-[#55667D] dark:border-[#243652] dark:text-[#8FA4BF]">
                Remember your password?{' '}
                <Link
                    href="/login"
                    className="font-medium text-[#0B5CAD] hover:underline dark:text-[#5AA9F5]"
                >
                    Return to sign in
                </Link>
            </p>
        </AuthSplitLayout>
    );
}
