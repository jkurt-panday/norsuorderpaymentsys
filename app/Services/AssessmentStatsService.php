<?php

namespace App\Services;

use App\Models\AssessmentForm;
// use Illuminate\Support\Facades\DB;
use Carbon\CarbonPeriod;

class AssessmentStatsService
{
    /** @return list<array{course: string, total: int}> */
    public function countsByCourse(): array
    {
        return array_values(AssessmentForm::query()
            ->join('courses', 'courses.id', '=', 'assessment_forms.course_id')
            ->selectRaw('courses.course_code as course, count(*) as total')
            ->groupBy('courses.course_code')
            ->orderByDesc('total')
            ->get()
            ->map(fn (AssessmentForm $record): array => [
                'course' => (string) $record->getAttribute('course'),
                'total' => (int) $record->getAttribute('total'),
            ])
            ->values()
            ->all());
    }

    /** @return list<array{semester: string, total: int}> */
    public function countsBySemester(): array
    {
        return array_values(AssessmentForm::query()
            ->selectRaw('semester, count(*) as total')
            ->whereNotNull('semester')
            ->groupBy('semester')
            ->orderBy('semester')
            ->get()
            ->map(fn (AssessmentForm $record): array => [
                'semester' => (string) $record->semester,
                'total' => (int) $record->getAttribute('total'),
            ])
            ->values()
            ->all());
    }

    /** @return list<array{enrolled_under: string, total: int}> */
    public function countsByEnrolledUnder(): array
    {
        return array_values(AssessmentForm::query()
            ->selectRaw('enrolled_under, count(*) as total')
            ->whereNotNull('enrolled_under')
            ->groupBy('enrolled_under')
            ->orderByDesc('total')
            ->get()
            ->map(fn (AssessmentForm $record): array => [
                'enrolled_under' => (string) $record->enrolled_under,
                'total' => (int) $record->getAttribute('total'),
            ])
            ->values()
            ->all());
    }

    /** @return list<array{month: string, total: int}> */
    public function monthlyTrend(int $months = 6): array
    {
        return array_values(AssessmentForm::query()
            ->selectRaw("to_char(created_at, 'YYYY-MM') as month, count(*) as total")
            ->where('created_at', '>=', now()->subMonths($months)->startOfMonth())
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn (AssessmentForm $record): array => [
                'month' => (string) $record->getAttribute('month'),
                'total' => (int) $record->getAttribute('total'),
            ])
            ->values()
            ->all());
    }

    /** @return list<array{date: string, total: int}> */
    public function dailyRequestsLast30Days(): array
    {
        $start = now()->subDays(29)->startOfDay();
        $end = now()->endOfDay();

        $counts = AssessmentForm::query()
            ->selectRaw('DATE(created_at) as date, count(*) as total')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('date')
            ->pluck('total', 'date');

        $daily = [];

        foreach (CarbonPeriod::create($start, $end) as $day) {
            $daily[] = [
                'date' => $day->format('M j'),
                'total' => (int) ($counts->get($day->format('Y-m-d')) ?? 0),
            ];
        }

        return $daily;
    }

    /**
     * @param  list<array{date: string, total: int}>  $daily
     * @return array{value: float, recent: int|float, previous: int|float}
     */
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
