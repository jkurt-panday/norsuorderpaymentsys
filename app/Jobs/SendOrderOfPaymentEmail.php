<?php

namespace App\Jobs;

use App\Mail\OrderOfPaymentMail;
use App\Models\FormInput;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendOrderOfPaymentEmail implements ShouldBeUnique, ShouldQueue
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

    public function uniqueId(): string
    {
        return (string) $this->formInputId;
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(): void
    {
        $formInput = FormInput::query()
            ->with([
                'staffInput.bankAccount',
                'staffInput.uacs',
                'staffInput.referenceDocument',
            ])
            ->findOrFail($this->formInputId);

        if (! $formInput->staffInput || ! $formInput->email) {
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

        $formInput->staffInput()->update(['emailed_at' => now()]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Failed to send Order of Payment email', [
            'form_input_id' => $this->formInputId,
            'message' => $exception->getMessage(),
        ]);
    }
}
