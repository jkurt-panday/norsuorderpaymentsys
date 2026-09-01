<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GraduateLedger extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'course_id',
        'academic_term_id',
        'entry_type',

        'units',
        'transaction_date',
        'reference_or_jev_number',
        'particulars',
        'tuition_per_unit_or_misc',
        'amount',
        'remarks',
        'input_by',
    ];

    protected $casts = [
        'units' => 'integer',
        'tuition_per_unit_or_misc' => 'decimal:2',
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
}
