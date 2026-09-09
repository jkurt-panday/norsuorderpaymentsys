<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LawSchoolLedger extends Model
{
    /** @use HasFactory<Factory<LawSchoolLedger>> */
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
        'status',
        'input_by',
    ];

    protected $casts = [
        'units' => 'decimal:2',
        'rate' => 'decimal:2',
        'amount' => 'decimal:2',
        'transaction_date' => 'date:Y-m-d',
    ];

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

    public function lawStudent(): BelongsTo
    {
        return $this->student();
    }

    public function lawCourse(): BelongsTo
    {
        return $this->course();
    }

    public function lawAcademicTerm(): BelongsTo
    {
        return $this->academicTerm();
    }

    public function getStudentIdFkAttribute(): int
    {
        return (int) $this->student_id;
    }

    public function getLastNameAttribute(): ?string { return $this->student?->last_name; }
    public function getFirstNameAttribute(): ?string { return $this->student?->first_name; }
    public function getMiddleNameAttribute(): ?string { return $this->student?->middle_name; }
    public function getMiddleInitialAttribute(): ?string { return $this->student?->middle_name ? substr($this->student->middle_name, 0, 1) : null; }
    public function getSchoolYearAttribute(): ?string { return $this->academicTerm?->school_year; }
    public function getSemesterOrSummerAttribute(): ?string { return $this->academicTerm?->semester; }
    public function getReferenceJevOrNumberAttribute(): ?string { return $this->reference_number; }
    public function getTuitionPerUnitOrFeePerSemesterAttribute(): mixed { return $this->rate; }
    public function getArOrPaymentAttribute(): string { return $this->entry_type === 'ar' ? 'AR' : ucfirst((string) $this->entry_type); }
}
