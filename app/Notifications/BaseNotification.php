<?php

namespace App\Notifications;

use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use App\Models\Zaak;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use App\Notifications\Contracts\HasLabel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

abstract class BaseNotification extends Notification implements HasLabel, ShouldQueue
{
    use Queueable;
    use RespectsNotificationPreferences;

    abstract public function toMail(User $notifiable): MailMessage;

    abstract public function toDatabase(User $notifiable): array;

    abstract public function logSubject(): Model;

    /**
     * The zaak this notification belongs to, used to honour a connection's
     * "suppress notifications" toggle. Derived from the log subject (a zaak, a
     * thread, or a message), or null when no zaak applies.
     */
    public function suppressionZaak(): ?Zaak
    {
        $subject = $this->logSubject();

        if ($subject instanceof Message) {
            $subject = $subject->thread;
        }

        if ($subject instanceof Zaak) {
            return $subject;
        }

        if ($subject instanceof Thread) {
            return $subject->zaak;
        }

        return null;
    }
}
