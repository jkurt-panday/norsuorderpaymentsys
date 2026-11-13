<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicFormSubmissionRequest;
use App\Models\AcademicTerm;
use App\Models\CollegeOffice;
use App\Models\Course;
use App\Models\FormInput;
use App\Models\Membership;
use App\Models\PaymentDetailOption;
use App\Models\Student;
use App\Services\FileUploadService;
use App\Services\ReceiptPDFService;
use App\Services\ReferenceNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PublicOpFormController extends Controller
{
    public function __construct(
        private readonly FileUploadService $fileUploadService,
        private readonly ReferenceNumberService $referenceNumberService,
        private readonly ReceiptPDFService $receiptPDFService,
    ) {}

    /**
     * Return select-list options for the public OP form.
     * GET /api/v1/public/opform/options
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'data' => [
                'memberships'    => Membership::query()->orderBy('member_desc')->get(['id', 'member_code', 'member_desc']),
                'paymentOptions' => PaymentDetailOption::query()->orderBy('payment_desc')->get(['id', 'payment_desc']),
                'courses'        => Course::query()->publicAvailable()->orderBy('course_desc')->get(['id', 'course_desc', 'course_code']),
                'academicTerms'  => AcademicTerm::query()->orderBy('school_year', 'desc')->get(['id', 'school_year', 'semester']),
                'collegeOffices' => CollegeOffice::query()->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    /**
     * Submit the public OP form.
     * POST /api/v1/public/opform
     */
    public function store(PublicFormSubmissionRequest $request): JsonResponse
    {
        $validated        = $request->validated();
        $uploadedDocuments = [];
        $transactionCommitted = false;

        try {
            DB::beginTransaction();

            $referenceNumber = $this->referenceNumberService->generate();

            $studentId      = $this->resolveStudentId((string) ($validated['student_num'] ?? ''));
            $academicTermId = $this->resolveAcademicTermId(
                (string) ($validated['school_year'] ?? ''),
                (string) ($validated['semester'] ?? ''),
            );

            $formInput = FormInput::create([
                'reference_number'          => $referenceNumber,
                'email'                     => $validated['email'],
                'contact_num'               => $validated['contact_num'],
                'firstname_or_office'       => $validated['firstname_or_office'],
                'middlename_or_project'     => $validated['middlename_or_project'] ?? null,
                'lastname_or_agency'        => $validated['lastname_or_agency'],
                'office_or_college'         => $validated['office_or_college'],
                'position_or_designation'   => $validated['position_or_designation'],
                'address'                   => $validated['address'],
                'amount'                    => $validated['amount'],
                'request_type'              => $validated['request_type'],
                'membership_id'             => $validated['membership_id'],
                'payment_detail_option_id'  => $validated['payment_detail_option_id'],
                'student_num'               => $studentId,
                'submitted_student_number'  => filled($validated['student_num'] ?? null)
                    ? trim((string) $validated['student_num'])
                    : null,
                'course_id'     => $validated['course_id'] ?? null,
                'academic_term' => $academicTermId,
            ]);

            if ($request->hasFile('documents')) {
                $uploadedDocuments = $this->fileUploadService->uploadDocuments(
                    $request->file('documents'),
                    $formInput,
                );
            }

            DB::commit();
            $transactionCommitted = true;

            return response()->json([
                'message' => 'Order of Payment submitted successfully.',
                'data'    => [
                    'reference_number' => $formInput->reference_number,
                    'access_token' => $this->accessToken($formInput->reference_number),
                ],
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            if (! $transactionCommitted && $uploadedDocuments !== []) {
                $this->fileUploadService->cleanupUploadedDocuments($uploadedDocuments);
            }

            Log::error('API public OP form submission failed', ['message' => $e->getMessage()]);

            return response()->json(['message' => 'Failed to submit request. Please try again.'], 500);
        }
    }

    /**
     * Return a submitted form's data by reference number.
     * Requires a signed token query param to prevent enumeration.
     * GET /api/v1/public/opform/{reference}
     */
    public function show(string $reference, Request $request): JsonResponse
    {
        $formInput = FormInput::where('reference_number', $reference)->firstOrFail();

        // Verify the request carries the access token issued at submission time.
        $this->authorizeReference($reference, $request);

        $formInput->load(['membership', 'paymentDetailOption', 'supportingDocuments', 'course', 'academicTerm']);

        return response()->json(['data' => $formInput]);
    }

    /**
     * Stream the receipt PDF inline.
     * GET /api/v1/public/opform/{reference}/print
     */
    public function print(string $reference, Request $request): mixed
    {
        $formInput = FormInput::where('reference_number', $reference)->firstOrFail();

        $this->authorizeReference($reference, $request);

        return $this->receiptPDFService
            ->orderOfPaymentPrint($formInput)
            ->name("OP-Transaction-{$formInput->reference_number}.pdf")
            ->inline();
    }

    /**
     * Download the receipt PDF.
     * GET /api/v1/public/opform/{reference}/download
     */
    public function download(string $reference, Request $request): mixed
    {
        $formInput = FormInput::where('reference_number', $reference)->firstOrFail();

        $this->authorizeReference($reference, $request);

        return $this->receiptPDFService
            ->orderOfPaymentPrint($formInput)
            ->name("OP-Transaction-{$formInput->reference_number}.pdf");
    }

    // ── private helpers (same logic as FormInputController) ──────────────────

    private function resolveStudentId(string $rawStudentNum): ?int
    {
        $raw = trim($rawStudentNum);
        if ($raw === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $raw) ?? '';

        $student = Student::query()->where('student_number', $raw)->first();
        if ($student !== null) {
            return $student->id;
        }

        if ($digits !== '') {
            $student = Student::query()
                ->whereRaw("REPLACE(REPLACE(student_number, '-', ''), ' ', '') = ?", [$digits])
                ->first();
            if ($student !== null) {
                return $student->id;
            }
        }

        return null;
    }

    private function resolveAcademicTermId(string $schoolYear, string $semester): ?int
    {
        $sy  = trim($schoolYear);
        $sem = trim($semester);

        if ($sy === '' || $sem === '') {
            return null;
        }

        return AcademicTerm::query()
            ->where('school_year', $sy)
            ->where('semester', $sem)
            ->value('id');
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
