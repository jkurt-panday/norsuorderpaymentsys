import { Head, Link, useForm } from '@inertiajs/react';
import React, { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { login } from '@/routes';
import { request as forgotPassword } from '@/routes/password';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword?: boolean;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const [showPassword, setShowPassword] = useState(false);

    // Automatically wipe password inputs clean if there is an auth error
    // Fix: Added 'reset' to the dependency array to satisfy ESLint rules
    useEffect(() => {
        return () => {
            reset('password');
        };
    }, [reset]);

    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post(login.url());
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
                        <span className="material-symbols-outlined absolute top-1/2 left-3 -translate-y-1/2 text-xl text-[#717786]">
                            mail
                        </span>
                        <Input
                            className="w-full rounded-lg border border-[#c1c6d7] bg-white py-3 pr-4 pl-10 text-sm text-[#091d2e] transition-all outline-none placeholder:text-muted-foreground focus:border-[#005ab7] focus:ring-2 focus:ring-[#005ab7]"
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
                        <span className="material-symbols-outlined absolute top-1/2 left-3 -translate-y-1/2 text-xl text-[#717786]">
                            lock
                        </span>
                        <Input
                            className="w-full rounded-lg border border-[#c1c6d7] bg-white py-3 pr-12 pl-10 text-sm text-[#091d2e] transition-all outline-none placeholder:text-muted-foreground focus:border-[#005ab7] focus:ring-2 focus:ring-[#005ab7]"
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
                            className="absolute top-1/2 right-3 -translate-y-1/2 text-[#717786] transition-colors hover:text-[#005ab7]"
                        >
                            <span className="material-symbols-outlined pt-2 text-xl">
                                {showPassword ? 'visibility_off' : 'visibility'}
                            </span>
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
                            className="cursor-pointer border-[#c1c6d7] focus-visible:ring-2 focus-visible:ring-[#005ab7] data-[state=checked]:border-[#005ab7] data-[state=checked]:bg-[#005ab7] data-[state=checked]:text-white"
                        />
                        <label
                            htmlFor="remember"
                            className="cursor-pointer text-xs font-medium text-[#414754] select-none"
                        >
                            Remember Me
                        </label>
                    </div>

                    {canResetPassword && (
                        <Link
                            href={forgotPassword.url()}
                            className="text-xs font-semibold text-[#005ab7] hover:underline"
                        >
                            Forgot password?
                        </Link>
                    )}
                </div>

                {/* Login Button */}
                <Button
                    className="flex w-full items-center justify-center gap-2 rounded-full border-0 bg-[#0072e5] py-6 text-xs font-semibold tracking-widest text-white uppercase transition-all duration-200 hover:bg-[#005ab7] hover:shadow-lg active:scale-[0.98]"
                    type="submit"
                    disabled={processing}
                >
                    <span>{processing ? 'LOGGING IN...' : 'LOG IN'}</span>
                    <span className="material-symbols-outlined text-sm">
                        login
                    </span>
                </Button>
            </form>
        </AuthLayout>
    );
}
