<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormInput extends Model
{
    protected $table = 'form_input';

    protected $fillable = [
        'email',
        'contact_num',
        'firstname_or_office',
        'middlename_or_project',
        'lastname_or_agency',
        'office_or_college',
        'position_or_designation',
        'address',
        'amount',
        'request_type',
        'membership_id',
        'payment_details_id',
        'supporting_documents',
    ];

    protected $casts = [
        'supporting_documents' => 'array',
        'amount' => 'decimal:2',
    ];

    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }

    public function paymentDetails()
    {
        return $this->belongsTo(PaymentDetailsOption::class, 'payment_details_id');
    }

    public function staffInput()
    {
        return $this->hasOne(StaffInput::class);
    }
}
