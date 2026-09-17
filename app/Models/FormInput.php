<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FormInput extends Model
{
    /** @use HasFactory<Factory<FormInput>> */
    use HasFactory;

    protected $appends = ['full_name'];

    protected $fillable = [
        'reference_number',
        'email',
        // 'purpose',
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
        'student_num',
        'academic_term',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'request_type' => 'string',
    ];

    /** @return BelongsTo<Membership, $this> */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    /** @return BelongsTo<PaymentDetailOption, $this> */
    public function paymentDetailOption(): BelongsTo
    {
        return $this->belongsTo(PaymentDetailOption::class, 'payment_detail_option_id');
    }

    /**
     * Student this submission is linked to.
     * Note the column is named `student_num` (FK → students.id),
     * so the foreign key must be passed explicitly.
     *
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_num');
    }

    /**
     * Academic term selected on the student tab.
     * Note the column is named `academic_term` (FK → academic_terms.id),
     * so the foreign key must be passed explicitly.
     *
     * @return BelongsTo<AcademicTerm, $this>
     */
    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'academic_term');
    }

    /** @return HasMany<SupportingDocument, $this> */
    public function supportingDocuments(): HasMany
    {
        return $this->hasMany(SupportingDocument::class);
    }

    /** @return HasOne<StaffInput, $this> */
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
        return trim($this->firstname_or_office.' '.$this->middlename_or_project.' '.$this->lastname_or_agency);
    }
}
