import { Head, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import React from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

const courseOptions = [
  'PhD Educational Management',
  'PhD Mathematics Education',
  'EdD Educational Management',
  'EdD Instruction',
  'EdD Science Education',
  'EdD Filipino',
  'EdD Technology Management',
  'DM HRM',
  'DM Public Administration',
  'MBA',
  'MPH',
  'MA Education',
  'MA English',
  'MA Filipino',
  'MA History',
  'MA Psychology',
  'MA Mathematics',
  'MAECE',
  'MS Agriculture',
  'MSIT',
  'MTE',
  'MPM HRM',
  'MPM LGA',
];

const semesterOptions = ['1st Sem.', '2nd Sem.', 'Summer'];
const particularsOptions = ['Registration', 'Tuition', 'Miscellaneous'];
const typeOptions = ['AR', 'Payment', 'Adjustment'];

export default function AddTransaction() {
  const [form, setForm] = React.useState({
    student_name: '',
    course: '',
    school_year: '',
    semester_short: '1st Sem.',
    semester: 'First Semester',
    units: '',
    transaction_date: '',
    reference_or_jev_number: '',
    particulars: 'Tuition',
    tuition_per_unit_or_misc: '',
    ar_payment: 'AR',
    amount: '',
    remarks: '',
    input_by: '',
  });

  const parsedUnits = Number(form.units || 0);
  const parsedRate = Number(form.tuition_per_unit_or_misc || 0);
  const computedAmount = form.ar_payment === 'AR' ? parsedUnits * parsedRate : Number(form.amount || 0);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    router.post('/graduate-ledger', {
      ...form,
      amount: computedAmount.toFixed(2),
      transaction_date: form.transaction_date || new Date().toISOString().slice(0, 10),
    });
  };

  return (
    <div className="min-h-screen bg-[#FAFAF5] p-4 md:p-8">
      <Head title="Add Transaction" />
      <div className="max-w-5xl mx-auto space-y-6">
        <div className="flex items-center gap-2 border-b border-[#CFE3FF] pb-4">
          <Button variant="outline" size="sm" onClick={() => router.get('/graduate-ledger')} className="border-[#CFE3FF] text-[#0B3D91]">
            <ArrowLeft className="h-4 w-4 mr-1" /> Back
          </Button>
          <div>
            <h1 className="text-2xl font-bold text-[#0B3D91]">Add New Transaction</h1>
            <p className="text-sm text-[#5C7A9E] mt-1">Create a manual ledger transaction entry for a student.</p>
          </div>
        </div>

        <Card className="border-[#CFE3FF] bg-white">
          <CardHeader>
            <CardTitle className="text-base text-[#0B3D91]">Transaction Details</CardTitle>
            <CardDescription className="text-[#7FA6D6]">Fill in the student information and financial details below.</CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSubmit} className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="md:col-span-2">
                <label className="text-sm text-[#334E68]">Student Name</label>
                <Input value={form.student_name} onChange={(e) => setForm({ ...form, student_name: e.target.value })} required />
              </div>
              <div>
                <label className="text-sm text-[#334E68]">Course</label>
                <select
                  value={form.course}
                  onChange={(e) => setForm({ ...form, course: e.target.value })}
                  className="w-full rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm text-[#334E68] focus:outline-none focus:ring-2 focus:ring-[#0F6FFF]"
                >
                  <option value="">-- Select Course --</option>
                  {courseOptions.map((course) => (
                    <option key={course} value={course}>
                      {course}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label className="text-sm text-[#334E68]">School Year</label>
                <Input
                  value={form.school_year}
                  placeholder="e.g. 2025-2026"
                  onChange={(e) => setForm({ ...form, school_year: e.target.value })}
                />
              </div>
              <div>
                <label className="text-sm text-[#334E68]">Semester Short</label>
                <p className="text-xs text-[#7FA6D6] mb-1">Short label for the term, such as 1st Sem. or 2nd Sem.</p>
                <select
                  value={form.semester_short}
                  onChange={(e) => {
                    const value = e.target.value;
                    setForm({
                      ...form,
                      semester_short: value,
                      semester: value === 'Summer' ? 'Summer' : value === '2nd Sem.' ? 'Second Semester' : 'First Semester',
                    });
                  }}
                  className="w-full rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm text-[#334E68] focus:outline-none focus:ring-2 focus:ring-[#0F6FFF]"
                >
                  {semesterOptions.map((option) => (
                    <option key={option} value={option}>
                      {option}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label className="text-sm text-[#334E68]">Semester</label>
                <Input value={form.semester} readOnly className="bg-[#F8FAFC]" />
              </div>
              <div>
                <label className="text-sm text-[#334E68]">Units</label>
                <Input type="number" value={form.units} onChange={(e) => setForm({ ...form, units: e.target.value })} />
              </div>
              <div>
                <label className="text-sm text-[#334E68]">Transaction Date</label>
                <Input type="date" value={form.transaction_date} onChange={(e) => setForm({ ...form, transaction_date: e.target.value })} />
              </div>
              <div>
                <label className="text-sm text-[#334E68]">Reference / JEV / OR #</label>
                <Input value={form.reference_or_jev_number} onChange={(e) => setForm({ ...form, reference_or_jev_number: e.target.value })} />
              </div>
              <div className="md:col-span-2">
                <label className="text-sm text-[#334E68]">Particulars</label>
                <select
                  value={form.particulars}
                  onChange={(e) => setForm({ ...form, particulars: e.target.value })}
                  className="w-full rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm text-[#334E68] focus:outline-none focus:ring-2 focus:ring-[#0F6FFF]"
                >
                  {particularsOptions.map((option) => (
                    <option key={option} value={option}>
                      {option}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label className="text-sm text-[#334E68]">Tuition / Unit</label>
                <Input type="number" step="0.01" value={form.tuition_per_unit_or_misc} onChange={(e) => setForm({ ...form, tuition_per_unit_or_misc: e.target.value })} />
              </div>
              <div>
                <label className="text-sm text-[#334E68]">Type</label>
                <select
                  value={form.ar_payment}
                  onChange={(e) => setForm({ ...form, ar_payment: e.target.value })}
                  className="w-full rounded-md border border-[#CFE3FF] bg-white px-3 py-2 text-sm text-[#334E68] focus:outline-none focus:ring-2 focus:ring-[#0F6FFF]"
                >
                  {typeOptions.map((option) => (
                    <option key={option} value={option}>
                      {option}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label className="text-sm text-[#334E68]">Amount</label>
                <Input
                  type="number"
                  step="0.01"
                  value={form.ar_payment === 'AR' ? computedAmount.toFixed(2) : form.amount}
                  readOnly={form.ar_payment === 'AR'}
                  onChange={(e) => setForm({ ...form, amount: e.target.value })}
                  className={form.ar_payment === 'AR' ? 'bg-[#F8FAFC]' : ''}
                />
              </div>
              <div>
                <label className="text-sm text-[#334E68]">Input By</label>
                <Input value={form.input_by} onChange={(e) => setForm({ ...form, input_by: e.target.value })} />
              </div>
              <div className="md:col-span-2">
                <label className="text-sm text-[#334E68]">Remarks</label>
                <Input value={form.remarks} onChange={(e) => setForm({ ...form, remarks: e.target.value })} />
              </div>
              <div className="md:col-span-2 flex justify-end">
                <Button type="submit" className="bg-[#0F6FFF] hover:bg-[#0B5DDB] text-white">Save Transaction</Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
