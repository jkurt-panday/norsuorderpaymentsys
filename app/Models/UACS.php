<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Uacs extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_title',
        'object_code',
    ];

    public function staffInputs(): HasMany
    {
        return $this->hasMany(StaffInput::class);
    }

    // Accessor for display
    public function getDisplayNameAttribute(): string
    {
        return $this->object_code . ' - ' . $this->account_title;
    }
}