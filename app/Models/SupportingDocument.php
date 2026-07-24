<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportingDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_input_id',
        'original_filename',
        'stored_filename',
        'file_url',
        'mime_type',
        'file_extension',
        'file_size',
        'uploaded_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'uploaded_at' => 'datetime',
    ];

    public function formInput(): BelongsTo
    {
        return $this->belongsTo(FormInput::class);
    }

    public function staffInputs(): HasMany
    {
        return $this->hasMany(StaffInput::class, 'ref_document_id');
    }

    // Accessor for formatted file size
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    // Accessor for full URL
    public function getFullUrlAttribute(): string
    {
        return asset($this->file_url);
    }
}