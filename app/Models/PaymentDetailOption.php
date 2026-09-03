<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentDetailOption extends Model
{
    /** @use HasFactory<Factory<PaymentDetailOption>> */
    use HasFactory;

    protected $fillable = [
        'payment_desc',
    ];

    /** @return HasMany<FormInput, $this> */
    public function formInputs(): HasMany
    {
        return $this->hasMany(FormInput::class);
    }
}
