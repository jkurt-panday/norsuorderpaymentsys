<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LawStudent extends Model
{
    /** @use HasFactory<Factory<LawStudent>> */
    use HasFactory;

    protected $table = 'law_student';

    protected $fillable = [
        'student_number',
        'last_name',
        'first_name',
        'middle_name',
        'raw_name_from_csv',
    ];

    /** @return HasMany<LawSchoolLedger, $this> */
    public function lawSchoolLedgers(): HasMany
    {
        return $this->hasMany(LawSchoolLedger::class);
    }

    /**
     * Returns the full formatted name: "LAST, FIRST M."
     */
    public function getFullNameAttribute(): string
    {
        $middle = $this->middle_name
            ? ' '.strtoupper(substr($this->middle_name, 0, 1)).'.'
            : '';

        return trim("{$this->last_name}, {$this->first_name}{$middle}");
    }

    /**
     * Calculates the outstanding balance for this student.
     * AR entries are charges; payment/adjustment entries are deductions.
     */
    public function balance(): float
    {
        $balance = $this->lawSchoolLedgers()
            ->selectRaw("SUM(CASE WHEN entry_type = 'ar' THEN amount WHEN entry_type IN ('payment','adjustment') THEN -amount ELSE 0 END) as bal")
            ->value('bal');

        return $balance === null ? 0.0 : (float) $balance;
    }

    /**
     * Attempt to parse a raw "LAST, FIRST M." string into name components.
     * Returns an array with keys: last_name, first_name, middle_name.
     */
    /** @return array{last_name: string, first_name: string, middle_name: string|null} */
    public static function parseRawName(string $rawName): array
    {
        $rawName = trim($rawName);

        // Pattern: "LAST, FIRST MIDDLE" or "LAST, FIRST"
        if (str_contains($rawName, ',')) {
            [$last, $rest] = explode(',', $rawName, 2);
            $rest = trim($rest);

            $parts = preg_split('/\s+/', $rest);

            if ($parts === false || $parts === []) {
                return [
                    'last_name' => trim($last),
                    'first_name' => '',
                    'middle_name' => null,
                ];
            }

            $count = count($parts);

            if ($count === 1) {
                $first = $parts[0];
                $middle = null;
            } elseif ($count === 2) {
                // If there are exactly two words, check if the last word is a middle initial
                $lastWord = trim($parts[1]);
                $cleanLastWord = rtrim($lastWord, '.');

                // Single character is a middle initial, otherwise it is a double first name
                if (strlen($cleanLastWord) === 1) {
                    $first = $parts[0];
                    $middle = $cleanLastWord;
                } else {
                    $first = $parts[0].' '.$parts[1];
                    $middle = null;
                }
            } else {
                // 3 or more words: last word is the middle name, all previous words are first name
                $middle = rtrim(trim($parts[$count - 1]), '.');
                $first = implode(' ', array_slice($parts, 0, $count - 1));
            }

            return [
                'last_name' => trim($last),
                'first_name' => trim($first),
                'middle_name' => $middle,
            ];
        }

        // Fallback: treat the whole thing as last name
        return [
            'last_name' => $rawName,
            'first_name' => '',
            'middle_name' => null,
        ];
    }
}
