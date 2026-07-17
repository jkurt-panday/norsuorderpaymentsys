import { Head, router } from '@inertiajs/react';
import React, { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { 
  Search, 
  BookOpen, 
  DollarSign, 
  FileText, 
  GraduationCap, 
  ArrowUpRight, 
  ShieldAlert, 
  CheckCircle2 
} from 'lucide-react';

export default function Index() {
  const [searchQuery, setSearchQuery] = useState('');

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (searchQuery.trim()) {
      // Directs the user to the ledger table with their search filter
      router.get(`/graduate-ledger?search=${encodeURIComponent(searchQuery)}`);
    }
  };

  return (
    <div className="min-h-screen bg-gray-50/50 dark:bg-gray-900 p-4 md:p-8">
      <Head title="Graduate School Ledger" />

      <div className="max-w-7xl mx-auto space-y-6">
        
        {/* Top Header / Action Bar */}
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-gray-200 dark:border-gray-800 pb-5">
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-2xl font-bold tracking-tight text-[#091d2e] dark:text-white">Graduate School Ledger</h1>
              <Badge variant="secondary" className="bg-[#091d2e] text-white dark:bg-blue-900 dark:text-blue-200 font-semibold">
                Postgraduate Registry
              </Badge>
            </div>
            <p className="text-sm text-muted-foreground mt-0.5">Manage master's, doctoral, and thesis tuition, fees, and clearances.</p>
          </div>
          
          <div className="flex flex-wrap items-center gap-2">
            {/* Quick Graduate Student Search */}
            <form onSubmit={handleSearchSubmit} className="relative w-full sm:w-60">
              <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
              <Input
                type="search"
                placeholder="Search Master/Doc ID..."
                className="pl-8 h-9"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
              />
            </form>
            
            <Button className="bg-[#091d2e] hover:bg-[#122e47] text-white" onClick={() => router.get('/graduate-ledger/add')}>
              New Student Account
            </Button>
          </div>
        </div>

        {/* Graduate Metrics Row */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <Card className="shadow-xs border-l-4 border-l-[#091d2e]">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">Active Grads</CardTitle>
              <GraduationCap className="h-4 w-4 text-[#091d2e]" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold tracking-tight">0</div>
              <p className="text-[10px] text-muted-foreground mt-1">MA, MS, & PhD Enrollees</p>
            </CardContent>
          </Card>

          <Card className="shadow-xs">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">Thesis/Dissertation Holds</CardTitle>
              <ShieldAlert className="h-4 w-4 text-amber-500" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold tracking-tight text-amber-600">0</div>
              <p className="text-[10px] text-muted-foreground mt-1">Accounts with unpaid defenses</p>
            </CardContent>
          </Card>

          <Card className="shadow-xs">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">Graduate Receivables</CardTitle>
              <DollarSign className="h-4 w-4 text-emerald-500" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold tracking-tight text-emerald-600">₱0.00</div>
              <p className="text-[10px] text-muted-foreground mt-1">Outstanding tuition & local fees</p>
            </CardContent>
          </Card>

          <Card className="shadow-xs">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">Exam Clearances</CardTitle>
              <CheckCircle2 className="h-4 w-4 text-indigo-500" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold tracking-tight">0</div>
              <p className="text-[10px] text-muted-foreground mt-1">Comprehensive Exam ready</p>
            </CardContent>
          </Card>
        </div>

        {/* Graduate School Actions Grid */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          
          {/* Main Action Block */}
          <div className="lg:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4 h-fit">
            <Card className="hover:border-blue-300 transition-all cursor-pointer" onClick={() => router.get('/graduate-ledger/upload')}>
              <CardHeader>
                <CardTitle className="text-md flex items-center justify-between">
                  Upload Graduate Fees Template
                  <ArrowUpRight className="h-4 w-4 text-muted-foreground" />
                </CardTitle>
                <CardDescription>
                  Import special batch files for doctoral tuition structures, research funding allocations, or lab residency fees.
                </CardDescription>
              </CardHeader>
              <CardContent>
                <Button size="sm" className="w-full bg-[#091d2e] hover:bg-[#122e47]">Go to Excel Upload</Button>
              </CardContent>
            </Card>

            <Card className="hover:border-blue-300 transition-all cursor-pointer" onClick={() => router.get('/graduate-ledger/audit')}>
              <CardHeader>
                <CardTitle className="text-md flex items-center justify-between">
                  Audit Thesis & Oral Defense Fees
                  <ArrowUpRight className="h-4 w-4 text-muted-foreground" />
                </CardTitle>
                <CardDescription>
                  Check panelist honorarium balances, dissertation panel allocations, and student defense payments.
                </CardDescription>
              </CardHeader>
              <CardContent>
                <Button size="sm" variant="outline" className="w-full">Open Defense Registry</Button>
              </CardContent>
            </Card>

            <Card className="hover:border-blue-300 transition-all cursor-pointer md:col-span-2" onClick={() => router.get('/graduate-ledger/clearances')}>
              <CardHeader>
                <CardTitle className="text-md flex items-center justify-between">
                  Process Graduation Clearances
                  <ArrowUpRight className="h-4 w-4 text-muted-foreground" />
                </CardTitle>
                <CardDescription>
                  Verify final statements of accounts (SOAs) before clearing students for terminal program comprehensive evaluations or graduation hooding.
                </CardDescription>
              </CardHeader>
              <CardContent>
                <Button size="sm" variant="secondary" className="w-full">Manage Clearances</Button>
              </CardContent>
            </Card>
          </div>

          {/* Right Sidebar Status Details */}
          <div className="lg:col-span-1">
            <Card className="h-full">
              <CardHeader>
                <CardTitle className="text-md">Graduate Audit Integrity</CardTitle>
                <CardDescription>Status check on master's and doctoral databases.</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="rounded-lg bg-blue-50/50 dark:bg-blue-950/20 border border-blue-100 dark:border-blue-900/50 p-3">
                  <h4 className="text-xs font-semibold text-blue-900 dark:text-blue-300 mb-1 flex items-center gap-1.5">
                    <BookOpen className="h-3.5 w-3.5 text-blue-600" />
                    Specialized Rate Mapping
                  </h4>
                  <p className="text-[11px] text-blue-700 dark:text-blue-400">
                    Graduate school credit rates are handled distinctively from law or undergraduate units. Ensure templates use the standard master's rate multiplier.
                  </p>
                </div>
                
                <div className="border-l-2 border-emerald-500 pl-3 py-1 space-y-1">
                  <p className="text-xs font-semibold text-emerald-800 dark:text-emerald-400">Database Connection Secure</p>
                  <p className="text-[11px] text-muted-foreground">Ready to read and write graduate record models.</p>
                </div>
              </CardContent>
            </Card>
          </div>

        </div>
      </div>
    </div>
  );
}