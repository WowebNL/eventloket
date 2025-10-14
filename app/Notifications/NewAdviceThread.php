<?php

namespace App\Notifications;

use App\Models\Threads\AdviceThread;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Notifications\Messages\MailMessage;

class NewAdviceThread extends BaseNotification
{
    private string $advisoryName;

    private string $eventName;

    private string $municipalityName;

    private string $title;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        protected AdviceThread $adviceThread,
    ) {
        $this->advisoryName = $adviceThread->advisory->name;
        $this->eventName = $adviceThread->zaak->reference_data->naam_evenement;
        $this->municipalityName = $adviceThread->zaak->municipality->name;
        $this->title = $adviceThread->title;
    }

    public static function getLabel(): string|Htmlable|null
    {
        return __('notification/new-advice-thread.label');
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notification/new-advice-thread.subject', [
                'event' => $this->eventName,
                'municipality' => $this->municipalityName,
            ]))
            ->markdown('mail.new-advice-thread', [
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
            ->title(__('notification/new-advice-thread.database.title', [
                'event' => $this->eventName,
                'municipality' => $this->municipalityName,
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
