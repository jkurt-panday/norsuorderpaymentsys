<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccountInfo extends Model
{
    use HasFactory;

    protected $table = 'bankaccount_infos';

    protected $fillable = [
        'account_name',
        'bank_name',
        'account_num',
    ];

    public function staffInputs(): HasMany
    {
        return $this->hasMany(StaffInput::class, 'fundcluster_id');
    }

    // Accessor for formatted account display
    public function getDisplayNameAttribute(): string
    {
        return $this->account_name . ' - ' . $this->bank_name . ' (' . $this->account_num . ')';
    }
}