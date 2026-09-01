<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGraduateLedgerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'tuition_per_unit_or_misc' => $this->input('tuition_per_unit_or_misc') ?: '0.00',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'exists:graduate_student,id'],
            'course_id' => ['nullable', 'exists:graduate_course,id'],
            'academic_term_id' => ['nullable', 'exists:graduate_academic_term,id'],
            'school_year' => ['required_without:academic_term_id', 'nullable', 'regex:/^\d{4}-\d{4}$/', 'max:20'],
            'semester' => [
                'required_without:academic_term_id',
                'nullable',
                Rule::in(['First Semester', 'Second Semester', 'Summer']),
            ],
            'entry_type' => ['required', Rule::in(['ar', 'payment', 'adjustment'])],
            'units' => ['nullable', 'integer', 'min:0'],
            'transaction_date' => ['required', 'date'],
            'reference_or_jev_number' => ['nullable', 'string', 'max:255'],
            'particulars' => ['nullable', 'string', 'max:255'],
            'tuition_per_unit_or_misc' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'amount' => [
                'nullable',
                'required_unless:entry_type,ar',
                'numeric',
                'decimal:0,2',
                Rule::when($this->input('entry_type') === 'ar', ['min:0'], ['min:0.01']),
                'max:99999999.99',
            ],
            'remarks' => ['nullable', 'string', 'max:255'],
            'input_by' => ['nullable', 'string', 'max:255'],
        ];
    }
}
