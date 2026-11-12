<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

class UniqueCaseInsensitive implements Rule
{
    /**
     * @param  string  $table
     * @param  string  $column
     */
    public function __construct(
        private readonly string $table,
        private readonly string $column,
    ) {
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     */
    public function passes($attribute, $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $exists = DB::table($this->table)
            ->whereRaw("LOWER({$this->column}) = ?", [strtolower((string) $value)])
            ->exists();

        return ! $exists;
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return "The :attribute has already been taken.";
    }
}