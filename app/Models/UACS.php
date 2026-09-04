<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UACS extends Model
{
    protected $table = 'uacs';

    /** @use HasFactory<Factory<UACS>> */
    use HasFactory;

    protected $fillable = [
        'account_title',
        'object_code',
    ];

    /** @return HasMany<StaffInput, $this> */
    public function staffInputs(): HasMany
    {
        return $this->hasMany(StaffInput::class, 'uacs_id');
    }

    // Accessor for display
    public function getDisplayNameAttribute(): string
    {
        return $this->object_code.' - '.$this->account_title;
    }
}
