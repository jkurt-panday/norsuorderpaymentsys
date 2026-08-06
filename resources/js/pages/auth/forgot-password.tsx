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
                <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-600">
                    {status}
                </div>
            )}

            <Form {...email.form()}>
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <div className="space-y-2">
                            <Label
                                className="block text-xs font-semibold uppercase tracking-wider text-[#091d2e]"
                                htmlFor="email"
                            >
                                Email Address
                            </Label>
                            <div className="relative">
                                <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-xl text-[#717786]">
                                    mail
                                </span>
                                <Input
                                    className="placeholder:text-muted-foreground w-full rounded-lg border border-[#c1c6d7] bg-white py-3 pl-10 pr-4 text-sm text-[#091d2e] outline-none transition-all focus:border-[#005ab7] focus:ring-2 focus:ring-[#005ab7]"
                                    id="email"
                                    type="email"
                                    name="email"
                                    autoComplete="email"
                                    autoFocus
                                    placeholder="e.g. name@university.edu"
                                />
                            </div>
                            {errors.email && (
                                <p className="text-xs font-semibold text-[#ba1a1a]">
                                    {errors.email}
                                </p>
                            )}
                        </div>

                        <Button
                            type="submit"
                            className="flex w-full items-center justify-center gap-2 rounded-full border-0 bg-[#0072e5] py-6 text-xs font-semibold uppercase tracking-widest text-white transition-all duration-200 hover:bg-[#005ab7] hover:shadow-lg active:scale-[0.98]"
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
                                    <span className="material-symbols-outlined text-sm">
                                        send
                                    </span>
                                </>
                            )}
                        </Button>
                    </div>
                )}
            </Form>

            <div className="mt-6 text-center text-sm text-[#414754]">
                <span>Or, return to </span>
                <Link
                    href={login.url()}
                    className="text-xs font-semibold text-[#005ab7] hover:underline"
                >
                    log in
                </Link>
            </div>
        </AuthLayout>
    );
}
