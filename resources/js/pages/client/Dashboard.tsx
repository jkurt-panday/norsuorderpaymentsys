import { Head, Link, usePage } from '@inertiajs/react';
import { FileText, ArrowRight, CheckCircle2, ShieldCheck, User } from 'lucide-react';
import React from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

export default function ClientDashboard() {
    const { auth } = usePage<{ auth: { user: { name: string; email: string; role: string } | null } }>().props;
    const user = auth?.user;

    return (
        <div className="max-w-5xl mx-auto space-y-6">
            <Head title="Client Dashboard" />

            {/* Header Banner */}
            <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-[#CFE3FF] pb-5">
                <div>
                    <div className="flex items-center gap-2">
                        <h1 className="text-2xl font-bold tracking-tight text-[#0B3D91]">
                            Welcome back, {user?.name || 'Client'}!
                        </h1>
                        <Badge variant="outline" className="bg-[#EAF2FF] text-[#0B62E0] border-[#B9D8FF] font-semibold capitalize">
                            {user?.role || 'Client'} Account
                        </Badge>
                    </div>
                    <p className="text-sm text-[#5C7A9E] mt-1">
                        NORSU Order of Payment System — Client Portal
                    </p>
                </div>

                <div>
                    <Link href="/public/submit">
                        <Button className="bg-[#0F6FFF] hover:bg-[#0B5DDB] text-white gap-2 shadow-sm">
                            <FileText className="h-4 w-4" />
                            Submit New Request
                            <ArrowRight className="h-4 w-4" />
                        </Button>
                    </Link>
                </div>
            </div>

            {/* Main Cards Grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {/* Account Details Card */}
                <Card className="border-[#CFE3FF] bg-white shadow-sm">
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base text-[#0B3D91] flex items-center gap-2">
                            <User className="h-4 w-4 text-[#0F6FFF]" />
                            Account Information
                        </CardTitle>
                        <CardDescription className="text-[#7FA6D6]">
                            Your registered client profile details
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm text-[#334E68]">
                        <div className="flex justify-between py-2 border-b border-[#EAF2FF]">
                            <span className="text-[#7FA6D6]">Full Name</span>
                            <span className="font-medium text-[#0B3D91]">{user?.name}</span>
                        </div>
                        <div className="flex justify-between py-2 border-b border-[#EAF2FF]">
                            <span className="text-[#7FA6D6]">Email Address</span>
                            <span className="font-medium text-[#0B3D91]">{user?.email}</span>
                        </div>
                        <div className="flex justify-between py-2">
                            <span className="text-[#7FA6D6]">Account Status</span>
                            <span className="inline-flex items-center gap-1 font-semibold text-emerald-600">
                                <CheckCircle2 className="h-4 w-4" /> Active
                            </span>
                        </div>
                    </CardContent>
                </Card>

                {/* Quick Actions Card */}
                <Card className="border-[#CFE3FF] bg-white shadow-sm">
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base text-[#0B3D91] flex items-center gap-2">
                            <ShieldCheck className="h-4 w-4 text-[#0F6FFF]" />
                            Services & Actions
                        </CardTitle>
                        <CardDescription className="text-[#7FA6D6]">
                            Available client services for payment processing
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="rounded-lg border border-[#EAF2FF] bg-[#F8FAFC] p-4 flex items-start gap-3">
                            <FileText className="h-5 w-5 text-[#0F6FFF] shrink-0 mt-0.5" />
                            <div>
                                <h4 className="text-sm font-semibold text-[#0B3D91]">Submit Order of Payment</h4>
                                <p className="text-xs text-[#5C7A9E] mt-1">
                                    Fill out and submit an order of payment request form directly to the university cashier.
                                </p>
                                <Link
                                    href="/public/submit"
                                    className="inline-flex items-center gap-1 text-xs font-semibold text-[#0F6FFF] hover:underline mt-2"
                                >
                                    Go to Submission Form →
                                </Link>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
