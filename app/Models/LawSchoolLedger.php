<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LawSchoolLedger extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'student_name',
        'program',
        'year_level',
        'academic_year',
        'semester',
        'units',
        'transaction_date',
        'due_date',
        'reference_or_jev_number',
        'particulars',
        'tuition_per_unit_or_misc',
        'transaction_type',
        'amount',
        'remaining_balance',
        'status',
        'remarks',
        'input_by',
    ];

    protected $casts = [
        'units' => 'decimal:2',
        'tuition_per_unit_or_misc' => 'decimal:2',
        'amount' => 'decimal:2',
        'remaining_balance' => 'decimal:2',
        'transaction_date' => 'date:Y-m-d',
        'due_date' => 'date:Y-m-d',
    ];
}
