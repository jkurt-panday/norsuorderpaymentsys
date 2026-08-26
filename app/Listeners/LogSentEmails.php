<?php

namespace App\Listeners;

use App\Models\ActivityLog;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Mime\Address;

class LogSentEmails
{
    public function handle(MessageSent $event): void
    {
        $message = $event->message;

        $recipientEmails = $this->extractRecipients($message);

        if ($recipientEmails === '') {
            return;
        }

        $subject = $message->getSubject() ?? '(no subject)';

        $dedupKey = 'email_log:' . md5($recipientEmails . '|' . $subject);

        if (Cache::get($dedupKey)) {
            return;
        }

        Cache::put($dedupKey, true, 5);

        $actor = Auth::guard('web')->user();

        ActivityLog::create([
            'actor_id' => $actor?->getAuthIdentifier(),
            'actor_name' => $actor?->name,
            'actor_role' => $actor?->role,
            'action' => 'email.sent',
            'target_id' => null,
            'subject_type' => null,
            'subject_id' => null,
            'description' => "Email sent to {$recipientEmails} with subject \"{$subject}\".",
            'meta' => [
                'to' => $recipientEmails,
                'subject' => $subject,
            ],
        ]);
    }

    private function extractRecipients(\Symfony\Component\Mime\Email $message): string
    {
        $recipients = [];

        $this->addAddresses($recipients, $message->getTo());
        $this->addAddresses($recipients, $message->getCc());
        $this->addAddresses($recipients, $message->getBcc());

        return implode(', ', array_unique($recipients));
    }

    /**
     * @param array<int, string> $recipients
     */
    private function addAddresses(array &$recipients, mixed $addresses): void
    {
        if ($addresses === null || $addresses === []) {
            return;
        }

        if ($addresses instanceof Address) {
            $recipients[] = $addresses->getAddress();

            return;
        }

        foreach ($addresses as $address) {
            if ($address instanceof Address) {
                $recipients[] = $address->getAddress();
            } elseif (is_string($address)) {
                $recipients[] = $address;
            }
        }
    }
}