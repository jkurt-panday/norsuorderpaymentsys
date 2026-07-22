<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GraduateLedger extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_name',
        'course',
        'school_year',
        'semester_short',
        'semester',
        'units',
        'transaction_date',
        'reference_or_jev_number',
        'particulars',
        'tuition_per_unit_or_misc',
        'ar_payment',
        'amount',
        'remarks',
        'input_by',
    ];

    protected $casts = [
        'units' => 'integer',
        'tuition_per_unit_or_misc' => 'float',
        'amount' => 'float',
        'transaction_date' => 'date:Y-m-d',
    ];
}