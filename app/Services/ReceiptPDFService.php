<?php

namespace App\Services;

use App\Models\AssessmentForm;
use App\Models\FormInput;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class ReceiptPDFService
{
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
}