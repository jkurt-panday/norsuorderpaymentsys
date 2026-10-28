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
        'norsu_object_code',
        'norsu_sub_object_code',
        'uacs_object_code',
        'uacs_sub_object_code',
        'rca_code',
        'account_title',
    ];

    /** @return HasMany<StaffInput, $this> */
    public function staffInputs(): HasMany
    {
        return $this->hasMany(StaffInput::class, 'uacs_id');
    }

    /**
     * Human-readable label for UACS selectors and related documents.
     */
    public function getDisplayNameAttribute(): string
    {
        $code = implode('-', array_filter([
            $this->uacs_object_code,
            $this->uacs_sub_object_code,
        ], static fn (?string $value): bool => $value !== null && $value !== ''));

        return implode(' - ', array_filter([
            $code,
            $this->account_title,
        ], static fn (?string $value): bool => $value !== null && $value !== ''));
    }
}
