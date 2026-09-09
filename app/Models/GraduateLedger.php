<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GraduateLedger extends Model
{
    /** @use HasFactory<Factory<GraduateLedger>> */
    use HasFactory;

    protected $fillable = [
        'student_id',
        'course_id',
        'academic_term_id',
        'entry_type',

        'units',
        'transaction_date',
        'reference_number',
        'particulars',
        'rate',
        'amount',
        'remarks',
        'input_by',
        'status',
    ];

    protected $casts = [
        'units' => 'integer',
        'rate' => 'decimal:2',
        'amount' => 'decimal:2',
        'transaction_date' => 'date:Y-m-d',
    ];

    public function getReferenceOrJevNumberAttribute(): ?string
    {
        return $this->reference_number;
    }

    public function getTuitionPerUnitOrMiscAttribute(): mixed
    {
        return $this->rate;
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<AcademicTerm, $this> */
    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }
}
