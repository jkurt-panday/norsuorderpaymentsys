<?php

namespace App\Http\Controllers;

use App\Models\AssessmentForm;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AssessmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): InertiaResponse
    {
        $sortable = ['reference_number', 'last_name', 'created_at'];

        $sort = in_array($request->input('sort'), $sortable, true)
            ? $request->input('sort')
            : 'created_at';

        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';

        $assessments = AssessmentForm::query()
            ->with('course') // needed for row.original.course.course_code in columns.tsx
            ->when($request->search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('reference_number', 'ilike', "%{$search}%")
                        ->orWhere('first_name', 'ilike', "%{$search}%")
                        ->orWhere('last_name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                });
            })
            ->when($request->date_from, function ($query, $dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($request->date_to, function ($query, $dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            })
            ->orderBy($sort, $direction)
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('staff/assessments/assessmentIndex', [
            'assessments' => $assessments,
            'filters' => [
                'search' => $request->input('search', ''),
                'sort' => $sort,
                'direction' => $direction,
                'date_from' => $request->input('date_from', ''),
                'date_to' => $request->input('date_to', ''),
            ],
        ]);
    }

    public function dashboard(): InertiaResponse
    {
        return Inertia::render('staff/assessments/assessmentDashboard');
    }

    // /**
    //  * Show the form for creating a new resource.
    //  */
    // public function create()
    // {
    //     //
    // }

    // /**
    //  * Store a newly created resource in storage.
    //  */
    // public function store(Request $request)
    // {
    //     //
    // }

    /**
     * Display the specified resource.
     */
    public function show(AssessmentForm $assessment): InertiaResponse
    {
        return Inertia::render('staff/assessments/assessmentShow', [
            'assessment' => $assessment->load('course'),
        ]);
    }

    // /**
    //  * Show the form for editing the specified resource.
    //  */
    // public function edit(string $id)
    // {
    //     //
    // }

    // /**
    //  * Update the specified resource in storage.
    //  */
    // public function update(Request $request, string $id)
    // {
    //     //
    // }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AssessmentForm $assessment)
    {
        $assessment->delete();

        return redirect()->route('staff.assessments.index')->with('success', 'Assessment deleted successfully.');
    }
}