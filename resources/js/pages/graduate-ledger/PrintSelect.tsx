import { Head, router } from '@inertiajs/react';
import { Printer, ArrowLeft } from 'lucide-react';
import React, { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';

interface Props {
  students: string[];
  selectedStudent: string | null;
  records: any[];
  summary: {
    totalCharges: number;
    totalPayments: number;
    outstandingBalance: number;
  };
}

function currency(n: number) {
  return `₱${(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function absAmount(val: any): number {
  if (!val) {
return 0;
}

  const num = parseFloat(String(val).replace(/[^\d.]/g, ''));

  return isNaN(num) ? 0 : num;
}

function formatTransactionDate(value?: string | null) {
  if (!value) {
return '-';
}

  const normalized = String(value).trim();

  if (!normalized) {
return '-';
}

  const datePart = normalized.includes('T') ? normalized.split('T')[0] : normalized.split(' ')[0];
  const parsedDate = new Date(`${datePart}T00:00:00`);

  if (Number.isNaN(parsedDate.getTime())) {
    return datePart;
  }

  return parsedDate.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
  });
}

export default function PrintSelect({ students, selectedStudent, records = [], summary }: Props) {
  const [selected, setSelected] = useState(selectedStudent || '');

  const handleStudentSelect = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const val = e.target.value;
    setSelected(val);
    router.get('/graduate-ledger/print-select', { student: val }, { preserveState: true });
  };

  const handleOpenPdf = () => {
    if (!selected) {
return;
}

    window.open(`/graduate-ledger/pdf?student=${encodeURIComponent(selected)}`, '_blank');
  };

  return (
    <div className="min-h-screen bg-[#FAFAF5] p-4 md:p-8">
      <Head title="Print Student Statement" />

      <div className="max-w-5xl mx-auto space-y-6">
        
        {/* Header */}
        <div className="flex items-center justify-between border-b border-[#CFE3FF] pb-4">
          <div>
            <div className="flex items-center gap-2">
              <Button
                variant="outline"
                size="sm"
                onClick={() => router.get('/graduate-ledger')}
                className="border-[#CFE3FF] text-[#0B3D91]"
              >
                <ArrowLeft className="h-4 w-4 mr-1" /> Back
              </Button>
              <h1 className="text-2xl font-bold text-[#0B3D91]">Student Statement Printer</h1>
            </div>
            <p className="text-sm text-[#5C7A9E] mt-1">
              Select a graduate student to review their transaction breakdown and print a formal SOA PDF.
            </p>
          </div>
        </div>

        {/* Selection Card */}
        <Card className="border-[#CFE3FF] bg-white">
          <CardHeader>
            <CardTitle className="text-base text-[#0B3D91]">Select Graduate Student</CardTitle>
            <CardDescription className="text-[#7FA6D6]">
              Choose from active registered students in the ledger database.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="flex flex-col sm:flex-row gap-4 items-center">
              <select
                className="w-full sm:w-2/3 p-2.5 border border-[#CFE3FF] rounded-md text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0F6FFF]"
                value={selected}
                onChange={handleStudentSelect}
              >
                <option value="">-- Select Student Name --</option>
                {students.map((name) => (
                  <option key={name} value={name}>
                    {name}
                  </option>
                ))}
              </select>

              <Button
                disabled={!selected}
                onClick={handleOpenPdf}
                className="w-full sm:w-auto bg-[#0F6FFF] hover:bg-[#0B5DDB] text-white"
              >
                <Printer className="h-4 w-4 mr-2" />
                Print PDF Statement
              </Button>
            </div>
          </CardContent>
        </Card>

        {/* Student Breakdown */}
        {selected && (
          <div className="space-y-6">
            
            {/* Metric Overview */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <Card className="border-[#CFE3FF] bg-white">
                <CardHeader className="pb-2">
                  <CardTitle className="text-xs font-medium text-[#5C7A9E]">Total Billed Charges (AR)</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="text-xl font-bold text-[#0B3D91]">{currency(summary.totalCharges)}</div>
                </CardContent>
              </Card>

              <Card className="border-[#CFE3FF] bg-white">
                <CardHeader className="pb-2">
                  <CardTitle className="text-xs font-medium text-[#5C7A9E]">Total Payments Received</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="text-xl font-bold text-emerald-600">{currency(summary.totalPayments)}</div>
                </CardContent>
              </Card>

              <Card className="border-[#CFE3FF] bg-white">
                <CardHeader className="pb-2">
                  <CardTitle className="text-xs font-medium text-[#5C7A9E]">Current Outstanding Balance</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className={`text-xl font-bold ${summary.outstandingBalance > 0 ? 'text-amber-600' : 'text-[#0B3D91]'}`}>
                    {currency(summary.outstandingBalance)}
                  </div>
                </CardContent>
              </Card>
            </div>

            {/* Preview Table */}
            <Card className="border-[#CFE3FF] bg-white">
              <CardHeader className="border-b border-[#EAF2FF] pb-3">
                <div className="flex justify-between items-center">
                  <div>
                    <CardTitle className="text-base text-[#0B3D91]">{selected}</CardTitle>
                    <CardDescription className="text-xs text-[#7FA6D6]">
                      {records.length} total ledger entry/entries found
                    </CardDescription>
                  </div>
                  <Badge variant="outline" className="bg-[#EAF2FF] text-[#0B62E0] border-[#B9D8FF]">
                    {records[0]?.course || 'Graduate Program'}
                  </Badge>
                </div>
              </CardHeader>
              <CardContent className="pt-4 overflow-x-auto">
                <table className="w-full text-sm border-collapse">
                  <thead>
                    <tr className="bg-[#F3F8FF] text-[#5C7A9E] text-left border-b border-[#CFE3FF]">
                      <th className="py-2 px-3">Date</th>
                      <th className="py-2 px-3">S.Y. / Term</th>
                      <th className="py-2 px-3">Ref / OR #</th>
                      <th className="py-2 px-3">Particulars</th>
                      <th className="py-2 px-3">Type</th>
                      <th className="py-2 px-3 text-right">Amount</th>
                    </tr>
                  </thead>
                  <tbody>
                    {records.length === 0 ? (
                      <tr>
                        <td colSpan={6} className="text-center py-6 text-[#8AA8CC]">
                          No records found for this student.
                        </td>
                      </tr>
                    ) : (
                      records.map((r) => (
                        <tr key={r.id} className="border-b border-[#EAF2FF] hover:bg-[#F3F8FF]">
                          <td className="py-2 px-3 text-[#334E68]">{formatTransactionDate(r.transaction_date)}</td>
                          <td className="py-2 px-3 text-[#334E68]">
                            {r.school_year} ({r.semester_short || r.semester})
                          </td>
                          <td className="py-2 px-3 text-[#334E68]">{r.reference_or_jev_number || '-'}</td>
                          <td className="py-2 px-3 text-[#334E68]">{r.particulars || '-'}</td>
                          <td className="py-2 px-3 font-medium text-[#0B3D91]">{r.ar_payment}</td>
                          <td className="py-2 px-3 text-right font-medium text-[#0B3D91]">
                            {currency(absAmount(r.amount))}
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </CardContent>
            </Card>

          </div>
        )}

      </div>
    </div>
  );
}