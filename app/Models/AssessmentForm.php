<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AssessmentForm extends Model
{
    use HasFactory;

    protected $table = 'assessment_forms';

    protected $fillable = [
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
}
