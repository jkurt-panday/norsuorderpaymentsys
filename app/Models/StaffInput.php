<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffInput extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_input_id',
        'fundcluster_id',
        'ref_document_id',
        'purpose',
        'ref_date',
        'uacs_id',
        'status',
        'or_no',
        'or_date',
    ];

    protected $casts = [
        'ref_date' => 'date',
        'or_date' => 'date',
        'status' => 'string',
    ];

    public const STATUSES = ['pending', 'processed', 'paid', 'approved', 'cancelled'];

    public function formInput(): BelongsTo
    {
        return $this->belongsTo(FormInput::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccountInfo::class, 'fundcluster_id');
    }

    public function referenceDocument(): BelongsTo
    {
        return $this->belongsTo(SupportingDocument::class, 'ref_document_id');
    }

    public function uacs(): BelongsTo
    {
        return $this->belongsTo(UACS::class, 'uacs_id');
    }

    // Accessor for status badge
    public function getStatusBadgeAttribute(): string
    {
        $badges = [
            'pending' => 'badge-warning',
            'approved' => 'badge-success',
            'cancelled' => 'badge-danger',
        ];

        return $badges[$this->status] ?? 'badge-secondary';
    }

    // Accessor for status label
    public function getStatusLabelAttribute(): string
    {
        return ucfirst($this->status);
    }
}