<?php

namespace App\Services;

use App\Models\FormInput;
use Spatie\LaravelPdf\Facades\Pdf;

class ReceiptPDFService
{
    public function make(FormInput $formInput)
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
}