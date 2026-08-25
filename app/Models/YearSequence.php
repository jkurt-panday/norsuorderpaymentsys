<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YearSequence extends Model
{
    protected $fillable = [
        'year',
        'month',
        'op_number',
        'assessment_number'
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'op_number' => 'integer',
        'assessment_number' => 'integer'
    ];
}
