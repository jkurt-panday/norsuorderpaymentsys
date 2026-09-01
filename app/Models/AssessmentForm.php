<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class AssessmentForm extends Model
{
    use HasFactory;

    protected $table = 'assessment_forms';

    protected $fillable = [
        'reference_number',
        'email',
        'contact_num',
        'first_name',
        'middle_name',
        'last_name',
        'student_id',
        'course_id',
        'address',
        'enrolled_under',
        'sy_last_attended',
        'semester',
    ];

    /**
     * Get the course associated with the assessment form.
     * 
     * @return BelongsTo<Courses, AssessmentForm>
     */
    public function course(): BelongsTo 
    {
        return $this->belongsTo(Courses::class);
    }

    /**
     * Scope a query to filter assessment forms.
     *
     * @param Builder<AssessmentForm> $query
     * @param string|null $search
     * @return Builder<AssessmentForm>
     */
    public function scopeFiltered(Builder $query, Request $request): Builder
    {
        return $query->when($request->search, fn ($q, $search) => $q->where(fn ($q) =>
                $q->where('reference_number', 'ilike', "%{$search}%")
                    ->orWhere('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
            ))
            ->when($request->date_from, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->date_to, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($request->course_id, fn ($q, $v) => $q->where('course_id', $v))
            ->when($request->enrolled_under, fn ($q, $v) => $q->where('enrolled_under', $v))
            ->when($request->sy_last_attended, fn ($q, $v) => $q->where('sy_last_attended', $v))
            ->when($request->semester, fn ($q, $v) => $q->where('semester', $v));
    }
}
