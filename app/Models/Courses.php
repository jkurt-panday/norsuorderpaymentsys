<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Courses extends Model
{
    /** @use HasFactory<Factory<Courses>> */
    use HasFactory;

    protected $table = 'courses';

    protected $fillable = [
        'course_code',
        'course_desc',
    ];
}
