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
        return $notifiable->getNotificationChannels(static::class);
    }
}
