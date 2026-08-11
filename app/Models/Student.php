<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $table = 'graduate_student';

    protected $fillable = [
        'student_number',
        'last_name',
        'first_name',
        'middle_name',
        'raw_name_from_csv',
    ];

    public function graduateLedgers()
    {
        return $this->hasMany(GraduateLedger::class);
    }

    /**
     * Returns the full formatted name: "LAST, FIRST M."
     */
    public function getFullNameAttribute(): string
    {
        $middle = $this->middle_name
            ? ' ' . strtoupper(substr($this->middle_name, 0, 1)) . '.'
            : '';

        return trim("{$this->last_name}, {$this->first_name}{$middle}");
    }

    /**
     * Calculates the outstanding balance for this student.
     * AR entries are charges; payment/adjustment entries are deductions.
     */
    public function balance(): float
    {
        return (float) $this->graduateLedgers()
            ->selectRaw("SUM(CASE WHEN entry_type = 'ar' THEN amount WHEN entry_type IN ('payment','adjustment') THEN -amount ELSE 0 END) as bal")
            ->value('bal') ?? 0.0;
    }

    /**
     * Attempt to parse a raw "LAST, FIRST M." string into name components.
     * Returns an array with keys: last_name, first_name, middle_name.
     */
    public static function parseRawName(string $rawName): array
    {
        $rawName = trim($rawName);

        // Pattern: "LAST, FIRST M." or "LAST, FIRST MIDDLE" or "LAST, FIRST"
        if (str_contains($rawName, ',')) {
            [$last, $rest] = explode(',', $rawName, 2);
            $rest = trim($rest);

            // Try to extract middle initial/name as last word
            $parts = preg_split('/\s+/', $rest);
            $first = $parts[0] ?? $rest;
            $middle = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : null;

            return [
                'last_name'   => trim($last),
                'first_name'  => trim($first),
                'middle_name' => $middle ? trim(rtrim($middle, '.')) : null,
            ];
        }

        // Fallback: treat the whole thing as last name
        return [
            'last_name'   => $rawName,
            'first_name'  => '',
            'middle_name' => null,
        ];
    }
}
