import { Head, router } from '@inertiajs/react';
import React, { useMemo, useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import {
  Search,
  DollarSign,
  GraduationCap,
  Layers,
  Wallet,
  PlusCircle,
} from 'lucide-react';

// ---- Shape of one ledger row, matching what the controller now sends ----
export interface LedgerRecord {
  id: string | number;
  name: string;
  course: string;
  schoolYear: string;
  term: string;
  units: number;
  transactionDate: string;
  referenceNo: string;
  particulars: string;
  ratePerUnit: number;
  amount: number;
  arPayment: 'AR' | 'Payment' | 'Adjustment';
  remark: string;
  inputBy: string;
}

// Laravel's paginate() -> Inertia shape: { data, links, meta } (or the
// legacy flat shape with current_page/last_page/etc. directly on the
// object). We only rely on `data` here.
export interface LedgerPaginator {
  data: LedgerRecord[];
  links?: { url: string | null; label: string; active: boolean }[];
  meta?: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  // legacy/flat paginator fields, present depending on Inertia/Laravel setup
  current_page?: number;
  last_page?: number;
  per_page?: number;
  total?: number;
}

function currency(n: number) {
  return `₱${(n ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

interface IndexProps {
  records?: LedgerPaginator;
  filters?: { search?: string };
  stats?: { totalStudents?: number };
}

export default function Index({ records, filters, stats }: IndexProps) {
  // Unwrap the paginator safely. If `records` is missing or malformed,
  // fall back to an empty array instead of crashing.
  const rows: LedgerRecord[] = records?.data ?? [];

  const [searchQuery, setSearchQuery] = useState(filters?.search ?? '');

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    router.get('/graduate-ledger', searchQuery.trim() ? { search: searchQuery } : {}, {
      preserveState: true,
      replace: true,
    });
  };

  // Client-side filtering is now redundant with server-side search, but
  // kept here for instant feedback while typing before submit.
  const filtered = useMemo(() => {
    const q = searchQuery.trim().toLowerCase();
    if (!q) return rows;
    return rows.filter(
      (r) =>
        (r.name ?? '').toLowerCase().includes(q) ||
        (r.course ?? '').toLowerCase().includes(q) ||
        (r.referenceNo ?? '').toLowerCase().includes(q)
    );
  }, [rows, searchQuery]);

  // ---- Metrics derived from real fields ----
  const totalStudents = useMemo(() => stats?.totalStudents ?? new Set(rows.map((r) => r.name)).size, [rows, stats?.totalStudents]);
  const totalUnits = useMemo(() => rows.reduce((sum, r) => sum + (r.units || 0), 0), [rows]);
  const totalCharges = useMemo(
    () => rows.filter((r) => r.arPayment === 'AR').reduce((sum, r) => sum + (r.amount || 0), 0),
    [rows]
  );
  const totalPayments = useMemo(
    () => rows.filter((r) => r.arPayment === 'Payment').reduce((sum, r) => sum + (r.amount || 0), 0),
    [rows]
  );
  const outstandingBalance = totalCharges - totalPayments;

  const totalRecordCount = records?.meta?.total ?? records?.total ?? rows.length;

  return (
    <div className="min-h-screen bg-[#FAFAF5] p-4 md:p-8">
      <Head title="Graduate School Ledger" />

      <div className="max-w-7xl mx-auto space-y-6">

        {/* Top Header / Action Bar */}
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-[#CFE3FF] pb-5">
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-2xl font-bold tracking-tight text-[#0B3D91]">Graduate School Ledger</h1>
              <Badge variant="outline" className="bg-[#EAF2FF] text-[#0B62E0] border-[#B9D8FF] font-semibold">
                Postgraduate Registry
              </Badge>
            </div>
            <p className="text-sm text-[#5C7A9E] mt-0.5">Tuition, fees, and payment transactions by student.</p>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            <form onSubmit={handleSearchSubmit} className="relative w-full sm:w-64">
              <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-[#7FA6D6]" />
              <Input
                type="search"
                placeholder="Search name, course, or OR/JEV #..."
                className="pl-8 h-9 bg-white border-[#CFE3FF] focus-visible:ring-[#0F6FFF]"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
              />
            </form>

            <Button className="bg-[#0F6FFF] hover:bg-[#0B5DDB] text-white" onClick={() => router.get('/graduate-ledger/add')}>
              <PlusCircle className="h-4 w-4 mr-1.5" />
              New Transaction
            </Button>
          </div>
        </div>

        {/* Metrics Row — computed from actual ledger rows on this page */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <Card className="shadow-xs border border-[#CFE3FF] bg-white">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium text-[#5C7A9E]">Students on Ledger</CardTitle>
              <GraduationCap className="h-4 w-4 text-[#0F6FFF]" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold tracking-tight text-[#0B3D91]">{totalStudents}</div>
              <p className="text-[10px] text-[#8AA8CC] mt-1">Unique names matching the current search</p>
            </CardContent>
          </Card>

          <Card className="shadow-xs border border-[#CFE3FF] bg-white">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium text-[#5C7A9E]">Total Units</CardTitle>
              <Layers className="h-4 w-4 text-[#0F6FFF]" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold tracking-tight text-[#0B3D91]">{totalUnits}</div>
              <p className="text-[10px] text-[#8AA8CC] mt-1">Sum of units on this page</p>
            </CardContent>
          </Card>

          <Card className="shadow-xs border border-[#CFE3FF] bg-white">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium text-[#5C7A9E]">Total Charges (AR)</CardTitle>
              <DollarSign className="h-4 w-4 text-[#0F6FFF]" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold tracking-tight text-[#0B3D91]">{currency(totalCharges)}</div>
              <p className="text-[10px] text-[#8AA8CC] mt-1">Tuition + misc fees billed (this page)</p>
            </CardContent>
          </Card>

          <Card className="shadow-xs border border-[#CFE3FF] bg-white">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium text-[#5C7A9E]">Outstanding Balance</CardTitle>
              <Wallet className="h-4 w-4 text-[#0F6FFF]" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold tracking-tight text-[#0B3D91]">{currency(outstandingBalance)}</div>
              <p className="text-[10px] text-[#8AA8CC] mt-1">Charges minus payments (this page)</p>
            </CardContent>
          </Card>
        </div>

        {/* Ledger Table — the actual data, not a summary card */}
        <Card className="border border-[#CFE3FF] bg-white">
          <CardHeader className="pb-2">
            <CardTitle className="text-md text-[#0B3D91]">Transaction Ledger</CardTitle>
            <CardDescription className="text-[#7FA6D6]">
              {filtered.length} of {totalRecordCount} record{totalRecordCount === 1 ? '' : 's'}
            </CardDescription>
          </CardHeader>
          <CardContent className="overflow-x-auto">
            <table className="w-full text-sm border-collapse">
              <thead>
                <tr className="border-b border-[#CFE3FF] bg-[#F3F8FF]">
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 pl-2 whitespace-nowrap">Name</th>
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Course</th>
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">S.Y.</th>
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Term</th>
                  <th className="text-right font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Units</th>
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Trans. Date</th>
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Ref. (JEV/OR #)</th>
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Particulars</th>
                  <th className="text-right font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Rate/Unit</th>
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">AR/Payment</th>
                  <th className="text-right font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Amount</th>
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Remark</th>
                  <th className="text-left font-medium text-[#5C7A9E] py-2 pr-4 whitespace-nowrap">Input By</th>
                </tr>
              </thead>
              <tbody>
                {filtered.length === 0 ? (
                  <tr>
                    <td colSpan={13} className="text-center text-sm text-[#8AA8CC] py-8">
                      No transactions yet. Upload a batch file or add one manually.
                    </td>
                  </tr>
                ) : (
                  filtered.map((r) => (
                    <tr key={r.id} className="border-b border-[#EAF2FF] hover:bg-[#F3F8FF]">
                      <td className="py-2 pr-4 pl-2 font-medium whitespace-nowrap text-[#0B3D91]">{r.name}</td>
                      <td className="py-2 pr-4 text-[#334E68]">{r.course}</td>
                      <td className="py-2 pr-4 text-[#334E68]">{r.schoolYear}</td>
                      <td className="py-2 pr-4 text-[#334E68]">{r.term}</td>
                      <td className="py-2 pr-4 text-right text-[#334E68]">{r.units}</td>
                      <td className="py-2 pr-4 whitespace-nowrap text-[#334E68]">{r.transactionDate}</td>
                      <td className="py-2 pr-4 whitespace-nowrap text-[#334E68]">{r.referenceNo}</td>
                      <td className="py-2 pr-4 text-[#334E68]">{r.particulars}</td>
                      <td className="py-2 pr-4 text-right text-[#334E68]">{currency(r.ratePerUnit)}</td>
                      <td className="py-2 pr-4">
                        <Badge variant="outline" className="border-[#B9D8FF] text-[#0B62E0] bg-[#EAF2FF]">
                          {r.arPayment}
                        </Badge>
                      </td>
                      <td className="py-2 pr-4 text-right font-medium text-[#0B3D91]">{currency(r.amount)}</td>
                      <td className="py-2 pr-4 text-[#8AA8CC]">{r.remark}</td>
                      <td className="py-2 pr-4 text-[#8AA8CC]">{r.inputBy}</td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </CardContent>
        </Card>

      </div>
    </div>
  );
}