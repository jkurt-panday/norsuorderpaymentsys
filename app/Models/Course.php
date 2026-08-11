<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;

    protected $table = 'graduate_course';

    protected $fillable = [
        'code',
        'title',
    ];

    public function graduateLedgers()
    {
        return $this->hasMany(GraduateLedger::class);
    }

    /**
     * All graduate program course codes used in the system.
     * Keep in sync with frontend courseOptions arrays.
     */
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
