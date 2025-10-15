<?php

namespace App\Notifications;

use App\Models\Threads\AdviceThread;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Notifications\Messages\MailMessage;

class AdviceReminder extends BaseNotification
{
    private string $when;

    private string $eventName;

    private string $municipalityName;

    private string $advisoryName;

    private string $title;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        protected AdviceThread $adviceThread,
        protected int $daysBeforeDue,
    ) {
        $this->when = trans_choice('notification/advice-reminder.when', $daysBeforeDue, ['count' => $daysBeforeDue]);
        $this->eventName = $adviceThread->zaak->reference_data->naam_evenement;
        $this->municipalityName = $adviceThread->zaak->municipality->name;
        $this->advisoryName = $adviceThread->advisory->name;
        $this->title = $adviceThread->title;
    }

    public static function getLabel(): string|Htmlable|null
    {
        return __('notification/advice-reminder.label');
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notification/advice-reminder.mail.subject', [
                'event' => $this->eventName,
                'when' => $this->when,
            ]))
            ->markdown('mail.advice-reminder', [
                'when' => $this->when,
                'advisory' => $this->advisoryName,
                'municipality' => $this->municipalityName,
                'event' => $this->eventName,
                'title' => $this->title,
                'viewUrl' => $this->adviceThread->getViewUrlForUser($notifiable),
            ]);
    }

    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('notification/advice-reminder.database.title', [
                'event' => $this->eventName,
                'when' => $this->when,
            ]))
            ->body(__('notification/advice-reminder.database.body', [
                'municipality' => $this->municipalityName,
                'advisory' => $this->advisoryName,
                'when' => $this->when,
            ]))
            ->actions([
                Action::make('view')
                    ->label(__('View'))
                    ->url($this->adviceThread->getViewUrlForUser($notifiable))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
