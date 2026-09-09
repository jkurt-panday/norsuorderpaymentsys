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
        'student_id_fk',
        'course_id',
        'academic_term_id',
        'entry_type',
        'last_name',
        'first_name',
        'middle_initial',
        'middle_name',
        'course',
        'school_year',
        'semester_or_summer',
        'units',
        'transaction_date',
        'reference_jev_or_number',
        'particulars',
        'tuition_per_unit_or_fee_per_semester',
        'ar_or_payment',
        'amount',
        'remarks',
        'status',
        'input_by',
    ];

    protected $casts = [
        'units' => 'decimal:2',
        'tuition_per_unit_or_fee_per_semester' => 'decimal:2',
        'amount' => 'decimal:2',
        'transaction_date' => 'date:Y-m-d',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    /** @return BelongsTo<LawStudent, $this> */
    public function lawStudent(): BelongsTo
    {
        return $this->belongsTo(LawStudent::class, 'student_id_fk');
    }

    /** @return BelongsTo<LawCourse, $this> */
    public function lawCourse(): BelongsTo
    {
        return $this->belongsTo(LawCourse::class, 'course_id');
    }

    /** @return BelongsTo<LawAcademicTerm, $this> */
    public function lawAcademicTerm(): BelongsTo
    {
        return $this->belongsTo(LawAcademicTerm::class, 'academic_term_id');
    }
}
