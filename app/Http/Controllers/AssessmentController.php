<?php

namespace App\Http\Controllers;

use App\Models\AssessmentForm;
use App\Models\Courses;
use App\Services\AssessmentStatsService;
use App\Services\LedgerMatchingService;
use App\Services\ReceiptPDFService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AssessmentController extends Controller
{
    public function __construct(
        private readonly AssessmentStatsService $stats,
        private readonly LedgerMatchingService $ledgerMatcher,
        private ReceiptPDFService $receiptPDFService,
    ) {}
    
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
             ->with('course')
             ->filtered($request)
             ->orderBy($sort, $direction)
             ->paginate(15)
             ->withQueryString();
     
         return Inertia::render('staff/assessments/assessmentIndex', [
             'assessments' => $assessments,
             'filters' => [
                 'search' => $request->input('search', ''),
                 'sort' => $sort,
                 'direction' => $direction,
                 'date_from' => $request->input('date_from', ''),
                 'date_to' => $request->input('date_to', ''),
                 'course_id' => $request->input('course_id', ''),
                 'enrolled_under' => $request->input('enrolled_under', ''),
                 'sy_last_attended' => $request->input('sy_last_attended', ''),
                 'semester' => $request->input('semester', ''),
             ],
             'filterOptions' => [
                 'courses' => Courses::query()
                     ->select('id', 'course_code')
                     ->orderBy('course_code')
                     ->get(),
                 'enrolledUnder' => AssessmentForm::query()
                     ->distinct()
                     ->whereNotNull('enrolled_under')
                     ->orderBy('enrolled_under')
                     ->pluck('enrolled_under'),
                 'syLastAttended' => AssessmentForm::query()
                     ->distinct()
                     ->whereNotNull('sy_last_attended')
                     ->orderByDesc('sy_last_attended')
                     ->pluck('sy_last_attended'),
                 'semesters' => AssessmentForm::query()
                     ->distinct()
                     ->whereNotNull('semester')
                     ->pluck('semester'),
             ],
         ]);
     }

    public function dashboard(): InertiaResponse
    {
        $dailyRequests = $this->stats->dailyRequestsLast30Days();
        $requestsTrend = $this->stats->requestsTrend($dailyRequests);
    
        return Inertia::render('staff/assessments/assessmentDashboard', [
            'byCourse' => $this->stats->countsByCourse(),
            'bySemester' => $this->stats->countsBySemester(),
            'byEnrolledUnder' => $this->stats->countsByEnrolledUnder(),
            'monthlyTrend' => $this->stats->monthlyTrend(),
            'dailyRequests' => $dailyRequests,
            'requestsTrend' => $requestsTrend,
        ]);
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
    public function edit(Request $request, AssessmentForm $assessment): InertiaResponse
    {
        $validated = $request->validate([
            'ledger_student' => ['nullable', 'string', 'max:255'],
        ]);

        return Inertia::render('staff/assessments/assessmentEdit', [
            'assessment' => $assessment->load(['course']),
            'ledgerStatement' => $this->ledgerMatcher->forAssessment(
                $assessment,
                $validated['ledger_student'] ?? null,
            ),
        ]);
    }

    public function print(Request $request, AssessmentForm $assessment)
    {
        return $this->receiptPDFService
            ->soaPrint($request, $assessment)
            ->inline();
    }

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
