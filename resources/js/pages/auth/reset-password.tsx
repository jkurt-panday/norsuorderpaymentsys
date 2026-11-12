import { Head, Link, useForm } from '@inertiajs/react';
import React, { useEffect, useState } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthSplitLayout from '@/layouts/auth/auth-split-layout';

const sharedInputClass =
    'min-h-[54px] w-full rounded-[9px] border-[1.5px] border-[#D3DCE6] bg-white px-4 py-3 text-[#0F1E33] placeholder:text-[#55667D] placeholder:opacity-70 focus-visible:outline-[3px] focus-visible:outline-offset-2 focus-visible:outline-[#0B7BE0] aria-invalid:border-[#B3261E] dark:border-[#243652] dark:bg-[#101D31] dark:text-[#E6EEF8] dark:focus-visible:outline-[#5AA9F5] dark:aria-invalid:border-[#FF8A80]';

interface ResetPasswordProps {
    token: string;
    email?: string;
    passwordRules?: string;
}

export default function ResetPassword({
    token,
    email = '',
    passwordRules,
}: ResetPasswordProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token: token || '',
        email: email || '',
        password: '',
        password_confirmation: '',
    });

    const [showPassword, setShowPassword] = useState(false);
    const [showConfirmation, setShowConfirmation] = useState(false);

    useEffect(() => {
        return () => {
            reset('password', 'password_confirmation');
        };
    }, [reset]);

    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post('/reset-password');
    };

    return (
        <AuthSplitLayout>
            <Head title="Order of Payment System – Reset Password" />

            <h2 className="font-heading text-[30px] leading-tight font-bold text-[#0F1E33] dark:text-[#E6EEF8]">
                Reset password
            </h2>
            <p className="mt-1 mb-6 text-[#55667D] dark:text-[#8FA4BF]">
                Create a new, secure password for your account.
            </p>

            {(errors.email || errors.password || errors.token) && (
                <div
                    role="alert"
                    aria-live="polite"
                    className="mb-3.5 min-h-[22px] text-sm text-destructive"
                >
                    {errors.token || errors.email || errors.password}
                </div>
            )}

            <form onSubmit={handleSubmit}>
                <input type="hidden" name="token" value={data.token} />

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
                        className={sharedInputClass}
                    />
                </div>

                <div className="mt-4">
                    <Label htmlFor="password" className="mb-2">
                        New password
                    </Label>
                    <div className="relative">
                        <Input
                            id="password"
                            name="password"
                            type={showPassword ? 'text' : 'password'}
                            autoComplete="new-password"
                            aria-invalid={errors.password ? 'true' : undefined}
                            placeholder="Enter new password"
                            value={data.password}
                            onChange={(e) =>
                                setData('password', e.target.value)
                            }
                            required
                            autoFocus
                            className={`${sharedInputClass} pr-[76px]`}
                        />
                        <button
                            type="button"
                            onClick={() => setShowPassword(!showPassword)}
                            aria-controls="password"
                            aria-pressed={showPassword}
                            className="absolute top-[9px] right-0 bottom-[9px] flex items-center border-l border-[#D3DCE6] px-[18px] text-[15px] font-medium text-[#0B5CAD] dark:border-[#243652] dark:text-[#5AA9F5]"
                        >
                            {showPassword ? 'Hide' : 'Show'}
                        </button>
                    </div>
                    {passwordRules && (
                        <p className="mt-1.5 text-xs text-[#55667D] dark:text-[#8FA4BF]">
                            {passwordRules}
                        </p>
                    )}
                </div>

                <div className="mt-4">
                    <Label htmlFor="password_confirmation" className="mb-2">
                        Confirm password
                    </Label>
                    <div className="relative">
                        <Input
                            id="password_confirmation"
                            name="password_confirmation"
                            type={showConfirmation ? 'text' : 'password'}
                            autoComplete="new-password"
                            aria-invalid={errors.password_confirmation ? 'true' : undefined}
                            placeholder="Confirm new password"
                            value={data.password_confirmation}
                            onChange={(e) =>
                                setData('password_confirmation', e.target.value)
                            }
                            required
                            className={`${sharedInputClass} pr-[76px]`}
                        />
                        <button
                            type="button"
                            onClick={() => setShowConfirmation(!showConfirmation)}
                            aria-controls="password_confirmation"
                            aria-pressed={showConfirmation}
                            className="absolute top-[9px] right-0 bottom-[9px] flex items-center border-l border-[#D3DCE6] px-[18px] text-[15px] font-medium text-[#0B5CAD] dark:border-[#243652] dark:text-[#5AA9F5]"
                        >
                            {showConfirmation ? 'Hide' : 'Show'}
                        </button>
                    </div>
                </div>

                <div className="mt-6">
                    <button
                        type="submit"
                        disabled={processing}
                        className="min-h-[50px] w-full rounded-[9px] bg-[#0B5CAD] text-base font-semibold text-white hover:brightness-110 disabled:opacity-70 dark:bg-[#5AA9F5] dark:text-[#04152B]"
                    >
                        {processing ? 'Resetting password…' : 'Reset password'}
                    </button>
                </div>
            </form>

            <p className="mt-6 border-t border-[#D3DCE6] pt-[18px] text-center text-[13.5px] text-[#55667D] dark:border-[#243652] dark:text-[#8FA4BF]">
                Remember your credentials?{' '}
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
