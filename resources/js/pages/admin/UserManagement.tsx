import React, { useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import AuthSplitLayout from '@/layouts/auth/auth-split-layout';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

export default function UserManagement() {
    // Local state for Phase 5 Step 20: Double-entry email confirmation
    const [confirmEmail, setConfirmEmail] = useState('');
    const [localValidationError, setLocalValidationError] = useState('');

    // Inertia Form State Management
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        role: 'accountant',
    });

    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        setLocalValidationError('');

        // 🌟 Phase 5 Step 20 Requirement: Double-entry email match validation
        if (data.email !== confirmEmail) {
            setLocalValidationError('The confirmation email does not match the primary email address.');
            return;
        }

        post(route('admin.users.store'), {
            onSuccess: () => {
                reset('name', 'email', 'password');
                setConfirmEmail('');
            },
        });
    };

    return (
        <AuthSplitLayout>
            <Head title="Register New System User" />
            
            <Card className="border-border shadow-md">
                <CardHeader className="space-y-1">
                    <CardTitle className="text-2xl font-bold tracking-tight text-foreground">
                        Register Account
                    </CardTitle>
                    <CardDescription>
                        Create authorized university portal profiles. All registration requests are permanently logged.
                    </CardDescription>
                </CardHeader>
                
                <form onSubmit={handleSubmit}>
                    <CardContent className="space-y-4">
                        
                        {/* Client-side Local Mismatch Error Alert */}
                        {localValidationError && (
                            <div className="rounded-md bg-destructive/10 p-3 text-sm text-destructive border border-destructive/20 font-medium">
                                {localValidationError}
                            </div>
                        )}

                        {/* Full Name Input */}
                        <div className="space-y-2">
                            <Label htmlFor="name">Full Name</Label>
                            <Input id="name" type="text" placeholder="e.g., Juan Dela Cruz" value={data.name} onChange={e => setData('name', e.target.value)} required />
                            {errors.name && <p className="text-xs font-semibold text-destructive">{errors.name}</p>}
                        </div>

                        {/* Email Input */}
                        <div className="space-y-2">
                            <Label htmlFor="email">Email Address</Label>
                            <Input id="email" type="email" placeholder="name@norsu.edu.ph" value={data.email} onChange={e => setData('email', e.target.value)} required />
                            {errors.email && <p className="text-xs font-semibold text-destructive">{errors.email}</p>}
                        </div>

                        {/* 🌟 Step 20 Mandatory Confirm Email Input */}
                        <div className="space-y-2">
                            <Label htmlFor="confirmEmail">Confirm Email Address</Label>
                            <Input id="confirmEmail" type="email" placeholder="Re-type email address" value={confirmEmail} onChange={e => setConfirmEmail(e.target.value)} required onPaste={(e) => e.preventDefault()} /* Blocks paste to ensure deliberate typing */ />
                        </div>

                        {/* Password Input */}
                        <div className="space-y-2">
                            <Label htmlFor="password">Temporary Password</Label>
                            <Input id="password" type="password" value={data.password} onChange={e => setData('password', e.target.value)} required />
                            {errors.password && <p className="text-xs font-semibold text-destructive">{errors.password}</p>}
                        </div>

                        {/* System Role Dropdown */}
                        <div className="space-y-2">
                            <Label htmlFor="role">Access Authorization Clearance</Label>
                            <select id="role" className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" value={data.role} onChange={e => setData('role', e.target.value)}>
                                <option value="accountant">University Accountant</option>
                                <option value="admin">System Operations Admin</option>
                            </select>
                            {errors.role && <p className="text-xs font-semibold text-destructive">{errors.role}</p>}
                        </div>

                    </CardContent>

                    <CardFooter>
                        <Button type="submit" className="w-full text-white font-medium shadow transition-all duration-150 active:scale-[0.98]" disabled={processing}>
                            {processing ? 'Processing Record...' : 'Provision Account'}
                        </Button>
                    </CardFooter>
                </form>
            </Card>
        </AuthSplitLayout>
    );
}
