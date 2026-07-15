import React, { useEffect } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AuthSplitLayout from '@/layouts/auth/auth-split-layout';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

export default function Login({ status, canResetPassword }: { status?: string; canResetPassword?: boolean }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    // Automatically wipe password inputs clean if there is an auth error
    useEffect(() => {
        return () => {
            reset('password');
        };
    }, []);

    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post(route('login'));
    };

    return (
        <AuthSplitLayout>
            <Head title="Portal Authentication" />

            {/* If Fortify or Laravel returns a status message (e.g., password reset link sent) */}
            {status && (
                <div className="mb-4 text-sm font-medium text-emerald-600 bg-emerald-50 border border-emerald-200 p-3 rounded-md">
                    {status}
                </div>
            )}

            <Card className="border-border shadow-md">
                <CardHeader className="space-y-1">
                    <CardTitle className="text-2xl font-bold tracking-tight text-foreground animate-fade-in">
                        Sign In
                    </CardTitle>
                    <CardDescription>
                        Access the NORSU Order & Payment administrative control network.
                    </CardDescription>
                </CardHeader>

                <form onSubmit={handleSubmit}>
                    <CardContent className="space-y-4">
                        
                        {/* Email Input Field */}
                        <div className="space-y-2">
                            <Label htmlFor="email">Official Email</Label>
                            <Input 
                                id="email" 
                                type="email" 
                                name="email"
                                placeholder="username@norsu.edu.ph" 
                                value={data.email} 
                                autoComplete="username"
                                onChange={e => setData('email', e.target.value)} 
                                required 
                            />
                            {errors.email && <p className="text-xs font-semibold text-destructive">{errors.email}</p>}
                        </div>

                        {/* Password Input Field */}
                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <Label htmlFor="password">Security Password</Label>
                                {canResetPassword && (
                                    <Link 
                                        href={route('password.request')} 
                                        className="text-xs font-medium text-primary hover:underline hover:text-primary/90"
                                    >
                                        Forgot?
                                    </Link>
                                )}
                            </div>
                            <Input 
                                id="password" 
                                type="password" 
                                name="password"
                                value={data.password} 
                                autoComplete="current-password"
                                onChange={e => setData('password', e.target.value)} 
                                required 
                            />
                            {errors.password && <p className="text-xs font-semibold text-destructive">{errors.password}</p>}
                        </div>

                        {/* Keep Me Signed In (Session Security) */}
                        <div className="flex items-center space-x-2">
                            <input
                                id="remember"
                                type="checkbox"
                                name="remember"
                                checked={data.remember}
                                onChange={e => setData('remember', e.target.checked)}
                                className="h-4 w-4 rounded border-input text-primary focus:ring-ring focus:ring-2 bg-background"
                            />
                            <Label htmlFor="remember" className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer">
                                Keep me signed in
                            </Label>
                        </div>

                    </CardContent>

                    <CardFooter className="flex flex-col space-y-4">
                        <Button 
                            type="submit" 
                            className="w-full text-white font-medium shadow transition-all duration-150 active:scale-[0.98]" 
                            disabled={processing}
                        >
                            {processing ? 'Verifying Credentials...' : 'Authenticate Access'}
                        </Button>
                    </CardFooter>
                </form>
            </Card>
        </AuthSplitLayout>
    );
}