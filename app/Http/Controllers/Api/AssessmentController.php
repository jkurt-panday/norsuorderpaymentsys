<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\AssessmentSoaMail;
use App\Models\AssessmentForm;
use App\Services\AssessmentStatsService;
use App\Services\ReceiptPDFService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class AssessmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AssessmentForm::query()->with('course')->filtered($request)->orderBy(in_array($request->query('sort'), ['reference_number', 'last_name', 'created_at'], true) ? $request->query('sort') : 'created_at', $request->query('direction') === 'asc' ? 'asc' : 'desc');
        $p = $query->paginate(min(max($request->integer('per_page', 10), 1), 100))->withQueryString();

        return response()->json(['data' => $p->items(), 'meta' => ['current_page' => $p->currentPage(), 'per_page' => $p->perPage(), 'total' => $p->total(), 'last_page' => $p->lastPage()], 'links' => ['next' => $p->nextPageUrl(), 'prev' => $p->previousPageUrl()]]);
    }

    public function dashboard(AssessmentStatsService $stats): JsonResponse
    {
        $daily = $stats->dailyRequestsLast30Days();

        return response()->json(['data' => ['by_course' => $stats->countsByCourse(), 'by_semester' => $stats->countsBySemester(), 'by_enrolled_under' => $stats->countsByEnrolledUnder(), 'monthly_trend' => $stats->monthlyTrend(), 'daily_requests' => $daily, 'requests_trend' => $stats->requestsTrend($daily)]]);
    }

    public function show(AssessmentForm $assessment): JsonResponse
    {
        return response()->json(['data' => $assessment->load('course')]);
    }

    public function update(Request $request, AssessmentForm $assessment): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'string', Rule::in(['pending', 'mailed', 'rejected', 'completed'])]]);
        $assessment->update($data);

        return response()->json(['message' => 'Assessment status updated.', 'data' => $assessment->refresh()]);
    }

    public function destroy(AssessmentForm $assessment): Response
    {
        $assessment->delete();

        return response()->noContent();
    }

    public function print(Request $request, AssessmentForm $assessment, ReceiptPDFService $service): Response
    {
        return $service->soaPrint($request, $assessment)->inline();
    }

    public function emailSoa(Request $request, AssessmentForm $assessment, ReceiptPDFService $service): JsonResponse
    {
        $data = $request->validate(['subject' => 'nullable|string|max:255', 'recipient_name' => 'nullable|string|max:255', 'note' => 'nullable|string|max:2000']);
        $pdf = $service->soaPrint($request, $assessment)->generatePdfContent();
        Mail::to($assessment->email)->send(new AssessmentSoaMail($assessment, $pdf, $data['subject'] ?? null, $data['recipient_name'] ?? null, $data['note'] ?? null));
        $assessment->update(['status' => 'mailed']);

        return response()->json(['message' => 'Assessment Statement of Account emailed successfully.']);
    }
}
