<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicTerm extends Model
{
    /** @use HasFactory<Factory<AcademicTerm>> */
    use HasFactory;

    protected $fillable = [
        'school_year',
        'semester',
    ];

    /** @return HasMany<GraduateLedger, $this> */
    public function graduateLedgers(): HasMany
    {
        return $this->hasMany(GraduateLedger::class);
    }

    /**
     * Normalize any raw semester string to one of 3 canonical values.
     * Returns null when the value contains no recognisable semester keyword
     * (prevents garbage values from being treated as "First Semester").
     */
    public static function normalizeSemester(string $raw): ?string
    {
        $lower = strtolower(trim($raw));

        if ($lower === '') {
            return null;
        }

        // Summer / Midyear / Intersession / Term 3
        if (
            str_contains($lower, 'summer') ||
            str_contains($lower, 'intersession') ||
            str_contains($lower, 'midyear') ||
            str_contains($lower, 'mid-year') ||
            $lower === '3' ||
            $lower === '3rd' ||
            $lower === 'sum' ||
            $lower === 'sum.'
        ) {
            return 'Summer';
        }

        // Second Semester
        if (
            str_contains($lower, '2nd') ||
            str_contains($lower, 'second') ||
            $lower === '2' ||
            $lower === 'sem 2' ||
            $lower === 'term 2'
        ) {
            return 'Second Semester';
        }

        // First Semester
        if (
            str_contains($lower, '1st') ||
            str_contains($lower, 'first') ||
            str_contains($lower, 'sem') ||
            $lower === '1' ||
            $lower === 'sem 1' ||
            $lower === 'term 1'
        ) {
            return 'First Semester';
        }

        return null;
    }

    /**
     * Parse a raw school-year string (e.g. "2025-2026", "2025–2026", "2025-26", "25-26", "SY 2025-2026")
     * into a canonical "YYYY-YYYY" string where the second year = first + 1.
     *
     * Auto-repairs malformed pairs in either half:
     *   - "209-2020"  → valid start 2020, so restore → "2020-2021"
     *   - "2019-202"  → valid end 2019, so restore → "2019-2020"
     * Returns null only when neither half yields a usable 4-digit year.
     */
    public static function parseSchoolYear(string $raw): ?string
    {
        // Strip common prefixes ("SY", "AY", "S.Y.", "A.Y.") and clean up dashes/slashes/spaces
        $cleaned = preg_replace('/^(SY|AY|S\.Y\.|A\.Y\.)\s*/i', '', trim($raw));
        $normalized = trim(str_replace(['–', '—', '/', '\\'], '-', (string) $cleaned));
        $normalized = (string) preg_replace('/\s*-\s*/', '-', $normalized);

        // Standard: "2025-2026"
        if (preg_match('/^(\d{4})-(\d{4})$/', $normalized, $m)) {
            $first = (int) $m[1];
            $second = (int) $m[2];
            if ($second === $first + 1) {
                return "{$m[1]}-{$m[2]}";
            }
            // E.g. "2015-2016, 2015-2014" → still restore from the valid start if possible
            if ($second - 1 >= 1000 && $second - 1 <= 9999 && self::isValidSingleYear($second - 1)) {
                return ($second - 1)."-{$second}";
            }
            if (self::isValidSingleYear($first + 1) && $first + 1 <= 9999) {
                return "{$first}-".($first + 1);
            }

            return null;
        }

        // Mixed 4-digit to 2-digit: "2025-26"
        if (preg_match('/^(\d{4})-(\d{2})$/', $normalized, $m)) {
            $first = (int) $m[1];
            $second = ((int) substr((string) $first, 0, 2) * 100) + (int) $m[2];
            if ($second === $first + 1) {
                return "{$first}-{$second}";
            }

            return null;
        }

        // Broken/typo-pair: at least one side is a usable 4-digit year.
        // Match "209-2020", "2019-202", "209-202", "2020-209", "2097", etc.
        $allDigits = preg_replace('/[^0-9]/', '', (string) $normalized);
        // Tokenise on dash/slash/space and gather whichever tokens resemble years
        if (preg_match('/^(\d+)-(\d+)$/', $normalized, $tokens)) {
            $left = $tokens[1];
            $right = $tokens[2];

            // left is a valid 4-digit year → derive the following year from it
            if (strlen($left) === 4 && self::isValidSingleYear((int) $left)) {
                $first = (int) $left;
                if ($first + 1 <= 9999) {
                    return "{$first}-".($first + 1);
                }
            }

            // right is a valid 4-digit year → derive the preceding year from it
            if (strlen($right) === 4 && self::isValidSingleYear((int) $right)) {
                $second = (int) $right;
                if ($second - 1 >= 1000) {
                    return ($second - 1)."-{$second}";
                }
            }
        }

        // Single 4-digit year: "2025" → "2025-2026"
        if (preg_match('/^\d{4}$/', $allDigits)) {
            $year = (int) $allDigits;
            if (self::isValidSingleYear($year)) {
                return "{$year}-".($year + 1);
            }
        }

        // Plain garbage → cannot interpret
        return null;
    }

    /**
     * A usable school year falls in a sane registrar range.
     */
    private static function isValidSingleYear(int $year): bool
    {
        return $year >= 1990 && $year <= 2100;
    }

    /**
     * Resolve sort order from a canonical semester string.
     */
    public static function sortOrder(string $semester): int
    {
        return match ($semester) {
            'Second Semester' => 2,
            'Summer' => 3,
            default => 1,
        };
    }
}
