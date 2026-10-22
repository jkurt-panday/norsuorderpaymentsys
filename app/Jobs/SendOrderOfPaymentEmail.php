<?php

namespace App\Jobs;

use App\Mail\OrderOfPaymentMail;
use App\Models\ActivityLog;
use App\Models\FormInput;
use App\Models\StaffInput;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
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
        public ?int $actorId = null,
    ) {}

    public function handle(): void
    {
        $actor = null;

        if ($this->actorId) {
            $actor = User::find($this->actorId);

            if ($actor) {
                Auth::guard('web')->setUser($actor);
                app()->instance('email.actor', $actor);
            }
        } else {
            $actor = Auth::guard('web')->user();
        }
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

        $subjectText = $this->subject ?? "Order of Payment - {$formInput->reference_number}";

        try {
            \Illuminate\Support\Facades\Cache::put(
                'email_actor:'.md5((string)$formInput->email.'|'.$subjectText),
                [
                    'id' => $actor?->getAuthIdentifier(),
                    'name' => $actor?->name,
                    'role' => $actor?->role,
                ],
                now()->addMinutes(10)
            );

            // Create activity log directly in job so queue worker attributes it to correct actor
            $log = ActivityLog::create([
                'actor_id' => $actor?->getAuthIdentifier(),
                'actor_name' => $actor?->name,
                'actor_role' => $actor?->role,
                'action' => 'email.sent',
                'target_id' => null,
                'subject_type' => null,
                'subject_id' => null,
                'description' => "Email sent to {$formInput->email} with subject \"{$subjectText}\".",
                'meta' => [
                    'to' => $formInput->email,
                    'subject' => $subjectText,
                ],
            ]);
            // Remove any existing null-actor logs for this email
            ActivityLog::where('action', 'email.sent')
                ->whereNull('actor_id')
                ->where('description', $log->description)
                ->where('id', '!=', $log->id)
                ->delete();
            // Delete any null-actor logs that match this exact description except this one
            ActivityLog::where('action', 'email.sent')
                ->whereNull('actor_id')
                ->where('description', $log->description)
                ->where('id', '!=', $log->id)
                ->delete();
        } catch (\Throwable $e) {
            Log::debug('Failed to log OP email activity in job', ['error' => $e->getMessage()]);
        }

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
