import { Head, Link, useForm } from '@inertiajs/react';
import React, { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import AuthLayout from '@/layouts/auth-layout';
import { login } from '@/routes';
import { request as passwordRequest } from '@/routes/password';

export default function Login({ status, canResetPassword }: { status?: string; canResetPassword?: boolean }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const [showPassword, setShowPassword] = useState(false);

    // Automatically wipe password inputs clean if there is an auth error
    useEffect(() => {
        return () => {
            reset('password');
        };
    }, []);

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
                <div className="mb-4 text-sm font-medium text-emerald-600 bg-emerald-50 border border-emerald-200 p-3 rounded-md">
                    {status}
                </div>
            )}

            <form onSubmit={handleSubmit} className="space-y-6">
                {/* Email Address */}
                <div className="space-y-2">
                    <Label className="block text-xs font-semibold text-[#091d2e] uppercase tracking-wider" htmlFor="email">
                        Email Address
                    </Label>
                    <div className="relative">
                        <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[#717786] text-xl">
                            mail
                        </span>
                        <Input
                            className="w-full pl-10 pr-4 py-3 bg-white border border-[#c1c6d7] rounded-lg focus:ring-2 focus:ring-[#005ab7] focus:border-[#005ab7] outline-none transition-all text-sm text-[#091d2e] placeholder:text-muted-foreground"
                            id="email"
                            type="email"
                            name="email"
                            placeholder="e.g. name@university.edu"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            required
                        />
                    </div>
                    {errors.email && <p className="text-xs font-semibold text-[#ba1a1a]">{errors.email}</p>}
                </div>

                {/* Password */}
                <div className="space-y-2">
                    <Label className="block text-xs font-semibold text-[#091d2e] uppercase tracking-wider" htmlFor="password">
                        Password
                    </Label>
                    <div className="relative">
                        <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[#717786] text-xl">
                            lock
                        </span>
                        <Input
                            className="w-full pl-10 pr-12 py-3 bg-white border border-[#c1c6d7] rounded-lg focus:ring-2 focus:ring-[#005ab7] focus:border-[#005ab7] outline-none transition-all text-sm text-[#091d2e] placeholder:text-muted-foreground"
                            id="password"
                            type={showPassword ? 'text' : 'password'}
                            name="password"
                            placeholder="••••••••"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            required
                        />
                        <button
                            type="button"
                            onClick={() => setShowPassword(!showPassword)}
                            className="absolute right-3 top-1/2 -translate-y-1/2 text-[#717786] hover:text-[#005ab7] transition-colors"
                        >
                            <span className="material-symbols-outlined text-xl">
                                {showPassword ? 'visibility_off' : 'visibility'}
                            </span>
                        </button>
                    </div>
                    {errors.password && <p className="text-xs font-semibold text-[#ba1a1a]">{errors.password}</p>}
                </div>

                {/* Remember & Forgot */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-2">
                        <Checkbox
                            id="remember"
                            checked={data.remember}
                            onCheckedChange={(checked) => setData('remember', checked === true)}
                            className="border-[#c1c6d7] data-[state=checked]:bg-[#005ab7] data-[state=checked]:border-[#005ab7] data-[state=checked]:text-white focus-visible:ring-[#005ab7] focus-visible:ring-2 cursor-pointer"
                        />
                        <label htmlFor="remember" className="text-xs text-[#414754] font-medium cursor-pointer select-none">
                            Remember Me
                        </label>
                    </div>
                    {canResetPassword && (
                        <Link
                            href={passwordRequest.url()}
                            className="text-xs text-[#005ab7] hover:underline font-semibold transition-all"
                        >
                            Forgot Password?
                        </Link>
                    )}
                </div>

                {/* Login Button */}
                <Button
                    className="w-full bg-[#0072e5] hover:bg-[#005ab7] text-white py-6 rounded-full text-xs font-semibold uppercase tracking-widest hover:shadow-lg transition-all duration-200 active:scale-[0.98] flex items-center justify-center gap-2 border-0"
                    type="submit"
                    disabled={processing}
                >
                    <span>{processing ? 'LOGGING IN...' : 'LOG IN'}</span>
                    <span className="material-symbols-outlined text-sm">login</span>
                </Button>
            </form>
        </AuthLayout>
    );
}