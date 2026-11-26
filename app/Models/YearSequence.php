<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class YearSequence extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'year',
        'month',
        'op_number',
        'assessment_number',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'op_number' => 'integer',
        'assessment_number' => 'integer',
    ];
}
