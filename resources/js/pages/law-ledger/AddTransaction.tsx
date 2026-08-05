import { Head, router, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import React, { useMemo } from 'react';
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

interface AddTransactionProps {
  filterOptions?: {
    courses: string[];
    schoolYears: string[];
    semesters: string[];
    statuses: string[];
  };
}

export default function AddTransaction({ filterOptions }: AddTransactionProps) {
  const { data, setData, post, processing, errors, reset } = useForm({
    last_name: '',
    first_name: '',
    middle_initial: '',
    course: '',
    school_year: '',
    semester_or_summer: '',
    units: '',
    transaction_date: '',
    reference_jev_or_number: '',
    particulars: '',
    tuition_per_unit_or_fee_per_semester: '',
    ar_or_payment: 'AR',
    amount: '',
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

  const semesterOptions = useMemo(() => {
    const defaults = ['First Semester', 'Second Semester', 'Summer'];
    const fromDb = filterOptions?.semesters ?? [];
    return [...new Set([...defaults, ...fromDb])];
  }, [filterOptions?.semesters]);

  const courseOptions = useMemo(() => {
    const defaults = ['JD', 'LLM', 'JSD'];
    const fromDb = filterOptions?.courses ?? [];
    return [...new Set([...defaults, ...fromDb])];
  }, [filterOptions?.courses]);



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
                  <Label htmlFor="last_name" className="text-sm font-medium text-slate-700">
                    Last Name <span className="text-red-500">*</span>
                  </Label>
                  <Input
                    id="last_name"
                    value={data.last_name}
                    onChange={e => setData('last_name', e.target.value)}
                    className="h-10 bg-white border-slate-200"
                    placeholder="e.g., CRUZ"
                  />
                  {errors.last_name && (
                    <p className="text-sm text-red-600">{errors.last_name}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="first_name" className="text-sm font-medium text-slate-700">
                    First Name <span className="text-red-500">*</span>
                  </Label>
                  <Input
                    id="first_name"
                    value={data.first_name}
                    onChange={e => setData('first_name', e.target.value)}
                    className="h-10 bg-white border-slate-200"
                    placeholder="e.g., JUAN"
                  />
                  {errors.first_name && (
                    <p className="text-sm text-red-600">{errors.first_name}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="middle_initial" className="text-sm font-medium text-slate-700">
                    Middle Initial
                  </Label>
                  <Input
                    id="middle_initial"
                    value={data.middle_initial}
                    onChange={e => setData('middle_initial', e.target.value)}
                    className="h-10 bg-white border-slate-200"
                    placeholder="e.g., D"
                  />
                  {errors.middle_initial && (
                    <p className="text-sm text-red-600">{errors.middle_initial}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="course" className="text-sm font-medium text-slate-700">
                    Course
                  </Label>
                  <Select value={data.course} onValueChange={value => setData('course', value ?? '')}>
                    <SelectTrigger className="h-10 bg-white border-slate-200">
                      <SelectValue placeholder="Select course" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="">Select course</SelectItem>
                      {courseOptions.map(course => (
                        <SelectItem key={course} value={course}>{course}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  {errors.course && (
                    <p className="text-sm text-red-600">{errors.course}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="school_year" className="text-sm font-medium text-slate-700">
                    School Year
                  </Label>
                  <Select value={data.school_year} onValueChange={value => setData('school_year', value ?? '')}>
                    <SelectTrigger className="h-10 bg-white border-slate-200">
                      <SelectValue placeholder="Select school year" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="">Select school year</SelectItem>
                      {filterOptions?.schoolYears?.map(year => (
                        <SelectItem key={year} value={year}>{year}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  {errors.school_year && (
                    <p className="text-sm text-red-600">{errors.school_year}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="semester_or_summer" className="text-sm font-medium text-slate-700">
                    Semester/Summer
                  </Label>
                  <Select value={data.semester_or_summer} onValueChange={value => setData('semester_or_summer', value ?? '')}>
                    <SelectTrigger className="h-10 bg-white border-slate-200">
                      <SelectValue placeholder="Select semester" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="">Select semester</SelectItem>
                      {semesterOptions.map(sem => (
                        <SelectItem key={sem} value={sem}>{sem}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  {errors.semester_or_summer && (
                    <p className="text-sm text-red-600">{errors.semester_or_summer}</p>
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
                  <Label htmlFor="reference_jev_or_number" className="text-sm font-medium text-slate-700">
                    Reference JEV/O.R. Number
                  </Label>
                  <Input
                    id="reference_jev_or_number"
                    value={data.reference_jev_or_number}
                    onChange={e => setData('reference_jev_or_number', e.target.value)}
                    className="h-10 bg-white border-slate-200"
                    placeholder="e.g., JEV-2024-001"
                  />
                  {errors.reference_jev_or_number && (
                    <p className="text-sm text-red-600">{errors.reference_jev_or_number}</p>
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
                  <Label htmlFor="tuition_per_unit_or_fee_per_semester" className="text-sm font-medium text-slate-700">
                    Tuition per Unit / Registration & Misc. Fee per Semester
                  </Label>
                  <Input
                    id="tuition_per_unit_or_fee_per_semester"
                    type="number"
                    step="0.01"
                    value={data.tuition_per_unit_or_fee_per_semester}
                    onChange={e => setData('tuition_per_unit_or_fee_per_semester', e.target.value)}
                    className="h-10 bg-white border-slate-200"
                    placeholder="0.00"
                  />
                  {errors.tuition_per_unit_or_fee_per_semester && (
                    <p className="text-sm text-red-600">{errors.tuition_per_unit_or_fee_per_semester}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="ar_or_payment" className="text-sm font-medium text-slate-700">
                    AR/Payment
                  </Label>
                  <Input
                    id="ar_or_payment"
                    value={data.ar_or_payment}
                    onChange={e => setData('ar_or_payment', e.target.value)}
                    className="h-10 bg-white border-slate-200"
                    placeholder="e.g., AR, Payment, Adjustment"
                  />
                  {errors.ar_or_payment && (
                    <p className="text-sm text-red-600">{errors.ar_or_payment}</p>
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
                  <Label htmlFor="status" className="text-sm font-medium text-slate-700">
                    Status
                  </Label>
                  <Input
                    id="status"
                    value={data.status}
                    onChange={e => setData('status', e.target.value)}
                    className="h-10 bg-white border-slate-200"
                    placeholder="e.g., Pending, Paid, Overdue"
                  />
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
