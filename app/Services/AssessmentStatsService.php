<?php

namespace App\Services;

use App\Models\AssessmentForm;
// use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class AssessmentStatsService
{
    public function countsByCourse(): array
    {
        return AssessmentForm::query()
            ->join('courses', 'courses.id', '=', 'assessment_forms.course_id')
            ->selectRaw('courses.course_code as course, count(*) as total')
            ->groupBy('courses.course_code')
            ->orderByDesc('total')
            ->get()
            ->toArray();
    }

    public function countsBySemester(): array
    {
        return AssessmentForm::query()
            ->selectRaw('semester, count(*) as total')
            ->whereNotNull('semester')
            ->groupBy('semester')
            ->orderBy('semester')
            ->get()
            ->toArray();
    }

    public function countsByEnrolledUnder(): array
    {
        return AssessmentForm::query()
            ->selectRaw('enrolled_under, count(*) as total')
            ->whereNotNull('enrolled_under')
            ->groupBy('enrolled_under')
            ->orderByDesc('total')
            ->get()
            ->toArray();
    }

    public function monthlyTrend(int $months = 6): array
    {
        return AssessmentForm::query()
            ->selectRaw("to_char(created_at, 'YYYY-MM') as month, count(*) as total")
            ->where('created_at', '>=', now()->subMonths($months)->startOfMonth())
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->toArray();
    }

    public function dailyRequestsLast30Days(): array
    {
        $start = now()->subDays(29)->startOfDay();
        $end = now()->endOfDay();
    
        $counts = AssessmentForm::query()
            ->selectRaw('DATE(created_at) as date, count(*) as total')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('date')
            ->pluck('total', 'date');
    
        return collect(CarbonPeriod::create($start, $end))
            ->map(fn (Carbon $day) => [
                'date' => $day->format('M j'),
                'total' => (int) ($counts[$day->format('Y-m-d')] ?? 0),
            ])
            ->values()
            ->toArray();
    }
    
    public function requestsTrend(array $daily): array
    {
        $mid = intdiv(count($daily), 2);
        $previous = collect($daily)->take($mid)->sum('total');
        $recent = collect($daily)->skip($mid)->sum('total');
    
        $change = $previous > 0
            ? round((($recent - $previous) / $previous) * 100, 1)
            : ($recent > 0 ? 100.0 : 0.0);
    
        return ['value' => $change, 'recent' => $recent, 'previous' => $previous];
    }
}