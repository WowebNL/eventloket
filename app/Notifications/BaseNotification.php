<?php

namespace App\Notifications;

use App\Models\User;
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
}
