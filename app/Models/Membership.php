<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Membership extends Model
{
    /** @use HasFactory<Factory<Membership>> */
    use HasFactory;

    protected $fillable = [
        'member_code',
        'member_desc',
    ];

    /** @return HasMany<FormInput, $this> */
    public function formInputs(): HasMany
    {
        return $this->hasMany(FormInput::class);
    }
}
