<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssessmentFormInfoRequest;
use App\Models\AssessmentForm;
use App\Models\Courses;
use App\Services\ReceiptPDFService;
use App\Services\ReferenceNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PublicAssessmentFormController extends Controller
{
    public function __construct(
        private readonly ReferenceNumberService $referenceNumberService,
        private readonly ReceiptPDFService $receiptPdfService,
    ) {}

    /**
     * Return courses for assessment form options.
     * GET /api/v1/public/assessmentform/options  (optional — add to routes if needed)
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'data' => [
                'courses' => Courses::query()->publicAvailable()->orderBy('course_code')->get(['id', 'course_code', 'course_desc']),
            ],
        ]);
    }

    /**
     * Submit the assessment form.
     * POST /api/v1/public/assessmentform
     */
    public function store(AssessmentFormInfoRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $referenceNumber = $this->referenceNumberService->assess_ref_gen();

            $assessment = AssessmentForm::query()->create([
                'reference_number' => $referenceNumber,
                'email'            => $validated['email'],
                'contact_num'      => $validated['contact_num'],
                'first_name'       => $validated['first_name'],
                'middle_name'      => $validated['middle_name'] ?? null,
                'last_name'        => $validated['last_name'],
                'student_id'       => $validated['student_id'],
                'course_id'        => $validated['course_id'],
                'address'          => $validated['address'],
                'enrolled_under'   => $validated['enrolled_under'],
                'sy_last_attended' => $validated['sy_last_attended'],
                'semester'         => $validated['semester'],
                'status'           => 'pending',
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Assessment form submitted successfully.',
                'data'    => [
                    'reference_number' => $assessment->reference_number,
                    'access_token' => $this->accessToken($assessment->reference_number),
                ],
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('API assessment form submission failed', ['message' => $e->getMessage()]);

            return response()->json(['message' => 'Failed to submit request. Please try again.'], 500);
        }
    }

    /**
     * Get a submitted assessment by reference number.
     * GET /api/v1/public/assessmentform/{reference}
     */
    public function show(string $reference, Request $request): JsonResponse
    {
        $assessment = AssessmentForm::where('reference_number', $reference)->firstOrFail();

        $this->authorizeReference($reference, $request);

        $assessment->load(['course']);

        return response()->json(['data' => $assessment]);
    }

    /**
     * Stream the assessment receipt PDF inline.
     * GET /api/v1/public/assessmentform/{reference}/print
     */
    public function print(string $reference, Request $request): mixed
    {
        $assessment = AssessmentForm::where('reference_number', $reference)->firstOrFail();

        $this->authorizeReference($reference, $request);

        return $this->receiptPdfService
            ->assessmentPrint($assessment)
            ->name("Assessment-Transaction-{$assessment->reference_number}.pdf")
            ->inline();
    }

    /**
     * Download the assessment receipt PDF.
     * GET /api/v1/public/assessmentform/{reference}/download
     */
    public function download(string $reference, Request $request): mixed
    {
        $assessment = AssessmentForm::where('reference_number', $reference)->firstOrFail();

        $this->authorizeReference($reference, $request);

        return $this->receiptPdfService
            ->assessmentPrint($assessment)
            ->name("Assessment-Transaction-{$assessment->reference_number}.pdf");
    }

    private function authorizeReference(string $reference, Request $request): void
    {
        abort_unless(
            hash_equals($this->accessToken($reference), (string) $request->query('token', '')),
            404,
        );
    }

    private function accessToken(string $reference): string
    {
        return hash_hmac('sha256', $reference, (string) config('app.key'));
    }
}
