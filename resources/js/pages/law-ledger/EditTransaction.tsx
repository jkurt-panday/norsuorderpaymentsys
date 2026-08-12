import { Head, useForm, router } from '@inertiajs/react';
import { ArrowLeft, Trash2 } from 'lucide-react';
import React, { useMemo } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  CardDescription,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';

interface LawLedgerRecord {
  id: number;
  last_name: string;
  first_name: string;
  middle_initial: string | null;
  course: string | null;
  school_year: string | null;
  semester_or_summer: string | null;
  units: number | string | null;
  transaction_date: string | null;
  reference_jev_or_number: string | null;
  particulars: string | null;
  tuition_per_unit_or_fee_per_semester: number | string | null;
  ar_or_payment: string | null;
  amount: number | string | null;
  status: string | null;
  remarks: string | null;
  input_by: string | null;
}

interface EditTransactionProps {
  record: LawLedgerRecord;
  filterOptions?: {
    courses: string[];
    schoolYears: string[];
    semesters: string[];
    statuses: string[];
  };
}

function FieldError({ message }: { message?: string }) {
  if (!message) return null;
  return <p className="mt-1 text-xs text-red-500">{message}</p>;
}

export default function EditTransaction({
  record,
  filterOptions,
}: EditTransactionProps) {
  const { data, setData, put, processing, errors } = useForm({
    last_name: record.last_name ?? '',
    first_name: record.first_name ?? '',
    middle_initial: record.middle_initial ?? '',
    course: record.course ?? '',
    school_year: record.school_year ?? '',
    semester_or_summer: record.semester_or_summer ?? '',
    units: String(record.units ?? ''),
    transaction_date: record.transaction_date
      ? String(record.transaction_date).split('T')[0]
      : '',
    reference_jev_or_number: record.reference_jev_or_number ?? '',
    particulars: record.particulars ?? '',
    tuition_per_unit_or_fee_per_semester: String(
      record.tuition_per_unit_or_fee_per_semester ?? '',
    ),
    ar_or_payment: record.ar_or_payment ?? 'AR',
    amount: String(record.amount ?? ''),
    status: record.status ?? 'Pending',
    remarks: record.remarks ?? '',
    input_by: record.input_by ?? '',
  });

  const semesterOptions = useMemo(() => {
    const defaults = ['1st Sem', '2nd Sem', 'Summer'];
    const fromDb = filterOptions?.semesters ?? [];
    return [...new Set([...defaults, ...fromDb])];
  }, [filterOptions?.semesters]);

  const courseOptions = useMemo(() => {
    const defaults = ['JD', 'LLM', 'JSD'];
    const fromDb = filterOptions?.courses ?? [];
    return [...new Set([...defaults, ...fromDb])];
  }, [filterOptions?.courses]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    router.put(`/law-ledger/${record.id}`, data);
  };

  const handleDelete = () => {
    if (
      !window.confirm(
        `Delete this transaction for "${record.last_name}, ${record.first_name}"? This cannot be undone.`,
      )
    )
      return;
    router.delete(`/law-ledger/${record.id}`);
  };

  const fullName = [
    record.last_name,
    record.first_name,
    record.middle_initial ?? '',
  ]
    .filter(Boolean)
    .join(', ');

  return (
    <div className="min-h-full bg-[#FAFAF5] p-4 md:p-8">
      <Head title="Edit Transaction - Law School Ledger" />
      <div className="mx-auto max-w-5xl space-y-6">
        <div className="flex items-center justify-between border-b border-[#CFE3FF] pb-4">
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              onClick={() => router.get('/law-ledger')}
              className="border-[#CFE3FF] text-[#0B3D91]"
            >
              <ArrowLeft className="mr-1 h-4 w-4" /> Back
            </Button>
            <div>
              <h1 className="text-2xl font-bold text-[#0B3D91]">
                Edit Transaction
              </h1>
              <p className="mt-1 text-sm text-[#5C7A9E]">
                Update the ledger transaction details below.
              </p>
            </div>
          </div>
          <div className="flex items-center gap-3">
            {data.status && (
              <Badge
                variant="outline"
                className="border-[#B9D8FF] bg-[#EAF2FF] text-[#0B62E0]"
              >
                {data.status}
              </Badge>
            )}
            <Button
              variant="outline"
              size="sm"
              onClick={handleDelete}
              className="border-red-200 text-red-600 hover:bg-red-50"
            >
              <Trash2 className="mr-1 h-4 w-4" /> Delete
            </Button>
          </div>
        </div>

        {fullName && (
          <Card className="border-[#CFE3FF] bg-white">
            <CardContent className="pt-6">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div>
                  <p className="text-xs font-medium uppercase tracking-wide text-[#7FA6D6]">
                    Student
                  </p>
                  <p className="mt-1 text-lg font-semibold text-[#0B3D91]">
                    {fullName}
                  </p>
                </div>
                {data.course && (
                  <Badge
                    variant="outline"
                    className="w-fit border-[#B9D8FF] bg-[#EAF2FF] text-[#0B62E0]"
                  >
                    {data.course}
                  </Badge>
                )}
              </div>
            </CardContent>
          </Card>
        )}

        <Card className="border-[#CFE3FF] bg-white">
          <CardHeader>
            <CardTitle className="text-base text-[#0B3D91]">
              Student Information
            </CardTitle>
            <CardDescription className="text-[#7FA6D6]">
              Basic student details and academic information
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form
              onSubmit={handleSubmit}
              className="grid grid-cols-1 gap-4 md:grid-cols-2"
            >
              <div>
                <label className="text-sm text-[#334E68]">
                  Last Name <span className="text-red-500">*</span>
                </label>
                <Input
                  value={data.last_name}
                  placeholder="e.g., CRUZ"
                  required
                  onChange={(e) => setData('last_name', e.target.value)}
                  className={
                    errors.last_name ? 'border-red-400' : ''
                  }
                />
                <FieldError message={errors.last_name} />
              </div>

              <div>
                <label className="text-sm text-[#334E68]">
                  First Name <span className="text-red-500">*</span>
                </label>
                <Input
                  value={data.first_name}
                  placeholder="e.g., JUAN"
                  required
                  onChange={(e) => setData('first_name', e.target.value)}
                  className={
                    errors.first_name ? 'border-red-400' : ''
                  }
                />
                <FieldError message={errors.first_name} />
              </div>

              <div>
                <label className="text-sm text-[#334E68]">
                  Middle Initial
                </label>
                <Input
                  value={data.middle_initial}
                  placeholder="e.g., D"
                  onChange={(e) =>
                    setData('middle_initial', e.target.value)
                  }
                />
              </div>

              <div>
                <label className="text-sm text-[#334E68]">Course</label>
                <select
                  value={data.course}
                  onChange={(e) => setData('course', e.target.value)}
                  className="w-full rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm text-[#334E68] focus:ring-2 focus:ring-[#0F6FFF] focus:outline-none"
                >
                  <option value="">-- Select Course --</option>
                  {courseOptions.map((course) => (
                    <option key={course} value={course}>
                      {course}
                    </option>
                  ))}
                </select>
                <FieldError message={errors.course} />
              </div>

              <div>
                <label className="text-sm text-[#334E68]">
                  School Year
                </label>
                <select
                  value={data.school_year}
                  onChange={(e) =>
                    setData('school_year', e.target.value)
                  }
                  className="w-full rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm text-[#334E68] focus:ring-2 focus:ring-[#0F6FFF] focus:outline-none"
                >
                  <option value="">-- Select School Year --</option>
                  {(filterOptions?.schoolYears ?? []).map((year) => (
                    <option key={year} value={year}>
                      {year}
                    </option>
                  ))}
                </select>
                <FieldError message={errors.school_year} />
              </div>

              <div>
                <label className="text-sm text-[#334E68]">
                  Semester/Summer
                </label>
                <select
                  value={data.semester_or_summer}
                  onChange={(e) =>
                    setData('semester_or_summer', e.target.value)
                  }
                  className="w-full rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm text-[#334E68] focus:ring-2 focus:ring-[#0F6FFF] focus:outline-none"
                >
                  <option value="">-- Select Semester --</option>
                  {semesterOptions.map((sem) => (
                    <option key={sem} value={sem}>
                      {sem}
                    </option>
                  ))}
                </select>
                <FieldError message={errors.semester_or_summer} />
              </div>

              <div>
                <label className="text-sm text-[#334E68]">Units</label>
                <Input
                  type="number"
                  step="0.01"
                  min="0"
                  value={data.units}
                  onChange={(e) => setData('units', e.target.value)}
                  className={errors.units ? 'border-red-400' : ''}
                />
                <FieldError message={errors.units} />
              </div>

              <div>
                <label className="text-sm text-[#334E68]">
                  Transaction Date
                </label>
                <Input
                  type="date"
                  value={data.transaction_date}
                  onChange={(e) =>
                    setData('transaction_date', e.target.value)
                  }
                  className={
                    errors.transaction_date ? 'border-red-400' : ''
                  }
                />
                <FieldError message={errors.transaction_date} />
              </div>

              <div>
                <label className="text-sm text-[#334E68]">
                  Reference JEV/O.R. Number
                </label>
                <Input
                  value={data.reference_jev_or_number}
                  placeholder="e.g., JEV-2024-001"
                  onChange={(e) =>
                    setData('reference_jev_or_number', e.target.value)
                  }
                />
              </div>

              <div>
                <label className="text-sm text-[#334E68]">
                  Tuition per Unit / Reg. & Misc. Fee per Semester
                </label>
                <Input
                  type="number"
                  step="0.01"
                  min="0"
                  value={data.tuition_per_unit_or_fee_per_semester}
                  onChange={(e) =>
                    setData(
                      'tuition_per_unit_or_fee_per_semester',
                      e.target.value,
                    )
                  }
                  className={
                    errors.tuition_per_unit_or_fee_per_semester
                      ? 'border-red-400'
                      : ''
                  }
                />
                <FieldError
                  message={errors.tuition_per_unit_or_fee_per_semester}
                />
              </div>

              <div>
                <label className="text-sm text-[#334E68]">
                  AR/Payment
                </label>
                <Input
                  value={data.ar_or_payment}
                  placeholder="e.g., AR, Payment, Adjustment"
                  onChange={(e) =>
                    setData('ar_or_payment', e.target.value)
                  }
                  className={
                    errors.ar_or_payment ? 'border-red-400' : ''
                  }
                />
                <FieldError message={errors.ar_or_payment} />
              </div>

              <div>
                <label className="text-sm text-[#334E68]">Amount</label>
                <Input
                  type="number"
                  step="0.01"
                  value={data.amount}
                  onChange={(e) => setData('amount', e.target.value)}
                  className={errors.amount ? 'border-red-400' : ''}
                />
                <FieldError message={errors.amount} />
              </div>

              <div>
                <label className="text-sm text-[#334E68]">Status</label>
                <Input
                  value={data.status}
                  placeholder="e.g., Pending, Paid, Overdue"
                  onChange={(e) => setData('status', e.target.value)}
                  className={errors.status ? 'border-red-400' : ''}
                />
                <FieldError message={errors.status} />
              </div>

              <div>
                <label className="text-sm text-[#334E68]">Input By</label>
                <Input
                  value={data.input_by}
                  placeholder="Encoder ID / Initials"
                  onChange={(e) => setData('input_by', e.target.value)}
                />
              </div>

              <div className="md:col-span-2">
                <label className="text-sm text-[#334E68]">Remarks</label>
                <textarea
                  value={data.remarks}
                  onChange={(e) => setData('remarks', e.target.value)}
                  placeholder="Additional notes or comments"
                  className="min-h-[80px] w-full rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm text-[#334E68] focus:ring-2 focus:ring-[#0F6FFF] focus:outline-none"
                />
              </div>

              <div className="flex items-center justify-end gap-3 md:col-span-2">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => router.get('/law-ledger')}
                  className="border-[#CFE3FF] text-[#0B3D91] hover:bg-[#F3F8FF]"
                >
                  Cancel
                </Button>
                <Button
                  type="submit"
                  disabled={processing}
                  className="bg-[#0F6FFF] text-white hover:bg-[#0B5DDB] disabled:opacity-60"
                >
                  {processing ? 'Saving...' : 'Update Transaction'}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
