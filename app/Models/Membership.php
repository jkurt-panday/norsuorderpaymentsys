<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Membership extends Model
{
    protected $table = 'membership';

    protected $fillable = [
        'member_code',
        'member_desc',
    ];

    public function formInput()
    {
        return $this->hasOne(FormInput::class);
    }
}
