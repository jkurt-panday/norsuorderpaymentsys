<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LawCourse extends Model
{
    use HasFactory;

    protected $table = 'law_course';

    protected $fillable = [
        'code',
        'title',
    ];

    public function lawSchoolLedgers()
    {
        return $this->hasMany(LawSchoolLedger::class);
    }
}
