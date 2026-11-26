<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentDetailsOption extends Model
{
    use SoftDeletes;

    protected $table = 'payment_details_options';

    protected $fillable = ['payment_desc'];

    /** @return HasOne<FormInput, $this> */
    public function formInput(): HasOne
    {
        return $this->hasOne(FormInput::class);
    }
}
