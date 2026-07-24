import { Form, Head, Link } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { login } from '@/routes';
import { update } from '@/routes/password';

type Props = {
    token: string;
    email: string;
    passwordRules: string;
};

export default function ResetPassword({ token, email, passwordRules }: Props) {
    const [showPassword, setShowPassword] = useState(false);
    const [showPasswordConfirmation, setShowPasswordConfirmation] = useState(false);

    return (
        <AuthLayout
            title="Reset Password"
            description="Enter your new password below to regain access to your account."
        >
            <Head title="Payment of Order System - Reset Password" />

            <Form
                {...update.form()}
                transform={(data) => ({ ...data, token, email })}
                resetOnSuccess={['password', 'password_confirmation']}
            >
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <div className="space-y-2">
                            <Label className="block text-xs font-semibold text-[#091d2e] uppercase tracking-wider" htmlFor="email">
                                Email Address
                            </Label>
                            <div className="relative">
                                <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[#717786] text-xl">
                                    mail
                                </span>
                                <Input
                                    className="w-full pl-10 pr-4 py-3 bg-[#f7f9ff] border border-[#c1c6d7] rounded-lg text-sm text-[#091d2e] cursor-not-allowed"
                                    id="email"
                                    type="email"
                                    name="email"
                                    autoComplete="email"
                                    value={email}
                                    readOnly
                                />
                            </div>
                            {errors.email && <p className="text-xs font-semibold text-[#ba1a1a]">{errors.email}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label className="block text-xs font-semibold text-[#091d2e] uppercase tracking-wider" htmlFor="password">
                                New Password
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
                                    autoComplete="new-password"
                                    autoFocus
                                    placeholder="Enter new password"
                                    passwordrules={passwordRules}
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

                        <div className="space-y-2">
                            <Label className="block text-xs font-semibold text-[#091d2e] uppercase tracking-wider" htmlFor="password_confirmation">
                                Confirm Password
                            </Label>
                            <div className="relative">
                                <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[#717786] text-xl">
                                    lock
                                </span>
                                <Input
                                    className="w-full pl-10 pr-12 py-3 bg-white border border-[#c1c6d7] rounded-lg focus:ring-2 focus:ring-[#005ab7] focus:border-[#005ab7] outline-none transition-all text-sm text-[#091d2e] placeholder:text-muted-foreground"
                                    id="password_confirmation"
                                    type={showPasswordConfirmation ? 'text' : 'password'}
                                    name="password_confirmation"
                                    autoComplete="new-password"
                                    placeholder="Confirm new password"
                                    passwordrules={passwordRules}
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPasswordConfirmation(!showPasswordConfirmation)}
                                    className="absolute right-3 top-1/2 -translate-y-1/2 text-[#717786] hover:text-[#005ab7] transition-colors"
                                >
                                    <span className="material-symbols-outlined text-xl">
                                        {showPasswordConfirmation ? 'visibility_off' : 'visibility'}
                                    </span>
                                </button>
                            </div>
                            {errors.password_confirmation && (
                                <p className="text-xs font-semibold text-[#ba1a1a]">{errors.password_confirmation}</p>
                            )}
                        </div>

                        <Button
                            type="submit"
                            className="w-full bg-[#0072e5] hover:bg-[#005ab7] text-white py-6 rounded-full text-xs font-semibold uppercase tracking-widest hover:shadow-lg transition-all duration-200 active:scale-[0.98] flex items-center justify-center gap-2 border-0"
                            disabled={processing}
                            data-test="reset-password-button"
                        >
                            {processing ? (
                                <>
                                    <LoaderCircle className="h-4 w-4 animate-spin" />
                                    <span>RESETTING...</span>
                                </>
                            ) : (
                                <>
                                    <span>RESET PASSWORD</span>
                                    <span className="material-symbols-outlined text-sm">lock_reset</span>
                                </>
                            )}
                        </Button>
                    </div>
                )}
            </Form>

            <div className="mt-6 text-center text-sm text-[#414754]">
                <span>Or, return to </span>
                <Link href={login.url()} className="text-xs text-[#005ab7] hover:underline font-semibold">
                    log in
                </Link>
            </div>
        </AuthLayout>
    );
}
