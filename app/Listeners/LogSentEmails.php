<?php

namespace App\Listeners;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

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

        $dedupKey = 'email_log:'.md5($recipientEmails.'|'.$subject);

        if (Cache::get($dedupKey)) {
            return;
        }

        Cache::put($dedupKey, true, 5); // short dedup
        // Skip duplicate logging quickly
        if (ActivityLog::where('action', 'email.sent')->where('description', 'like', "%{$recipientEmails}%")->where('created_at', '>=', now()->subSeconds(5))->exists()) {
            return;
        }

        $actor = null;
        $cached = Cache::get('email_actor:'.md5($recipientEmails.'|'.$subject));
        if (is_array($cached) && isset($cached['id'])) {
            $actor = User::query()->whereKey($cached['id'])->first();
        }

        if ($actor === null && Auth::guard('web')->check()) {
            $authenticated = Auth::guard('web')->user();
            $actor = $authenticated instanceof User ? $authenticated : null;
        }

        // If job likely already created the log, don't create another (prevents system)
        if (! $actor || ! $actor->getAuthIdentifier()) {
            // Check if recent log exists from job
            $recent = ActivityLog::where('description', 'like', "%{$recipientEmails}%")
                ->where('description', 'like', '%'.str_replace('"', '', $subject).'%')
                ->latest()
                ->first();
            if ($recent && $recent->created_at && $recent->created_at->diffInSeconds(now()) < 30) {
                return;
            }
        }

        $actorId = $actor instanceof User ? $actor->getAuthIdentifier() : null;
        $actorName = $actor instanceof User ? $actor->name : null;
        $actorRole = $actor instanceof User ? $actor->role : null;

        ActivityLog::create([
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'actor_role' => $actorRole,
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

    private function extractRecipients(Email $message): string
    {
        $recipients = [];

        $this->addAddresses($recipients, $message->getTo());
        $this->addAddresses($recipients, $message->getCc());
        $this->addAddresses($recipients, $message->getBcc());

        return implode(', ', array_unique($recipients));
    }

    /**
     * @param  array<int, string>  $recipients
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
