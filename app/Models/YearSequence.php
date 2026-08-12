<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YearSequence extends Model
{
    protected $fillable = [
        'year',
        'month',
        'current_number',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'current_number' => 'integer',
    ];
}