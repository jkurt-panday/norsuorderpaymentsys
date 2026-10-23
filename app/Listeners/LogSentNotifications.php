<?php

namespace App\Listeners;

use App\Models\ActivityLog;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class LogSentNotifications
{
    public function handle(NotificationSent $event): void
    {
        $notifiable = $event->notifiable;
        $notification = $event->notification;

        $recipient = $notifiable->email ?? $notifiable->routeNotificationFor('mail') ?? 'unknown';
        $notificationClass = class_basename($notification);

        $dedupKey = 'notification_log:'.md5((string) $recipient.'|'.$notificationClass);

        if (Cache::get($dedupKey)) {
            return;
        }

        Cache::put($dedupKey, true, 5);

        $actor = app()->bound('email.actor')
            ? app('email.actor')
            : Auth::guard('web')->user();

        if (! $actor) {
            $cached = Cache::get('email_actor:'.md5((string) $recipient.'|'.$notificationClass));
            if ($cached) {
                $actor = (object) [
                    'id' => $cached['id'],
                    'name' => $cached['name'],
                    'role' => $cached['role'],
                ];
            }
        }

        ActivityLog::create([
            'actor_id' => $actor?->getAuthIdentifier(),
            'actor_name' => $actor?->name,
            'actor_role' => $actor?->role,
            'action' => 'notification.sent',
            'target_id' => null,
            'subject_type' => null,
            'subject_id' => null,
            'description' => "Notification \"{$notificationClass}\" sent to {$recipient}.",
            'meta' => [
                'notification' => $notificationClass,
                'recipient' => (string) $recipient,
            ],
        ]);
    }
}
