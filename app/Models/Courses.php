<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Courses extends Model
{
    /** @use HasFactory<Factory<Courses>> */
    use HasFactory;

    /**
     * Placeholder/fallback course codes that should not be displayed
     * in public submission dropdowns (e.g. Order of Payment / Assessment forms).
     *
     * @var list<string>
     */
    public const PLACEHOLDER_CODES = [
        'UNASSIGNED',
        'UNDECIDED',
        'N/A',
        'NONE',
    ];

    protected $table = 'courses';

    protected $fillable = [
        'course_code',
        'course_desc',
        'course_college',
    ];

    /**
     * Scope to exclude internal/placeholder courses from public-facing forms.
     *
     * @param  Builder<Courses>  $query
     * @return Builder<Courses>
     */
    public function scopePublicAvailable($query)
    {
        return $query->whereNotIn('course_code', self::PLACEHOLDER_CODES);
    }
}
