<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLawSchoolLedgerRequest extends FormRequest
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
                ?? $this->input('tuition_per_unit_or_misc')
                ?? '0.00',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['nullable', 'exists:law_student,id'],
            'course_id' => ['nullable', 'exists:law_course,id'],
            'academic_term_id' => ['nullable', 'exists:law_academic_term,id'],
            'school_year' => ['required_without:academic_term_id', 'nullable', 'regex:/^\d{4}-\d{4}$/', 'max:20'],
            'semester' => [
                'required_without:academic_term_id',
                'nullable',
                Rule::in(['First Semester', 'Second Semester', 'Summer']),
            ],
            'entry_type' => ['required', Rule::in(['ar', 'payment', 'adjustment'])],
            'last_name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'middle_initial' => ['nullable', 'string', 'max:10'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'course' => ['nullable', 'string', 'max:255'],
            'semester_or_summer' => ['nullable', 'string', 'max:50'],
            'units' => ['nullable', 'numeric', 'min:0'],
            'transaction_date' => ['required', 'date'],
            'reference_jev_or_number' => ['nullable', 'string', 'max:255'],
            'particulars' => ['nullable', 'string', 'max:255'],
            'tuition_per_unit_or_fee_per_semester' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'ar_or_payment' => ['nullable', 'string', 'max:50'],
            'amount' => [
                'nullable',
                'required_unless:entry_type,ar',
                'numeric',
                'decimal:0,2',
                Rule::when($this->input('entry_type') === 'ar', ['min:0'], ['min:0.01']),
                'max:99999999.99',
            ],
            'status' => ['nullable', 'string', 'max:50'],
            'remarks' => ['nullable', 'string', 'max:255'],
            'input_by' => ['nullable', 'string', 'max:255'],
        ];
    }
}