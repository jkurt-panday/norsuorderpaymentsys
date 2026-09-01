<?php

namespace App\Services;

use App\Models\AssessmentForm;
use App\Models\FormInput;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use App\Services\LedgerMatchingService; // adjust to wherever this actually lives
use Illuminate\Http\Request;

class ReceiptPDFService
{
    public function __construct(
        private readonly LedgerMatchingService $ledgerMatcher,
    ) {}
    
    public function orderOfPaymentPrint(FormInput $formInput): PdfBuilder
    {
        $formInput->load([
            'membership',
            'paymentDetailOption',
            'supportingDocuments',
        ]);
    
        return Pdf::view('pdf.success-receipt', [
            'formInput' => $formInput,
        ])->format('a4');
    }

    public function assessmentPrint(AssessmentForm $assessmentForm): PdfBuilder
    {
        $assessmentForm->load(['course']);

        return Pdf::view('pdf.assessment-success-receipt', [
            'assessmentForm' => $assessmentForm,
        ])->format('a4');
    }

    public function soaPrint(Request $request, AssessmentForm $assessment): PdfBuilder
    {
        $validated = $request->validate([
            'ledger_student' => ['nullable', 'string', 'max:255'],
        ]);

        $assessment->load(['course']);

        $ledgerStatement = $this->ledgerMatcher->forAssessment(
            $assessment,
            $validated['ledger_student'] ?? null,
        );

        return Pdf::view('pdf.assessment-soa', [
            'assessment'  => $assessment,
            'ledgerStatement' => $ledgerStatement,
        ])->format('a5');
    }
}