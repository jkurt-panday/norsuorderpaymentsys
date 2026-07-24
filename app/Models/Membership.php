<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Membership extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_code',
        'member_desc',
    ];

    public function formInputs(): HasMany
    {
        return $this->hasMany(FormInput::class);
    }
}