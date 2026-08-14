<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AssessmentForm extends Model
{
    use HasFactory;

    protected $table = 'assessment_forms';

    protected $fillable = [
        'reference_num',
        'email',
        'contact_num',
        'first_name',
        'middle_name',
        'last_name',
        'courses',
        'address',
        'enrolled_under',
        'sy_last_attended',
        'semester',
    ];

    public function course(): HasOne 
    {
        return $this->hasOne(Courses::class);
    }
}
