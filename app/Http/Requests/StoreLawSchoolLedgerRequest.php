<?php

namespace App\Http\Requests;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLawSchoolLedgerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'semester' => $this->input('semester') ?? $this->input('semester_or_summer'),
            'reference_jev_or_number' => $this->input('reference_jev_or_number') ?? $this->input('reference_or_jev_number'),
            'tuition_per_unit_or_fee_per_semester' => $this->input('tuition_per_unit_or_fee_per_semester')
                ?: ($this->input('tuition_per_unit_or_misc') ?: '0.00'),
            'rate' => $this->input('rate') ?: ($this->input('tuition_per_unit_or_fee_per_semester') ?: ($this->input('tuition_per_unit_or_misc') ?: '0.00')),
            'reference_number' => $this->input('reference_number') ?? $this->input('reference_jev_or_number') ?? $this->input('reference_or_jev_number'),
            'input_by' => $this->filled('input_by') ? $this->input('input_by') : ($this->user()?->name ?? $this->user()?->id),
        ]);

        if ($this->has('items') && is_array($this->input('items'))) {
            $items = array_map(function (array $item): array {
                $item['tuition_per_unit_or_fee_per_semester'] = ($item['tuition_per_unit_or_fee_per_semester'] ?? null)
                    ?: (($item['tuition_per_unit_or_misc'] ?? null) ?: '0.00');
                $item['rate'] = ($item['rate'] ?? null) ?: (($item['tuition_per_unit_or_fee_per_semester'] ?? null) ?: '0.00');
                $item['reference_jev_or_number'] = $item['reference_jev_or_number'] ?? $item['reference_or_jev_number'] ?? null;
                $item['reference_number'] = $item['reference_number'] ?? $item['reference_jev_or_number'] ?? null;
                $item['status'] = $item['status'] ?? $this->input('status', 'Pending');

                return $item;
            }, $this->input('items'));

            $this->merge(['items' => $items]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['nullable', 'required_without:new_student', 'exists:students,id'],
            'new_student' => ['nullable', 'array'],
            'new_student.student_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique(Student::class, 'student_number'),
            ],
            'new_student.email' => ['nullable', 'email:rfc', 'max:255'],
            'new_student.last_name' => ['required_with:new_student', 'string', 'max:255'],
            'new_student.first_name' => ['required_with:new_student', 'string', 'max:255'],
            'new_student.middle_name' => ['nullable', 'string', 'max:255'],
            'course_id' => ['required', Rule::exists('courses', 'id')->where('course_college', 'School of Law')],
            'academic_term_id' => ['nullable', 'exists:academic_terms,id'],
            'school_year' => ['required_without:academic_term_id', 'nullable', 'regex:/^\d{4}-\d{4}$/', 'max:20'],
            'semester' => [
                'required_without:academic_term_id',
                'nullable',
                Rule::in(['First Semester', 'Second Semester', 'Summer']),
            ],
            'entry_type' => [
                Rule::requiredIf(fn () => ! is_array($this->input('items'))),
                'nullable',
                Rule::in(['ar', 'payment', 'adjustment']),
            ],
            'last_name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'middle_initial' => ['nullable', 'string', 'max:10'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'course' => ['nullable', 'string', 'max:255'],
            'semester_or_summer' => ['nullable', 'string', 'max:50'],
            'units' => ['nullable', 'numeric', 'min:0'],
            'transaction_date' => ['required', 'date'],
            'reference_jev_or_number' => ['nullable', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'particulars' => ['nullable', 'string', 'max:255'],
            'tuition_per_unit_or_fee_per_semester' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'rate' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'ar_or_payment' => ['nullable', 'string', 'max:50'],
            'amount' => [
                'nullable',
                Rule::requiredIf(fn () => ! is_array($this->input('items')) && $this->input('entry_type') !== 'ar'),
                'numeric',
                'decimal:0,2',
                Rule::when($this->input('entry_type') === 'ar', ['min:0'], ['min:0.01']),
                'max:99999999.99',
            ],
            'status' => ['nullable', 'string', 'max:50'],
            'remarks' => ['nullable', 'string', 'max:255'],
            'input_by' => ['nullable', 'string', 'max:255'],
            'latin_honor' => ['nullable', Rule::in(['SUMMA', 'MAGNA', 'CUM_LAUDE'])],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'items' => ['nullable', 'array', 'min:1', 'max:50'],
            'items.*.course_id' => ['required_with:items', Rule::exists('courses', 'id')->where('course_college', 'School of Law')],
            'items.*.entry_type' => ['required_with:items', Rule::in(['ar', 'payment', 'adjustment'])],
            'items.*.units' => ['nullable', 'numeric', 'min:0'],
            'items.*.transaction_date' => ['nullable', 'date'],
            'items.*.reference_jev_or_number' => ['nullable', 'string', 'max:255'],
            'items.*.reference_number' => ['nullable', 'string', 'max:100'],
            'items.*.particulars' => ['nullable', 'string', 'max:255'],
            'items.*.tuition_per_unit_or_fee_per_semester' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'items.*.rate' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'items.*.amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'items.*.remarks' => ['nullable', 'string', 'max:255'],
            'items.*.latin_honor' => ['nullable', Rule::in(['SUMMA', 'MAGNA', 'CUM_LAUDE'])],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'new_student.student_number.unique' => 'This Student ID is already registered. Select the existing student instead.',
        ];
    }
}
