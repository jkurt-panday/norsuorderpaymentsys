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
     * All graduate program course codes used in the system.
     * Keep in sync with frontend courseOptions arrays.
     */
    /** @return list<string> */
    public static function allCodes(): array
    {
        return [
            'PhD Educational Management',
            'PhD Mathematics Education',
            'EdD Educational Management',
            'EdD Instruction',
            'EdD Science Education',
            'EdD Filipino',
            'EdD Technology Management',
            'DM HRM',
            'DM Public Administration',
            'MBA',
            'MPH',
            'MA Education',
            'MA English',
            'MA Filipino',
            'MA History',
            'MA Psychology',
            'MA Mathematics',
            'MAECE',
            'MS Agriculture',
            'MSIT',
            'MTE',
            'MPM HRM',
            'MPM LGA',
        ];
    }
}
