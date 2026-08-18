<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GraduateLedger extends Model
{
    use HasFactory;

    protected $fillable = [
        // --- New normalized FK columns (post-migration) ---
        'student_id',
        'course_id',
        'academic_term_id',
        'entry_type',

        // --- Legacy string columns (kept until drop migration) ---
        'student_name',
        'course',
        'school_year',
        'semester_short',
        'semester',
        'ar_payment',

        // --- Stable transaction columns (unchanged) ---
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
        'units' => 'float',
        'tuition_per_unit_or_misc' => 'float',
        'amount' => 'float',
        'transaction_date' => 'date:Y-m-d',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function academicTerm()
    {
        return $this->belongsTo(AcademicTerm::class);
    }
}
