<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class CreateAndLinkOpStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Staff->value;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'student_number' => ['required', 'string', 'max:50', 'regex:/^(?=.*\d)[0-9\-\s]+$/'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'contact_num' => ['nullable', 'string', 'max:11', 'regex:/^[0-9+\-\s()]+$/'],
        ];
    }
}
