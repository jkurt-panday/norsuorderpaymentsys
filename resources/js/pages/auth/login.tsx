import { Head, Link, useForm } from '@inertiajs/react';
import React, { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
        <div className="min-h-screen flex flex-col bg-[#f7f9ff] text-[#091d2e]">
            <Head title="Payment of Order System - Login" />

            {/* TopAppBar */}
            <header className="w-full bg-white border-b border-[#c1c6d7] z-50">
                <div className="flex items-center justify-between px-6 md:px-10 py-4 max-w-[1280px] mx-auto">
                    <div className="flex items-center gap-4">
                        <div className="flex items-center gap-2">
                            <span className="material-symbols-outlined text-[#005ab7] text-3xl" style={{ fontVariationSettings: "'FILL' 1" }}>
                                account_balance
                            </span>
                            <div className="h-8 w-px bg-[#c1c6d7] mx-2"></div>
                            <h1 className="text-2xl md:text-3xl font-semibold text-[#005ab7] leading-none" style={{ fontFamily: "'Source Serif 4', serif" }}>
                                University Accounting Office
                            </h1>
                        </div>
                    </div>
                </div>
            </header>

            {/* Main Content */}
            <main className="flex-grow flex items-center justify-center relative py-12 px-4 md:px-10">
                {/* Abstract background pattern */}
                <div className="absolute inset-0 z-0 overflow-hidden opacity-40 pointer-events-none">
                    <div className="absolute top-0 right-0 w-1/3 h-1/3 bg-[#0072e5]/10 rounded-full blur-3xl -mr-20 -mt-20"></div>
                    <div className="absolute bottom-0 left-0 w-1/2 h-1/2 bg-[#d9eaff]/20 rounded-full blur-3xl -ml-40 -mb-40"></div>
                </div>

                {/* Login Card */}
                <div className="z-10 w-full max-w-md">
                    {/* Status Alert */}
                    {status && (
                        <div className="mb-4 text-sm font-medium text-emerald-600 bg-emerald-50 border border-emerald-200 p-3 rounded-md">
                            {status}
                        </div>
                    )}

                    <Card className="bg-white border border-[#c1c6d7] rounded-xl shadow-xl p-6 md:p-8">
                        <CardHeader className="text-center pb-6">
                            <div className="flex justify-center">
                                <span className="material-symbols-outlined text-[#005ab7] text-5xl mb-3" style={{ fontVariationSettings: "'FILL' 1" }}>
                                    lock_person
                                </span>
                            </div>
                            <CardTitle className="text-2xl font-bold text-[#091d2e]" style={{ fontFamily: "'Source Serif 4', serif" }}>
                                Payment of Order System
                            </CardTitle>
                            <CardDescription className="text-[#414754] text-sm mt-1">
                                Enter your institutional credentials to continue.
                            </CardDescription>
                        </CardHeader>

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
                                    <input
                                        id="remember"
                                        type="checkbox"
                                        name="remember"
                                        checked={data.remember}
                                        onChange={(e) => setData('remember', e.target.checked)}
                                        style={{
                                            backgroundColor: '#ffffff',
                                            borderColor: '#c1c6d7',
                                            color: '#005ab7'
                                        }}
                                        className="h-4 w-4 rounded focus:ring-[#005ab7] focus:ring-2 cursor-pointer"
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
                    </Card>
                </div>

                {/* Visual Side Decoration */}
                <div className="hidden lg:block absolute right-12 top-1/2 -translate-y-1/2 w-80 h-96 opacity-20 pointer-events-none">
                    <div className="w-full h-full border-2 border-[#005ab7]/20 rounded-xl p-4 rotate-3">
                        <div className="w-full h-full border-2 border-[#005ab7]/20 rounded-xl p-4 -rotate-6">
                            <div className="w-full h-full border-2 border-[#005ab7]/20 rounded-xl"></div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    );
}