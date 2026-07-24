<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FormInput extends Model
{
    use HasFactory;

    protected $appends = ['full_name'];

    protected $fillable = [
        'reference_number',
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
        'payment_detail_option_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'request_type' => 'string',
    ];

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function paymentDetailOption(): BelongsTo
    {
        return $this->belongsTo(PaymentDetailOption::class, 'payment_detail_option_id');
    }

    public function supportingDocuments(): HasMany
    {
        return $this->hasMany(SupportingDocument::class);
    }

    public function staffInput(): HasOne
    {
        return $this->hasOne(StaffInput::class);
    }

    // Accessor for formatted reference number
    public function getFormattedReferenceNumberAttribute(): string
    {
        return $this->reference_number;
    }

    // Accessor for full name
    public function getFullNameAttribute(): string
    {
        return trim($this->firstname_or_office . ' ' . $this->middlename_or_project . ' ' . $this->lastname_or_agency);
    }
}