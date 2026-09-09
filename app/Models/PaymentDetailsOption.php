<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PaymentDetailsOption extends Model
{
    protected $table = 'payment_details_options';

    protected $fillable = ['payment_desc'];

    /** @return HasOne<FormInput, $this> */
    public function formInput(): HasOne
    {
        return $this->hasOne(FormInput::class);
    }
}
