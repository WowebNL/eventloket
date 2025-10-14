<?php

namespace App\Notifications;

use App\Models\Message;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Notifications\Messages\MailMessage;

class NewOrganiserThreadMessage extends BaseNotification
{
    private string $senderName;

    private string $eventName;

    private string $organisationName;

    private string $title;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        protected Message $message,
    ) {
        $organiserThread = $message->thread;
        $this->senderName = $message->user->name;
        $this->eventName = $organiserThread->zaak->reference_data->naam_evenement;
        $this->organisationName = $organiserThread->zaak->organisation->name;
        $this->title = $organiserThread->title;
    }

    public static function getLabel(): string|Htmlable|null
    {
        return __('notification/new-organiser-thread-message.label');
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('mail/new-organiser-thread-message.subject', [
                'sender' => $this->senderName,
                'event' => $this->eventName,
                'organisation' => $this->organisationName,
            ]))
            ->markdown('mail.new-organiser-thread-message', [
                'sender' => $this->senderName,
                'organisation' => $this->organisationName,
                'event' => $this->eventName,
                'title' => $this->title,
                'viewUrl' => $this->message->getViewUrlForUser($notifiable),
            ]);
    }

    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('notification/new-organiser-thread-message.database.title', [
                'sender' => $this->senderName,
                'event' => $this->eventName,
                'organisation' => $this->organisationName,
            ]))
            ->actions([
                Action::make('view')
                    ->label(__('View'))
                    ->url($this->message->getViewUrlForUser($notifiable))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
