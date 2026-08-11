<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicTerm extends Model
{
    use HasFactory;

    protected $table = 'graduate_academic_term';

    protected $fillable = [
        'school_year',
        'semester_short',
        'semester',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function graduateLedgers()
    {
        return $this->hasMany(GraduateLedger::class);
    }

    /**
     * Resolve the full semester label from the short code.
     */
    public static function semesterLabel(string $short): string
    {
        return match ($short) {
            '2nd Sem.' => 'Second Semester',
            'Summer'   => 'Summer',
            default    => 'First Semester',
        };
    }

    /**
     * Resolve the sort order from the short code.
     */
    public static function sortOrder(string $short): int
    {
        return match ($short) {
            '1st Sem.' => 1,
            '2nd Sem.' => 2,
            'Summer'   => 3,
            default    => 1,
        };
    }
}
