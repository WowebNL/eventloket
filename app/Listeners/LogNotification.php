<?php

namespace App\Listeners;

use Illuminate\Notifications\Events\NotificationSending;

class LogNotification
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(NotificationSending $event): void
    {
        $activity = activity('notifications')
            ->event('sent')
            ->causedBy($event->notifiable)
            ->withProperties([
                'notification' => get_class($event->notification),
                'channel' => $event->channel,
            ]);

        if (method_exists($event->notification, 'logSubject')) {
            $activity->performedOn($event->notification->logSubject());
        }

        $activity->log('Notification sent');
    }
}
