import { Form, Head, Link } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <AuthLayout
            title="Forgot Password"
            description="Enter your email address and we will send you a password reset link."
        >
            <Head title="Payment of Order System - Forgot Password" />

            {status && (
                <div className="mb-4 text-sm font-medium text-emerald-600 bg-emerald-50 border border-emerald-200 p-3 rounded-md">
                    {status}
                </div>
            )}

            <Form {...email.form()}>
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
                                    className="w-full pl-10 pr-4 py-3 bg-white border border-[#c1c6d7] rounded-lg focus:ring-2 focus:ring-[#005ab7] focus:border-[#005ab7] outline-none transition-all text-sm text-[#091d2e] placeholder:text-muted-foreground"
                                    id="email"
                                    type="email"
                                    name="email"
                                    autoComplete="email"
                                    autoFocus
                                    placeholder="e.g. name@university.edu"
                                />
                            </div>
                            {errors.email && <p className="text-xs font-semibold text-[#ba1a1a]">{errors.email}</p>}
                        </div>

                        <Button
                            type="submit"
                            className="w-full bg-[#0072e5] hover:bg-[#005ab7] text-white py-6 rounded-full text-xs font-semibold uppercase tracking-widest hover:shadow-lg transition-all duration-200 active:scale-[0.98] flex items-center justify-center gap-2 border-0"
                            disabled={processing}
                            data-test="email-password-reset-link-button"
                        >
                            {processing ? (
                                <>
                                    <LoaderCircle className="h-4 w-4 animate-spin" />
                                    <span>SENDING...</span>
                                </>
                            ) : (
                                <>
                                    <span>EMAIL PASSWORD RESET LINK</span>
                                    <span className="material-symbols-outlined text-sm">send</span>
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
