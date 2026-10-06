<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PasswordUpdateRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => $this->currentPasswordRules(),
            'password' => [
                ...$this->passwordRules(),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === $this->input('current_password')) {
                        $fail('The new password must be different from your current password.');
                    }
                },
            ],
        ];
    }
}
