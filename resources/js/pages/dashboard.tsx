import { Head, useForm, usePage, Link } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import { dashboard } from '@/routes';
import { store as storeAdminUser } from '@/routes/admin/users';

export default function Dashboard() {
    const { auth } = usePage<{ auth: { user: { role: string } | null } }>().props;
    const [confirmEmail, setConfirmEmail] = useState('');
    const [confirmationError, setConfirmationError] = useState('');
    
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '', email: '', password: '', role: 'accountant',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (data.email !== confirmEmail) {
            setConfirmationError('The email addresses do not match.');

            return;
        }

        setConfirmationError('');
        post(storeAdminUser.url(), {
            onSuccess: () => {
                reset();
                setConfirmEmail('');
            },
        });
    };

    return (
        <>
            <Head title="Dashboard" />
            
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                
                {/* Logout Link acting as a button */}
                <div className="flex justify-between items-center bg-card border rounded-lg p-4 shadow-sm">
                    <div>
                        <h2 className="text-lg font-semibold text-foreground">Welcome, {auth.user?.name}!</h2>
                        <p className="text-sm text-muted-foreground">
                            Logged in as: <span className="font-medium text-foreground">{auth.user?.email}</span> | Role: <span className="inline-flex items-center rounded bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary capitalize">{auth.user?.role}</span>
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Link
                            href="/admin/users"
                            className="inline-flex items-center justify-center rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring border border-transparent bg-primary text-primary-foreground shadow hover:bg-primary/95 h-9 px-4 py-2"
                        >
                            Test Admin Middleware Route
                        </Link>
                        <Link 
                            href="/logout" 
                            method="post" 
                            as="button"
                            className="inline-flex items-center justify-center rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 border border-input bg-background shadow-sm hover:bg-accent hover:text-accent-foreground h-9 px-4 py-2"
                        >
                            Logout
                        </Link>
                    </div>
                </div>

                <div className="bg-muted/50 border border-dashed rounded-lg p-4">
                    <h3 className="text-sm font-semibold text-foreground">Role-Based Middleware Verification Info:</h3>
                    <p className="text-xs text-muted-foreground mt-1">
                        Clicking the <strong>"Test Admin Middleware Route"</strong> button above routes to <code>/admin/users</code> (guarded by the <code>admin</code> middleware).
                    </p>
                    <ul className="list-disc pl-5 mt-2 text-xs space-y-1">
                        {auth.user?.role === 'admin' ? (
                            <li className="text-green-600 dark:text-green-400 font-medium">
                                ✔ Your role is <span className="capitalize">{auth.user?.role}</span>: Accessing that route will succeed and display the User Management screen.
                            </li>
                        ) : (
                            <li className="text-amber-600 dark:text-amber-400 font-medium">
                                ℹ Your role is <span className="capitalize">{auth.user?.role}</span>: Accessing that route will be blocked by the <code>EnsureUserIsAdmin</code> middleware, returning a <code>403 Forbidden</code> response.
                            </li>
                        )}
                    </ul>
                </div>

                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                </div>

                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
                    <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                </div>

                {auth.user?.role === 'admin' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Register New Accountant Account</CardTitle>
                            <CardDescription>Provision an accountant account for the NORSU system.</CardDescription>
                        </CardHeader>
                        <form onSubmit={submit}>
                            <CardContent className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                                    {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="email">Email</Label>
                                    <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} required />
                                    {errors.email && <p className="text-sm text-destructive">{errors.email}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="confirm-email">Confirm email</Label>
                                    <Input id="confirm-email" type="email" value={confirmEmail} onChange={(e) => setConfirmEmail(e.target.value)} required />
                                    {confirmationError && <p className="text-sm text-destructive">{confirmationError}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="password">Temporary password</Label>
                                    <Input id="password" type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} required />
                                    {errors.password && <p className="text-sm text-destructive">{errors.password}</p>}
                                </div>
                            </CardContent>
                            <CardFooter>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Creating…' : 'Create accountant account'}
                                </Button>
                            </CardFooter>
                        </form>
                    </Card>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};