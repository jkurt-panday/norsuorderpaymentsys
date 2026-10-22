<?php

namespace App\Jobs;

use App\Mail\OrderOfPaymentMail;
use App\Models\FormInput;
use App\Models\StaffInput;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class SendOrderOfPaymentEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [30, 60, 120];

    public function __construct(
        public int $formInputId,
        public ?string $subject = null,
        public ?string $recipientName = null,
        public ?string $note = null,
    ) {}

    public function handle(): void
    {
        $formInput = FormInput::query()
            ->with([
                'staffInput.bankAccount',
                'staffInput.uacs',
                'staffInput.referenceDocument',
            ])
            ->find($this->formInputId);

        if (! $formInput) {
            Log::warning('Order of Payment email skipped — form input missing', [
                'form_input_id' => $this->formInputId,
            ]);

            return;
        }

        if (! $formInput->staffInput || ! $formInput->email) {
            $this->markFailed('Order of Payment record or recipient email is missing.');

            return;
        }

        $copyLabels = [
            "Payor's Copy",
            "Cash Unit's Copy",
            "Accounting Unit's Copy",
        ];

        $portraitPdf = Pdf::loadView('pdf.op-a6', compact('formInput', 'copyLabels'))
            ->setPaper('a5', 'portrait');

        $landscapePdf = Pdf::loadView('pdf.op-landscape', compact('formInput', 'copyLabels'))
            ->setPaper('legal', 'landscape');

        Mail::to($formInput->email)->send(
            new OrderOfPaymentMail(
                $formInput,
                $portraitPdf->output(),
                $landscapePdf->output(),
                $this->subject,
                $this->recipientName,
                $this->note,
            )
        );

        $formInput->staffInput()->update([
            'emailed_at' => now(),
            'email_status' => 'sent',
            'email_error' => null,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Failed to send Order of Payment email', [
            'form_input_id' => $this->formInputId,
            'message' => $exception->getMessage(),
        ]);

        $this->markFailed($exception->getMessage());
    }

    /**
     * Persist a terminal failure so the bulk-email progress UI stops
     * reporting the recipient as "pending" forever.
     */
    private function markFailed(string $reason): void
    {
        StaffInput::query()
            ->where('form_input_id', $this->formInputId)
            ->update([
                'email_status' => 'failed',
                'email_error' => Str::limit($reason, 500),
            ]);
    }
}
