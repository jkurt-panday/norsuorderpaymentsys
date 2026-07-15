<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentDetailsOption extends Model
{
    protected $table = 'payment_details_options';

    protected $fillable = ['payment_desc'];

    public function formInput()
    {
        return $this->hasOne(FormInput::class);
    }
}
