<?php

namespace App\Http\Controllers;

use App\Models\AssessmentForm;
use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Redirect as FacadeRedirect;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use App\Models\Courses;
use Inertia\Response as InertiaResponse;
use App\Http\Requests\AssessmentFormInfoRequest;
use Illuminate\Support\Facades\DB;
use App\Services\ReferenceNumberService;
use App\Services\ReceiptPDFService;

class AssessmentFormController extends Controller
{

    // constructor dependency injection 
    public function __construct(
        protected ReferenceNumberService $assessment_ref_num,
        protected ReceiptPDFService $receiptPdfService
    ) {}
    
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): InertiaResponse
    {
        $courses = Courses::query()->orderBy('course_desc')->get();
        
        return Inertia::render('public/AssessmentForm', [
            'courses' => $courses
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AssessmentFormInfoRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try 
        {
            DB::beginTransaction();

            $reference_number = $this->assessment_ref_num->assess_ref_gen();

            // 1. Create the assessment record
            $assessment = AssessmentForm::query()->create([
                'reference_number' => $reference_number,
                'email'            => $validated['email'],
                'contact_num'      => $validated['contact_num'],
                'first_name'       => $validated['first_name'],
                'middle_name'      => $validated['middle_name'] ?? null,
                'last_name'        => $validated['last_name'],
                'student_id'       => $validated['student_id'],
                'course_id'        => $validated['course_id'], // Or 'course_id' if renamed
                'address'          => $validated['address'],
                'enrolled_under'   => $validated['enrolled_under'],
                'sy_last_attended' => $validated['sy_last_attended'],
                'semester'         => $validated['semester'],
            ]);
    
            DB::commit();

            // dd($assessment);

            // 2. Return Inertia response with success notification
            // return redirect()->back()->with('success', 'Assessment request submitted successfully!');

            return redirect()->route('public.complete', [
                'assessmentForm' => $assessment->reference_number,
            ]);
            
        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('Assessment form submission failed', [
                'message' => $e->getMessage(),
                // 'trace' => $e->getTraceAsString(),
            ]);
            
            return back()->withInput()->with('error', 'Faild to submit request.'.$e->getMessage());
        }
    }

    public function complete(string $reference_number): InertiaResponse
    {
        $assessmentForm = AssessmentForm::query()->where('reference_number', $reference_number)->firstOrFail();
        $assessmentForm->load(['course']);

        // dd($assessmentform);
        
        return Inertia::render('public/AssessmentSuccess', [
            // 'reference_number' => $assessmentform->reference_number,
            'assessmentForm' => $assessmentForm,        // this format must be followed for the props
        ]);
    }

    /**
    * Stream the PDF receipt in the browser window.
    */
    public function print(AssessmentForm $assessmentForm)
    {
        // $assessmentForm = AssessmentForm::query()
        //     ->where('reference_number', $referenceNumber)
        //     ->firstOrFail();

        // dd($formInput);

        return $this->receiptPdfService
            ->assessmentPrint($assessmentForm)
            ->inline();
    }

    /**
    * Download the PDF receipt file.
    */
    public function downloadReceipt(AssessmentForm $assessmentForm)
    {
        return $this->receiptPdfService
            ->assessmentPrint($assessmentForm)
            ->name("receipt-{$assessmentForm->reference_number}.pdf");
    }

    /**
     * Display the specified resource.
     */
    public function show(AssessmentForm $assessmentForm)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AssessmentForm $assessmentForm)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AssessmentForm $assessmentForm)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AssessmentForm $assessmentForm)
    {
        //
    }
}
