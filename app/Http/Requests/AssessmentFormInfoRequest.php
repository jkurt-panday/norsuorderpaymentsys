<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AssessmentFormInfoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email'            => ['required', 'string', 'email', 'max:255'],
            'contact_num'      => ['required', 'string', 'max:11', 'regex:/^[0-9+\-\s()]+$/'],
            'first_name'       => ['required', 'string', 'max:100'],
            'middle_name'      => ['nullable', 'string', 'max:100'],
            'last_name'        => ['required', 'string', 'max:100'],
            'student_id'       => ['required', 'string', 'max:9', 'regex:/^[0-9]+$/'],
            'course_id'        => ['required', 'exists:courses,id'],
            'address'          => ['required', 'string'],
            'enrolled_under'   => ['required', 'string', 'max:100'],
            'sy_last_attended' => ['required', 'string', 'max:100'],
            'semester'         => ['required', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            // Email
            'email.required' => 'Please enter your email address.',
            'email.email'    => 'Please enter a valid email address.',
            'email.max'      => 'Email address must not exceed 255 characters.',
    
            // Contact Number
            'contact_num.required' => 'Please enter your contact number.',
            'contact_num.max'      => 'Contact number must not exceed 11 digits.',
            'contact_num.regex'    => 'Contact number should only contain numbers, spaces, and + - ( ) characters.',
    
            // Names
            'first_name.required' => 'Please enter your first name.',
            'first_name.max'      => 'First name must not exceed 100 characters.',
            'middle_name.max'     => 'Middle name must not exceed 100 characters.',
            'last_name.required'  => 'Please enter your last name.',
            'last_name.max'       => 'Last name must not exceed 100 characters.',

            'student_id:required' => 'Student ID is required.',
    
            // Course Selection
            'courses.required' => 'Please select a course.',
            'courses.integer'  => 'Selected course is invalid.',
            'courses.exists'   => 'The selected course does not exist.',
    
            // Address
            'address.required' => 'Please enter your address.',
    
            // Academic Details
            'enrolled_under.required'   => 'Please select where you are enrolled under.',
            'enrolled_under.max'        => 'Selected option is invalid.',
            
            'sy_last_attended.required' => 'Please select the school year last attended.',
            'sy_last_attended.max'      => 'Selected school year is invalid.',
            
            'semester.required'         => 'Please select a semester.',
            'semester.max'              => 'Selected semester is invalid.',
        ];
    }
    
    public function attributes(): array
    {
        return [
            'email'            => 'Email Address',
            'contact_num'      => 'Contact Number',
            'first_name'       => 'First Name',
            'middle_name'      => 'Middle Name',
            'last_name'        => 'Last Name',
            'student_id'       => 'Student ID',
            'courses'          => 'Course',
            'address'          => 'Address',
            'enrolled_under'   => 'Enrolled Under',
            'sy_last_attended' => 'School Year Last Attended',
            'semester'         => 'Semester',
        ];
    }
}
