<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuthorizedOfficial extends Model
{
    /** @use HasFactory<Factory<AuthorizedOfficial>> */
    use HasFactory;

    protected $table = 'authorized_officials';

    protected $fillable = [
        'name',
        'position',
        'course',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * @param  Builder<AuthorizedOfficial>  $query
     * @return Builder<AuthorizedOfficial>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * The official currently designated as the document signatory.
     * Prefers the explicitly-active record, then falls back to the most
     * recently-created one so the PDF never renders with a blank signature.
     */
    public static function current(): ?self
    {
        return static::active()->latest('id')->first()
            ?? static::query()->latest('id')->first();
    }
}
