import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Eye, EyeOff, LogIn, Mail, Lock } from 'lucide-react';
import React, { useEffect, useState } from 'react';
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
import AuthLayout from '@/layouts/auth-layout';
import { login } from '@/routes';
import { request as forgotPassword } from '@/routes/password';

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
        if (!auth?.user) return;

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
        <AuthLayout
            title="Payment of Order System"
            description="Enter your institutional credentials to continue."
        >
            <Head title="Payment of Order System - Login" />

            {status && (
                <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-600">
                    {status}
                </div>
            )}

            <form onSubmit={handleSubmit} className="space-y-6">
                {/* Email Address */}
                <div className="space-y-2">
                    <Label
                        className="block text-xs font-semibold tracking-wider text-[#091d2e] uppercase"
                        htmlFor="email"
                    >
                        Email Address
                    </Label>
                    <div className="relative">
                        <Mail className="absolute top-1/2 left-3 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                        <Input
                            className="w-full rounded-lg border border-border bg-white py-3 pr-4 pl-10 text-sm text-foreground transition-all outline-none placeholder:text-muted-foreground focus:border-[#0d51b5] focus:ring-2 focus:ring-[#0d51b5]/20"
                            id="email"
                            type="email"
                            name="email"
                            placeholder="e.g. name@university.edu"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            required
                        />
                    </div>
                    {errors.email && (
                        <p className="text-xs font-semibold text-[#ba1a1a]">
                            {errors.email}
                        </p>
                    )}
                </div>

                {/* Password */}
                <div className="space-y-2">
                    <Label
                        className="block text-xs font-semibold tracking-wider text-[#091d2e] uppercase"
                        htmlFor="password"
                    >
                        Password
                    </Label>
                    <div className="relative">
                        <Lock className="absolute top-1/2 left-3 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                        <Input
                            className="w-full rounded-lg border border-border bg-white py-3 pr-12 pl-10 text-sm text-foreground transition-all outline-none placeholder:text-muted-foreground focus:border-[#0d51b5] focus:ring-2 focus:ring-[#0d51b5]/20"
                            id="password"
                            type={showPassword ? 'text' : 'password'}
                            name="password"
                            placeholder="••••••••"
                            value={data.password}
                            onChange={(e) =>
                                setData('password', e.target.value)
                            }
                            required
                        />
                        <button
                            type="button"
                            onClick={() => setShowPassword(!showPassword)}
                            className="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground transition-colors hover:text-[#0d51b5]"
                        >
                            {showPassword ? (
                                <EyeOff className="w-4 h-4" />
                            ) : (
                                <Eye className="w-4 h-4" />
                            )}
                        </button>
                    </div>
                    {errors.password && (
                        <p className="text-xs font-semibold text-[#ba1a1a]">
                            {errors.password}
                        </p>
                    )}
                </div>

                {/* Remember + Forgot Password */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-2">
                        <Checkbox
                            id="remember"
                            checked={data.remember}
                            onCheckedChange={(checked) =>
                                setData('remember', checked === true)
                            }
                            className="cursor-pointer border-border focus-visible:ring-2 focus-visible:ring-[#0d51b5] data-[state=checked]:border-[#0d51b5] data-[state=checked]:bg-[#0d51b5] data-[state=checked]:text-white"
                        />
                        <label
                            htmlFor="remember"
                            className="cursor-pointer text-xs font-medium text-muted-foreground select-none"
                        >
                            Remember Me
                        </label>
                    </div>

                    {canResetPassword && (
                        <Link
                            href={forgotPassword.url()}
                            className="text-xs font-semibold text-[#0d51b5] hover:underline"
                        >
                            Forgot password?
                        </Link>
                    )}
                </div>

                {/* Login Button */}
                <Button
                    className="flex w-full items-center justify-center gap-2 rounded-full border-0 bg-[#0d51b5] py-6 text-xs font-semibold tracking-widest text-white uppercase transition-all duration-200 hover:bg-[#0a3d8a] hover:shadow-lg active:scale-[0.98]"
                    type="submit"
                    disabled={processing}
                >
                    <span>{processing ? 'LOGGING IN...' : 'LOG IN'}</span>
                    <LogIn className="w-4 h-4" />
                </Button>
            </form>

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
        </AuthLayout>
    );
}
