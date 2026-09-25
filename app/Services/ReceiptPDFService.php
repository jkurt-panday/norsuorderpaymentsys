<?php

namespace App\Services;

use App\Models\AssessmentForm;
use App\Models\AuthorizedOfficial;
use App\Models\FormInput;
use Illuminate\Http\Request;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class ReceiptPDFService
{
    private const PHP_TIMEOUT_SECONDS = 360;

    private const BROWSER_TIMEOUT_SECONDS = 300;

    private const PROTOCOL_TIMEOUT_MILLISECONDS = 300_000;

    public function __construct(
        private readonly LedgerMatchingService $ledgerMatcher,
    ) {}

    public function orderOfPaymentPrint(FormInput $formInput): PdfBuilder
    {
        $this->extendPhpExecutionTime();

        $formInput->load([
            'membership',
            'paymentDetailOption',
            'supportingDocuments',
        ]);

        return Pdf::view('pdf.success-receipt', [
            'formInput' => $formInput,
        ])
            ->withBrowsershot(function (Browsershot $browsershot): void {
                $this->configureBrowsershot($browsershot);
            })
            ->format('a4');
    }

    public function assessmentPrint(AssessmentForm $assessmentForm): PdfBuilder
    {
        $this->extendPhpExecutionTime();

        $assessmentForm->load(['course']);

        return Pdf::view('pdf.assessment-success-receipt', [
            'assessmentForm' => $assessmentForm,
        ])
            ->withBrowsershot(function (Browsershot $browsershot): void {
                $this->configureBrowsershot($browsershot);
            })
            ->format('a4');
    }

    public function soaPrint(Request $request, AssessmentForm $assessment): PdfBuilder
    {
        $this->extendPhpExecutionTime();

        $validated = $request->validate([
            'ledger_student' => ['nullable', 'string', 'max:255'],
        ]);

        $assessment->load(['course']);

        $ledgerStatement = $this->ledgerMatcher->forAssessment(
            $assessment,
            $validated['ledger_student'] ?? null,
        );

        $authOfficial = AuthorizedOfficial::query()
            ->where('is_active', true)
            ->first();

        return Pdf::view('pdf.assessment-soa', [
            'assessment' => $assessment,
            'ledgerStatement' => $ledgerStatement,
            'preparedBy' => $request->user()->name ?? '—',
            'authOfficial' => $authOfficial,
        ])
            ->driver('browsershot')
            ->withBrowsershot(function (Browsershot $browsershot): void {
                $this->configureBrowsershot($browsershot);
            })
            ->format('a4');
    }

    private function configureBrowsershot(Browsershot $browsershot): void
    {
        // Full Chrome's current headless mode avoids the Windows
        // chrome-headless-shell IO.read failure while retaining CSS.
        $browsershot
            ->newHeadless()
            ->timeout(self::BROWSER_TIMEOUT_SECONDS)
            ->setOption('protocolTimeout', self::PROTOCOL_TIMEOUT_MILLISECONDS);
    }

    private function extendPhpExecutionTime(): void
    {
        ini_set('max_execution_time', (string) self::PHP_TIMEOUT_SECONDS);
        set_time_limit(self::PHP_TIMEOUT_SECONDS);
    }
}
