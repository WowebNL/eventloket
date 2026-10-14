<?php

namespace App\Notifications\Concerns;

use App\Models\User;

trait RespectsNotificationPreferences
{
    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        // Connections may suppress all notifications about a zaak; only the
        // submission confirmation (a plain Mailable) still goes out.
        $zaak = $this->suppressionZaak();

        if ($zaak !== null && $zaak->suppressesNotifications()) {
            return [];
        }

        return $notifiable->getNotificationChannels(static::class);
    }
}
