import { Head, Link, useForm, usePage } from '@inertiajs/react';
import React, { useEffect, useState } from 'react';
import AuthSplitLayout from '@/layouts/auth/auth-split-layout';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { login } from '@/routes';
import { request as forgotPassword } from '@/routes/password';

const sharedInputClass =
    'min-h-[54px] w-full rounded-[9px] border-[1.5px] border-[#D3DCE6] bg-white px-4 py-3 text-[#0F1E33] placeholder:text-[#55667D] placeholder:opacity-70 focus-visible:outline-[3px] focus-visible:outline-offset-2 focus-visible:outline-[#0B7BE0] aria-invalid:border-[#B3261E] dark:border-[#243652] dark:bg-[#101D31] dark:text-[#E6EEF8] dark:focus-visible:outline-[#5AA9F5] dark:aria-invalid:border-[#FF8A80]';

export default function Login({
    status,
    canResetPassword,
    deactivated_account = false,
}: {
    status?: string;
    canResetPassword?: boolean;
    deactivated_account?: boolean;
}) {
    const { auth } = usePage<{ auth: { user: { role: string } | null } }>().props;
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const [showPassword, setShowPassword] = useState(false);
    const [isDeactivatedOpen, setIsDeactivatedOpen] = useState(deactivated_account);

    // If the user is already logged in (e.g. they navigated here via
    // browser back/forward, which restores cached Inertia props), bounce
    // them to their role dashboard immediately.
    useEffect(() => {
        if (!auth?.user) {
            return;
        }

        const role = auth.user.role;

        if (role === 'admin') {
            window.location.assign('/admin/dashboard');
        } else if (role === 'staff') {
            window.location.assign('/staff/staffdashboard');
        } else if (role === 'cashier') {
            window.location.assign('/cashier/requests');
        } else if (role === 'client') {
            window.location.assign('/client/dashboard');
        } else {
            window.location.assign('/dashboard');
        }
    }, [auth?.user]);

    // Automatically wipe password inputs clean if there is an auth error
    useEffect(() => {
        return () => {
            reset('password');
        };
    }, [reset]);

    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post(login.url(), {
            onError: (validationErrors) => {
                if (
                    validationErrors.email &&
                    /deactivated/i.test(validationErrors.email)
                ) {
                    setIsDeactivatedOpen(true);
                }
            },
        });
    };

    return (
        <AuthSplitLayout>
            <Head title="Order of Payment System – Sign in" />

            <h2 className="font-heading text-[30px] font-bold leading-tight text-[#0F1E33] dark:text-[#E6EEF8]">
                Sign in
            </h2>
            <p className="mt-1 mb-6 text-[#55667D] dark:text-[#8FA4BF]">
                Access your university finance account.
            </p>

            {status && (
                <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-600 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-400">
                    {status}
                </div>
            )}

            <div
                role="alert"
                aria-live="polite"
                className="mb-3.5 min-h-[22px] text-sm text-destructive"
            >
                {errors.email || errors.password}
            </div>

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
                        aria-describedby="email-hint"
                        aria-invalid={errors.email ? 'true' : undefined}
                        placeholder="e.g. cashier@university.edu"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                        className={sharedInputClass}
                    />
                    <p
                        id="email-hint"
                        className="mt-1.5 text-[13px] text-[#55667D] dark:text-[#8FA4BF]"
                    >
                        Use the email issued by your institution.
                    </p>
                </div>

                <div className="mt-4">
                    <Label htmlFor="password" className="mb-2">
                        Password
                    </Label>
                    <div className="relative">
                        <Input
                            id="password"
                            name="password"
                            type={showPassword ? 'text' : 'password'}
                            autoComplete="current-password"
                            aria-invalid={errors.password ? 'true' : undefined}
                            placeholder="Enter your password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            required
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
                </div>

                <div className="my-5 flex items-center justify-between gap-3 text-sm">
                    <label className="flex cursor-pointer items-center gap-2 font-normal select-none">
                        <Checkbox
                            id="remember"
                            checked={data.remember}
                            onCheckedChange={(c) => setData('remember', c === true)}
                        />
                        Keep me signed in
                    </label>

                    {canResetPassword && (
                        <Link
                            href={forgotPassword.url()}
                            className="text-sm font-medium text-[#0B5CAD] hover:underline dark:text-[#5AA9F5]"
                        >
                            Forgot password?
                        </Link>
                    )}
                </div>

                <button
                    type="submit"
                    disabled={processing}
                    className="min-h-[50px] w-full rounded-[9px] bg-[#0B5CAD] text-base font-semibold text-white hover:brightness-110 disabled:opacity-70 dark:bg-[#5AA9F5] dark:text-[#04152B]"
                >
                    {processing ? 'Signing in…' : 'Sign in'}
                </button>
            </form>

            <p className="mt-6 border-t border-[#D3DCE6] pt-[18px] text-center text-[13.5px] text-[#55667D] dark:border-[#243652] dark:text-[#8FA4BF]">
                Need access? Contact your system administrator.
            </p>

            <Dialog open={isDeactivatedOpen} onOpenChange={setIsDeactivatedOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Account Deactivated</DialogTitle>
                        <DialogDescription>
                            Your account has been deactivated. Please contact an
                            administrator for assistance.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            onClick={() => setIsDeactivatedOpen(false)}
                            className="w-full"
                        >
                            OK
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AuthSplitLayout>
    );
}
