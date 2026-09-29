<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class AssignOpAcademicTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(
            $this->user()?->role,
            [UserRole::Staff->value, UserRole::Admin->value],
            true,
        );
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'academic_term' => ['required', 'integer', 'exists:academic_terms,id'],
        ];
    }
}
