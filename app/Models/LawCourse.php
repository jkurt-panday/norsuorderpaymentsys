<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LawCourse extends Model
{
    /** @use HasFactory<Factory<LawCourse>> */
    use HasFactory;

    protected $table = 'law_course';

    protected $fillable = [
        'code',
        'title',
    ];

    /** @return HasMany<LawSchoolLedger, $this> */
    public function lawSchoolLedgers(): HasMany
    {
        return $this->hasMany(LawSchoolLedger::class);
    }
}
