<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LawSchoolLedger extends Model
{
    use HasFactory;

    protected $fillable = [
        'last_name',
        'first_name',
        'middle_initial',
        'student_id',
        'course',
        'school_year',
        'semester_or_summer',
        'units',
        'transaction_date',
        'reference_jev_or_number',
        'particulars',
        'tuition_per_unit_or_fee_per_semester',
        'ar_or_payment',
        'amount',
        'remarks',
        'status',
        'input_by',
    ];

    protected $casts = [
        'units' => 'decimal:2',
        'tuition_per_unit_or_fee_per_semester' => 'decimal:2',
        'amount' => 'decimal:2',
        'transaction_date' => 'date:Y-m-d',
    ];
}
