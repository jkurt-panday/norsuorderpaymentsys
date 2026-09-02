<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LawAcademicTerm extends Model
{
    use HasFactory;

    protected $table = 'law_academic_term';

    protected $fillable = [
        'school_year',
        'semester_short',
        'semester',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function lawSchoolLedgers()
    {
        return $this->hasMany(LawSchoolLedger::class);
    }

    /**
     * Normalize any raw semester string to one of 3 canonical values.
     */
    public static function normalizeSemester(string $raw): string
    {
        $lower = strtolower(trim($raw));
        if (str_contains($lower, '2nd') || str_contains($lower, 'second')) {
            return 'Second Semester';
        }
        if (str_contains($lower, 'summer') || str_contains($lower, 'intersession')) {
            return 'Summer';
        }
        return 'First Semester';
    }

    /**
     * Resolve sort order from a canonical semester string.
     */
    public static function sortOrder(string $semester): int
    {
        return match ($semester) {
            'Second Semester' => 2,
            'Summer'          => 3,
            default           => 1,
        };
    }
}
