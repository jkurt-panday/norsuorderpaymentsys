<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Student extends Model
{
    /** @use HasFactory<Factory<Student>> */
    use HasFactory;

    protected $fillable = [
        'student_number',
        'email',
        'last_name',
        'first_name',
        'middle_name',
        'contact_num',
    ];

    /** @return HasMany<GraduateLedger, $this> */
    public function graduateLedgers(): HasMany
    {
        return $this->hasMany(GraduateLedger::class);
    }

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
        $balance = $this->graduateLedgers()
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
        $rawName = Str::squish($rawName);

        if (str_contains($rawName, ',')) {
            [$last, $rest] = explode(',', $rawName, 2);
            $parts = preg_split('/\s+/', Str::squish($rest));

            if ($parts === false || $parts === []) {
                return [
                    'last_name' => trim($last),
                    'first_name' => '',
                    'middle_name' => null,
                ];
            }

            $parts = array_values(array_filter(
                $parts,
                fn (string $part): bool => ! preg_match('/^\([^)]*\)$/u', $part),
            ));

            if ($parts === []) {
                return [
                    'last_name' => trim($last),
                    'first_name' => '',
                    'middle_name' => null,
                ];
            }

            $suffix = null;
            $lastPart = Str::upper(rtrim(end($parts), '.,'));
            if (in_array($lastPart, ['JR', 'SR', 'II', 'III', 'IV', 'V'], true)) {
                $suffix = array_pop($parts);
            }

            $count = count($parts);
            $middle = null;

            if ($count === 1) {
                $first = $parts[0];
            } elseif ($count === 2) {
                if (self::isMiddleInitial($parts[1])) {
                    $first = $parts[0];
                    $middle = self::normalizeMiddleInitial($parts[1]);
                } else {
                    $first = implode(' ', $parts);
                }
            } else {
                $middle = self::normalizeMiddleInitial($parts[$count - 1]);
                $first = implode(' ', array_slice($parts, 0, $count - 1));
            }

            if ($suffix !== null) {
                $first = trim($first.' '.$suffix);
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

    private static function isMiddleInitial(string $value): bool
    {
        return preg_match('/^\p{L}[.,]?$/u', trim($value)) === 1;
    }

    public static function normalizeMiddleInitial(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (preg_match('/\p{L}/u', $value, $matches) !== 1) {
            return null;
        }

        return Str::upper($matches[0]);
    }
}
