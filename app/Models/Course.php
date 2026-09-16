<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    /** @use HasFactory<Factory<Course>> */
    use HasFactory;

    protected $fillable = [
        'course_code',
        'course_desc',
        'course_college',
    ];

    /** @return HasMany<GraduateLedger, $this> */
    public function graduateLedgers(): HasMany
    {
        return $this->hasMany(GraduateLedger::class);
    }

    public function getCodeAttribute(): string
    {
        return (string) $this->course_code;
    }

    public function getTitleAttribute(): ?string
    {
        return $this->course_desc;
    }

    /**
     * Normalize a raw course string so spelling variants collapse to one key:
     * uppercase, strip periods/parens/brackets, collapse spaces and every kind
     * of separator (hyphen, en/em dash, underscore, slash, plus).
     * Example: "M.S. - Math" / "M.S. – Math" / "M.S. Math" → "MS MATH".
     */
    public static function normalizeCode(string $raw): string
    {
        $upper = strtoupper(trim($raw));
        // Periods are *removed* (M.S. → MS); parens/brackets/colons/commas/slashes
        // become separators ("MBA (Nursing)" → "MBA NURSING").
        $upper = str_replace('.', '', $upper);
        $upper = str_replace(['(', ')', '[', ']', ':', ',', '/', '\\', '+'], ' ', $upper);

        return trim((string) preg_replace('/[\s\-_–—]+/', ' ', $upper));
    }

    /**
     * Explicit map of normalized variants → canonical course codes.
     * Used by the graduate ledger import to resolve misspelled/legacy
     * course values instead of creating junk course rows.
     * Keep in sync with the canonical list in allCodes().
     *
     * @return array<string, string> normalized variant => canonical code
     */
    public static function aliasMap(): array
    {
        return [
            // Mathematics
            'MS MATH' => 'MA Mathematics',
            'MSMATH' => 'MA Mathematics',
            'MS MATHEMATICS' => 'MA Mathematics',
            'MA MATH' => 'MA Mathematics',
            'MAMATH' => 'MA Mathematics',
            'MA MATHEMATICS' => 'MA Mathematics',
            'MATH' => 'MA Mathematics',
            'MATHEMATICS' => 'MA Mathematics',
            'MASTER OF ARTS IN MATHEMATICS' => 'MA Mathematics',
            'MASTER OF SCIENCE IN MATHEMATICS' => 'MA Mathematics',
            'MASTER IN MATHEMATICS' => 'MA Mathematics',

            // Information Technology
            'MS IT' => 'MSIT',
            'MSIT' => 'MSIT',
            'MS INFORMATION TECHNOLOGY' => 'MSIT',
            'MSINFOTECH' => 'MSIT',
            'MASTER OF SCIENCE IN INFORMATION TECHNOLOGY' => 'MSIT',
            'MASTER IN INFORMATION TECHNOLOGY' => 'MSIT',

            // Business Administration
            'MBA' => 'MBA',
            'MASTER IN BUSINESS ADMINISTRATION' => 'MBA',
            'MASTER OF BUSINESS ADMINISTRATION' => 'MBA',
            'MA BUSINESS ADMINISTRATION' => 'MBA',

            // Teaching English
            'MTE' => 'MTE',
            'MASTER IN TEACHING ENGLISH' => 'MTE',
            'MASTER OF TEACHING ENGLISH' => 'MTE',

            // Education
            'MAED' => 'MA Education',
            'MA EDUCATION' => 'MA Education',
            'MASTER OF ARTS IN EDUCATION' => 'MA Education',
            'MASTER IN EDUCATION' => 'MA Education',
            'MA ED' => 'MA Education',

            // English / Filipino / History / Psychology / Sociology / Early Childhood
            'MAE' => 'MA English',
            'MA ENG' => 'MA English',
            'MAENG' => 'MA English',
            'MA ENGLISH' => 'MA English',
            'MASTER OF ARTS IN ENGLISH' => 'MA English',
            'MA FIL' => 'MA Filipino',
            'MAFIL' => 'MA Filipino',
            'MA FILIPINO' => 'MA Filipino',
            'MASTER OF ARTS IN FILIPINO' => 'MA Filipino',
            'MA HIST' => 'MA History',
            'MAHIST' => 'MA History',
            'MA HISTORY' => 'MA History',
            'MASTER OF ARTS IN HISTORY' => 'MA History',
            'MA SOC' => 'MA Sociology',
            'MASOC' => 'MA Sociology',
            'MA SOCIO' => 'MA Sociology',
            'MASOCIO' => 'MA Sociology',
            'MA SOCIOLOGY' => 'MA Sociology',
            'MASTER OF ARTS IN SOCIOLOGY' => 'MA Sociology',
            'MA PSYCHOLOGY' => 'MA Psychology',
            'MA PSYCH' => 'MA Psychology',
            'MAPSYCH' => 'MA Psychology',
            'MASTER OF ARTS IN PSYCHOLOGY' => 'MA Psychology',
            'MAECE' => 'MAECE',
            'MA ECE' => 'MAECE',
            'MASTER OF ARTS IN EARLY CHILDHOOD EDUCATION' => 'MAECE',

            // Agriculture & Public Health
            'MS AGRICULTURE' => 'MS Agriculture',
            'MS AGRI' => 'MS Agriculture',
            'MSAGRI' => 'MS Agriculture',
            'MASTER OF SCIENCE IN AGRICULTURE' => 'MS Agriculture',
            'MPH' => 'MPH',
            'MASTER IN PUBLIC HEALTH' => 'MPH',
            'MASTER OF PUBLIC HEALTH' => 'MPH',

            // Public Management
            'MPM HRM' => 'MPM HRM',
            'MPM HR' => 'MPM HRM',
            'MPM LGA' => 'MPM LGA',
            'MPM' => 'MPM',
            'M P M' => 'MPM',
            'MASTER IN PUBLIC MANAGEMENT' => 'MPM',
            'MASTER OF PUBLIC MANAGEMENT' => 'MPM',

            // Doctoral Programs
            'DM HRM' => 'DM HRM',
            'DOCTOR IN MANAGEMENT HRM' => 'DM HRM',
            'DM PUBLIC ADMINISTRATION' => 'DM Public Administration',
            'DM PA' => 'DM Public Administration',
            'DMPA' => 'DM Public Administration',
            'DOCTOR IN MANAGEMENT PUBLIC ADMINISTRATION' => 'DM Public Administration',
            'PHD EDUCATIONAL MANAGEMENT' => 'PhD. Educational Management',
            'PHD EM' => 'PhD. Educational Management',
            'PH EDUCATIONAL MANAGEMENT' => 'PhD. Educational Management',
            'PH EM' => 'PhD. Educational Management',
            'PHED' => 'PhD. Educational Management',
            'PHD MATHEMATICS EDUCATION' => 'PhD. Mathematics',
            'PHD MATHEMATICS' => 'PhD. Mathematics',
            'PHD MATH' => 'PhD. Mathematics',
            'PHD MATH ED' => 'PhD. Mathematics',
            'PHD MATHED' => 'PhD. Mathematics',
            'PHDMATH' => 'PhD. Mathematics',
            // "PH" is used in the wild as shorthand for PhD (e.g. "PH Math")
            'PH MATH' => 'PhD. Mathematics',
            'PH MATHEMATICS' => 'PhD. Mathematics',
            'PH MATHEMATICS EDUCATION' => 'PhD. Mathematics',
            'PH MATH ED' => 'PhD. Mathematics',
            'PHMATH' => 'PhD. Mathematics',
            // General "ED.D" / "EDD" / "PH.D" / "PHD" variants without a major specified.
            // normalizeCode strips periods, so "ED.D", "E.D.D", "Ed. D." all become "EDD".
            'EDD' => 'EdD. Educational Management',
            'ED' => 'EdD. Educational Management',
            'DOCTOR OF EDUCATION' => 'EdD. Educational Management',
            'PHD' => 'PhD. Educational Management',
            'PH' => 'PhD. Educational Management',
            'DOCTOR OF PHILOSOPHY' => 'PhD. Educational Management',

            'EDD EDUCATIONAL MANAGEMENT' => 'EdD. Educational Management',
            'EDD EM' => 'EdD. Educational Management',
            'EDD INSTRUCTION' => 'EdD. Instruction',
            'EDD SCIENCE EDUCATION' => 'EdD. Science Education',
            'EDD SCED' => 'EdD. Science Education',
            'EDD FIL' => 'EdD. Filipino',
            'EDDFIL' => 'EdD. Filipino',
            'EDD FILIPINO' => 'EdD. Filipino',
            'EDD TECHNOLOGY MANAGEMENT' => 'EdD. Technology Management',
            'EDD TM' => 'EdD. Technology Management',
        ];
    }

    /**
     * Resolve a raw course value to its canonical code.
     * Returns the canonical code, or null when it cannot be resolved
     * (unresolvable values fall back to their normalized form).
     */
    public static function resolveCanonicalCode(string $raw): ?string
    {
        $normalized = self::normalizeCode($raw);

        if ($normalized === '') {
            return null;
        }

        // Direct match against canonical codes.
        foreach (self::allCodes() as $code) {
            if (self::normalizeCode($code) === $normalized) {
                return $code;
            }
        }

        return self::aliasMap()[$normalized] ?? null;
    }

    /**
     * All graduate program course codes used in the system.
     * Keep in sync with frontend courseOptions arrays.
     */
    /** @return list<string> */
    public static function allCodes(): array
    {
        return array_keys(static::descriptions());
    }

    /**
     * Canonical graduate program code → full program description (course_desc).
     * Single source of truth for both codes and descriptions so auto-created
     * courses and the dedup command never store junk descriptions.
     *
     * @return array<string, string> canonical code => description
     */
    public static function descriptions(): array
    {
        return [
            'PhD. Educational Management' => 'Doctor of Philosophy in Educational Management',
            'PhD. Mathematics' => 'Doctor of Philosophy in Mathematics',
            'EdD. Educational Management' => 'Doctor of Education in Educational Management',
            'EdD. Instruction' => 'Doctor of Education in Instruction',
            'EdD. Science Education' => 'Doctor of Education in Science Education',
            'EdD. Filipino' => 'Doctor of Education in Filipino',
            'EdD. Technology Management' => 'Doctor of Education in Technology Management',
            'DM HRM' => 'Doctor of Management in Human Resource Management',
            'DM Public Administration' => 'Doctor of Management in Public Administration',
            'MBA' => 'Master in Business Administration',
            'MPH' => 'Master of Public Health',
            'MA Education' => 'Master of Arts in Education',
            'MA English' => 'Master of Arts in English',
            'MA Filipino' => 'Master of Arts in Filipino',
            'MA History' => 'Master of Arts in History',
            'MA Psychology' => 'Master of Arts in Psychology',
            'MA Sociology' => 'Master of Arts in Sociology',
            'MA Mathematics' => 'Master of Arts in Mathematics',
            'MAECE' => 'Master of Arts in Early Childhood Education',
            'MS Agriculture' => 'Master of Science in Agriculture',
            'MSIT' => 'Master of Science in Information Technology',
            'MTE' => 'Master in Teaching English',
            'MPM HRM' => 'Master in Public Management major in Human Resource Management',
            'MPM LGA' => 'Master in Public Management major in Local Governance and Administration',
            'MPM' => 'Master in Public Management',
        ];
    }

    /**
     * Description for a canonical course code, or null when unknown.
     */
    public static function descriptionFor(string $canonicalCode): ?string
    {
        return static::descriptions()[$canonicalCode] ?? null;
    }
}
