import { Head, router, useForm } from '@inertiajs/react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { ArrowLeft } from 'lucide-react';

interface AddTransactionProps {
  filterOptions?: {
    programs: string[];
    yearLevels: string[];
    academicYears: string[];
    semesters: string[];
    statuses: string[];
  };
}

export default function AddTransaction({ filterOptions }: AddTransactionProps) {
  const { data, setData, post, processing, errors, reset } = useForm({
    student_id: '',
    student_name: '',
    program: '',
    year_level: '',
    academic_year: '',
    semester: '',
    units: '',
    transaction_date: '',
    due_date: '',
    reference_or_jev_number: '',
    particulars: '',
    tuition_per_unit_or_misc: '',
    transaction_type: 'Assessment',
    amount: '',
    remaining_balance: '',
    status: 'Pending',
    remarks: '',
    input_by: '',
  });

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    post('/law-ledger', {
      onSuccess: () => {
        reset();
      },
    });
  };

  const handleTransactionTypeChange = (value: string | null) => {
    setData('transaction_type', value ?? '');
    if (value === 'Assessment') {
      setData('status', 'Pending');
    } else if (value === 'Payment') {
      setData('status', 'Paid');
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50/30 p-4 md:p-6 lg:p-8">
      <Head title="Add New Transaction - Law School Ledger" />

      <div className="max-w-4xl mx-auto">
        {/* Header */}
        <div className="flex items-center gap-4 mb-6">
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
            <h1 className="text-2xl font-bold tracking-tight text-slate-900">Add New Transaction</h1>
            <p className="text-sm text-slate-500 mt-0.5">Enter a new ledger entry for a student</p>
          </div>
        </div>

        <form onSubmit={submit}>
          <div className="space-y-6">
            {/* Student Information */}
            <Card className="border-slate-200 shadow-sm bg-white">
              <CardHeader>
                <CardTitle className="text-base font-semibold text-slate-900 flex items-center gap-2">
                  Student Information
                </CardTitle>
                <CardDescription className="text-slate-500">
                  Basic student details and academic information
                </CardDescription>
              </CardHeader>
              <CardContent className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="student_id" className="text-sm font-medium text-slate-700">
                    Student ID <span className="text-red-500">*</span>
                  </Label>
                  <Input
                    id="student_id"
                    value={data.student_id}
                    onChange={e => setData('student_id', e.target.value)}
                    className="h-10 bg-white border-slate-200"
                    placeholder="e.g., 2021-00123"
                  />
                  {errors.student_id && (
                    <p className="text-sm text-red-600">{errors.student_id}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="student_name" className="text-sm font-medium text-slate-700">
                    Student Name <span className="text-red-500">*</span>
                  </Label>
                  <Input
                    id="student_name"
                    value={data.student_name}
                    onChange={e => setData('student_name', e.target.value)}
                    className="h-10 bg-white border-slate-200"
                    placeholder="e.g., CRUZ, JUAN DELA"
                  />
                  {errors.student_name && (
                    <p className="text-sm text-red-600">{errors.student_name}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="program" className="text-sm font-medium text-slate-700">
                    Program
                  </Label>
                  <Select value={data.program} onValueChange={value => setData('program', value ?? '')}>
                    <SelectTrigger className="h-10 bg-white border-slate-200">
                      <SelectValue placeholder="Select program" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="">Select program</SelectItem>
                      {filterOptions?.programs?.map(program => (
                        <SelectItem key={program} value={program}>{program}</SelectItem>
                      ))}
                      <SelectItem value="JD">JD</SelectItem>
                      <SelectItem value="LLM">LLM</SelectItem>
                      <SelectItem value="JSD">JSD</SelectItem>
                    </SelectContent>
                  </Select>
                  {errors.program && (
                    <p className="text-sm text-red-600">{errors.program}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="year_level" className="text-sm font-medium text-slate-700">
                    Year Level
                  </Label>
                  <Select value={data.year_level} onValueChange={value => setData('year_level', value ?? '')}>
                    <SelectTrigger className="h-10 bg-white border-slate-200">
                      <SelectValue placeholder="Select year level" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="">Select year level</SelectItem>
                      {filterOptions?.yearLevels?.map(level => (
                        <SelectItem key={level} value={level}>{level}</SelectItem>
                      ))}
                      <SelectItem value="1L">1L</SelectItem>
                      <SelectItem value="2L">2L</SelectItem>
                      <SelectItem value="3L">3L</SelectItem>
                      <SelectItem value="4L">4L</SelectItem>
                    </SelectContent>
                  </Select>
                  {errors.year_level && (
                    <p className="text-sm text-red-600">{errors.year_level}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="academic_year" className="text-sm font-medium text-slate-700">
                    Academic Year
                  </Label>
                  <Select value={data.academic_year} onValueChange={value => setData('academic_year', value ?? '')}>
                    <SelectTrigger className="h-10 bg-white border-slate-200">
                      <SelectValue placeholder="Select academic year" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="">Select academic year</SelectItem>
                      {filterOptions?.academicYears?.map(year => (
                        <SelectItem key={year} value={year}>{year}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  {errors.academic_year && (
                    <p className="text-sm text-red-600">{errors.academic_year}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="semester" className="text-sm font-medium text-slate-700">
                    Semester
                  </Label>
                  <Select value={data.semester} onValueChange={value => setData('semester', value ?? '')}>
                    <SelectTrigger className="h-10 bg-white border-slate-200">
                      <SelectValue placeholder="Select semester" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="">Select semester</SelectItem>
                      {filterOptions?.semesters?.map(sem => (
                        <SelectItem key={sem} value={sem}>{sem}</SelectItem>
                      ))}
                      <SelectItem value="First Semester">First Semester</SelectItem>
                      <SelectItem value="Second Semester">Second Semester</SelectItem>
                      <SelectItem value="Summer">Summer</SelectItem>
                    </SelectContent>
                  </Select>
                  {errors.semester && (
                    <p className="text-sm text-red-600">{errors.semester}</p>
                  )}
                </div>
              </CardContent>
            </Card>

            {/* Transaction Details */}
            <Card className="border-slate-200 shadow-sm bg-white">
              <CardHeader>
                <CardTitle className="text-base font-semibold text-slate-900 flex items-center gap-2">
                  Transaction Details
                </CardTitle>
                <CardDescription className="text-slate-500">
                  Financial transaction information
                </CardDescription>
              </CardHeader>
              <CardContent className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="transaction_date" className="text-sm font-medium text-slate-700">
                    Transaction Date
                  </Label>
                  <Input
                    id="transaction_date"
                    type="date"
                    value={data.transaction_date}
                    onChange={e => setData('transaction_date', e.target.value)}
                    className="h-10 bg-white border-slate-200"
                  />
                  {errors.transaction_date && (
                    <p className="text-sm text-red-600">{errors.transaction_date}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="due_date" className="text-sm font-medium text-slate-700">
                    Due Date
                  </Label>
                  <Input
                    id="due_date"
                    type="date"
                    value={data.due_date}
                    onChange={e => setData('due_date', e.target.value)}
                    className="h-10 bg-white border-slate-200"
                  />
                  {errors.due_date && (
                    <p className="text-sm text-red-600">{errors.due_date}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="units" className="text-sm font-medium text-slate-700">
                    Units
                  </Label>
                  <Input
                    id="units"
                    type="number"
                    step="0.01"
                    value={data.units}
                    onChange={e => setData('units', e.target.value)}
                    className="h-10 bg-white border-slate-200"
                    placeholder="e.g., 9.00"
                  />
                  {errors.units && (
                    <p className="text-sm text-red-600">{errors.units}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="reference_or_jev_number" className="text-sm font-medium text-slate-700">
                    Reference / JEV Number
                  </Label>
                  <Input
                    id="reference_or_jev_number"
                    value={data.reference_or_jev_number}
                    onChange={e => setData('reference_or_jev_number', e.target.value)}
                    className="h-10 bg-white border-slate-200"
                    placeholder="e.g., JEV-2024-001"
                  />
                  {errors.reference_or_jev_number && (
                    <p className="text-sm text-red-600">{errors.reference_or_jev_number}</p>
                  )}
                </div>

                <div className="space-y-2 md:col-span-2">
                  <Label htmlFor="particulars" className="text-sm font-medium text-slate-700">
                    Particulars
                  </Label>
                  <Input
                    id="particulars"
                    value={data.particulars}
                    onChange={e => setData('particulars', e.target.value)}
                    className="h-10 bg-white border-slate-200"
                    placeholder="e.g., Tuition Fee, Miscellaneous Fee"
                  />
                  {errors.particulars && (
                    <p className="text-sm text-red-600">{errors.particulars}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="tuition_per_unit_or_misc" className="text-sm font-medium text-slate-700">
                    Tuition per Unit / Miscellaneous
                  </Label>
                  <Input
                    id="tuition_per_unit_or_misc"
                    type="number"
                    step="0.01"
                    value={data.tuition_per_unit_or_misc}
                    onChange={e => setData('tuition_per_unit_or_misc', e.target.value)}
                    className="h-10 bg-white border-slate-200"
                    placeholder="0.00"
                  />
                  {errors.tuition_per_unit_or_misc && (
                    <p className="text-sm text-red-600">{errors.tuition_per_unit_or_misc}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="transaction_type" className="text-sm font-medium text-slate-700">
                    Transaction Type
                  </Label>
                  <Select value={data.transaction_type} onValueChange={handleTransactionTypeChange}>
                    <SelectTrigger className="h-10 bg-white border-slate-200">
                      <SelectValue placeholder="Select type" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="Assessment">Assessment</SelectItem>
                      <SelectItem value="Payment">Payment</SelectItem>
                      <SelectItem value="Adjustment">Adjustment</SelectItem>
                    </SelectContent>
                  </Select>
                  {errors.transaction_type && (
                    <p className="text-sm text-red-600">{errors.transaction_type}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="amount" className="text-sm font-medium text-slate-700">
                    Amount <span className="text-red-500">*</span>
                  </Label>
                  <Input
                    id="amount"
                    type="number"
                    step="0.01"
                    value={data.amount}
                    onChange={e => setData('amount', e.target.value)}
                    className="h-10 bg-white border-slate-200"
                    placeholder="0.00"
                  />
                  {errors.amount && (
                    <p className="text-sm text-red-600">{errors.amount}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="remaining_balance" className="text-sm font-medium text-slate-700">
                    Remaining Balance
                  </Label>
                  <Input
                    id="remaining_balance"
                    type="number"
                    step="0.01"
                    value={data.remaining_balance}
                    onChange={e => setData('remaining_balance', e.target.value)}
                    className="h-10 bg-white border-slate-200"
                    placeholder="0.00"
                  />
                  {errors.remaining_balance && (
                    <p className="text-sm text-red-600">{errors.remaining_balance}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="status" className="text-sm font-medium text-slate-700">
                    Status
                  </Label>
                  <Select value={data.status} onValueChange={value => setData('status', value ?? '')}>
                    <SelectTrigger className="h-10 bg-white border-slate-200">
                      <SelectValue placeholder="Select status" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="Pending">Pending</SelectItem>
                      <SelectItem value="Paid">Paid</SelectItem>
                      <SelectItem value="Overdue">Overdue</SelectItem>
                      <SelectItem value="Partial Payment">Partial Payment</SelectItem>
                      <SelectItem value="Settled">Settled</SelectItem>
                    </SelectContent>
                  </Select>
                  {errors.status && (
                    <p className="text-sm text-red-600">{errors.status}</p>
                  )}
                </div>

                <div className="space-y-2 md:col-span-2">
                  <Label htmlFor="remarks" className="text-sm font-medium text-slate-700">
                    Remarks
                  </Label>
                  <Textarea
                    id="remarks"
                    value={data.remarks}
                    onChange={e => setData('remarks', e.target.value)}
                    className="min-h-[80px] bg-white border-slate-200"
                    placeholder="Additional notes or comments"
                  />
                  {errors.remarks && (
                    <p className="text-sm text-red-600">{errors.remarks}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="input_by" className="text-sm font-medium text-slate-700">
                    Input By
                  </Label>
                  <Input
                    id="input_by"
                    value={data.input_by}
                    onChange={e => setData('input_by', e.target.value)}
                    className="h-10 bg-white border-slate-200"
                    placeholder="Encoder ID / Initials"
                  />
                  {errors.input_by && (
                    <p className="text-sm text-red-600">{errors.input_by}</p>
                  )}
                </div>
              </CardContent>
            </Card>

            {/* Submit Button */}
            <div className="flex items-center justify-end gap-3">
              <Button
                type="button"
                variant="outline"
                onClick={() => router.get('/law-ledger')}
                className="h-10 border-slate-200 hover:bg-slate-50"
              >
                Cancel
              </Button>
              <Button
                type="submit"
                disabled={processing}
                className="h-10 bg-blue-600 hover:bg-blue-700 text-white shadow-sm"
              >
                {processing ? 'Saving...' : 'Save Transaction'}
              </Button>
            </div>
          </div>
        </form>
      </div>
    </div>
  );
}
