import { Head, router } from '@inertiajs/react';
import React, { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
  CardFooter,
} from '@/components/ui/card';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { ArrowLeft, FileText, Printer } from 'lucide-react';

interface LawLedgerRecord {
  id: number;
  studentId: string;
  name: string;
  program: string;
  yearLevel: string;
  academicYear: string;
  semester: string;
  units: number;
  transactionDate: string;
  dueDate: string;
  referenceNo: string;
  particulars: string;
  tuitionPerUnitOrMisc: number;
  transactionType: string;
  amount: number;
  remainingBalance: number;
  status: string;
  remark: string;
  inputBy: string;
}

interface PrintSelectProps {
  students: string[];
  selectedStudent?: string;
  records?: LawLedgerRecord[];
  summary?: {
    totalAssessments: number;
    totalPayments: number;
    outstandingBalance: number;
  };
}

function currency(n: number) {
  return `₱${(n ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function formatDate(value?: string | null) {
  if (!value) return '-';
  
  const normalized = String(value).trim();
  if (!normalized) return '-';

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

function getStatusBadgeVariant(status: string) {
  const statusUpper = status?.toUpperCase() || '';
  
  if (statusUpper === 'PAID' || statusUpper === 'SETTLED') {
    return 'default';
  }
  if (statusUpper === 'PENDING') {
    return 'secondary';
  }
  if (statusUpper === 'OVERDUE') {
    return 'destructive';
  }
  if (statusUpper === 'PARTIAL PAYMENT') {
    return 'outline';
  }
  
  return 'outline';
}

function getStatusBadgeClass(status: string) {
  const statusUpper = status?.toUpperCase() || '';
  
  if (statusUpper === 'PAID' || statusUpper === 'SETTLED') {
    return 'bg-green-100 text-green-700 border-green-200 hover:bg-green-100';
  }
  if (statusUpper === 'PENDING') {
    return 'bg-yellow-100 text-yellow-700 border-yellow-200 hover:bg-yellow-100';
  }
  if (statusUpper === 'OVERDUE') {
    return 'bg-red-100 text-red-700 border-red-200 hover:bg-red-100';
  }
  if (statusUpper === 'PARTIAL PAYMENT') {
    return 'bg-blue-100 text-blue-700 border-blue-200 hover:bg-blue-100';
  }
  
  return 'bg-gray-100 text-gray-700 border-gray-200 hover:bg-gray-100';
}

function getTransactionTypeBadgeClass(type: string) {
  const typeUpper = type?.toUpperCase() || '';
  
  if (typeUpper === 'ASSESSMENT' || typeUpper === 'AR') {
    return 'bg-purple-100 text-purple-700 border-purple-200';
  }
  if (typeUpper === 'PAYMENT') {
    return 'bg-green-100 text-green-700 border-green-200';
  }
  if (typeUpper === 'ADJUSTMENT') {
    return 'bg-orange-100 text-orange-700 border-orange-200';
  }
  
  return 'bg-gray-100 text-gray-700 border-gray-200';
}

export default function PrintSelect({ students, selectedStudent, records, summary }: PrintSelectProps) {
  const [selectedStudentState, setSelectedStudentState] = useState(selectedStudent || '');

  const handleStudentChange = (value: string | null) => {
    setSelectedStudentState(value ?? '');
    if (value) {
      router.get(`/law-ledger/print-select?student=${encodeURIComponent(value)}`);
    }
  };

  const handleGeneratePdf = () => {
    if (selectedStudentState) {
      window.open(`/law-ledger/pdf?student=${encodeURIComponent(selectedStudentState)}`, '_blank');
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50/30 p-4 md:p-6 lg:p-8">
      <Head title="Print Statement - Law School Ledger" />

      <div className="max-w-5xl mx-auto space-y-6">
        {/* Header */}
        <div className="flex items-center gap-4">
          <Button
            variant="ghost"
            size="sm"
            onClick={() => router.get('/law-ledger')}
            className="h-9"
          >
            <ArrowLeft className="h-4 w-4 mr-2" />
            Back to Ledger
          </Button>
          <div>
            <h1 className="text-2xl font-bold tracking-tight text-slate-900">Print Statement of Account</h1>
            <p className="text-sm text-slate-500 mt-0.5">Generate PDF statements for students</p>
          </div>
        </div>

        {/* Student Selection */}
        <Card className="border-slate-200 shadow-sm bg-white">
          <CardHeader>
            <CardTitle className="text-base font-semibold text-slate-900 flex items-center gap-2">
              <FileText className="h-4 w-4 text-blue-600" />
              Select Student
            </CardTitle>
            <CardDescription className="text-slate-500">
              Choose a student to view and print their statement of account
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="flex flex-col sm:flex-row items-start sm:items-center gap-4">
              <div className="flex-1 w-full sm:w-auto">
                <Select value={selectedStudentState} onValueChange={handleStudentChange}>
                  <SelectTrigger className="h-10 bg-white border-slate-200 w-full sm:w-96">
                    <SelectValue placeholder="Select a student" />
                  </SelectTrigger>
                  <SelectContent>
                    {students.map(student => (
                      <SelectItem key={student} value={student}>{student}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <Button
                onClick={handleGeneratePdf}
                disabled={!selectedStudentState}
                className="h-10 bg-blue-600 hover:bg-blue-700 text-white shadow-sm"
              >
                <Printer className="h-4 w-4 mr-2" />
                Generate PDF
              </Button>
            </div>
          </CardContent>
        </Card>

        {/* Student Records and Summary */}
        {selectedStudentState && records && records.length > 0 && (
          <>
            {/* Summary Cards */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <Card className="border-slate-200 shadow-sm bg-white">
                <CardHeader className="pb-2">
                  <CardTitle className="text-sm font-medium text-slate-600">Total Assessments</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-bold text-slate-900">{currency(summary?.totalAssessments || 0)}</div>
                </CardContent>
              </Card>

              <Card className="border-slate-200 shadow-sm bg-white">
                <CardHeader className="pb-2">
                  <CardTitle className="text-sm font-medium text-slate-600">Total Payments</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-bold text-slate-900">{currency(summary?.totalPayments || 0)}</div>
                </CardContent>
              </Card>

              <Card className="border-slate-200 shadow-sm bg-white">
                <CardHeader className="pb-2">
                  <CardTitle className="text-sm font-medium text-slate-600">Outstanding Balance</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-bold text-slate-900">{currency(summary?.outstandingBalance || 0)}</div>
                </CardContent>
              </Card>
            </div>

            {/* Student Ledger Table */}
            <Card className="border-slate-200 shadow-sm bg-white">
              <CardHeader>
                <CardTitle className="text-base font-semibold text-slate-900">
                  {selectedStudentState} - Ledger Details
                </CardTitle>
                <CardDescription className="text-slate-500">
                  Showing {records.length} transaction{records.length === 1 ? '' : 's'}
                </CardDescription>
              </CardHeader>
              <CardContent className="p-0">
                <div className="overflow-x-auto">
                  <Table>
                    <TableHeader>
                      <TableRow className="bg-slate-50 hover:bg-slate-50">
                        <TableHead>Date</TableHead>
                        <TableHead>Reference/JEV No.</TableHead>
                        <TableHead>Particulars</TableHead>
                        <TableHead>Type</TableHead>
                        <TableHead className="text-right">Amount</TableHead>
                        <TableHead>Status</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {records.map((record) => (
                        <TableRow key={record.id} className="hover:bg-slate-50 transition-colors">
                          <TableCell>{formatDate(record.transactionDate)}</TableCell>
                          <TableCell className="font-mono text-sm">{record.referenceNo || '-'}</TableCell>
                          <TableCell>{record.particulars || '-'}</TableCell>
                          <TableCell>
                            <Badge 
                              variant="outline" 
                              className={`${getTransactionTypeBadgeClass(record.transactionType)} border text-xs`}
                            >
                              {record.transactionType || '-'}
                            </Badge>
                          </TableCell>
                          <TableCell className="text-right font-medium">{currency(record.amount)}</TableCell>
                          <TableCell>
                            <Badge 
                              variant={getStatusBadgeVariant(record.status)}
                              className={`${getStatusBadgeClass(record.status)} border text-xs`}
                            >
                              {record.status || '-'}
                            </Badge>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
              </CardContent>
            </Card>
          </>
        )}

        {selectedStudentState && (!records || records.length === 0) && (
          <Card className="border-slate-200 shadow-sm bg-white">
            <CardContent className="text-center py-12">
              <FileText className="h-12 w-12 text-slate-300 mx-auto mb-3" />
              <p className="text-sm font-medium text-slate-600">No records found</p>
              <p className="text-xs text-slate-400 mt-1">
                This student has no transactions on record
              </p>
            </CardContent>
          </Card>
        )}
      </div>
    </div>
  );
}
