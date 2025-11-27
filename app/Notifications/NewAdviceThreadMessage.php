<?php

namespace App\Notifications;

use App\Models\Message;
use App\Models\Threads\AdviceThread;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;

class NewAdviceThreadMessage extends BaseNotification
{
    private string $senderName;

    private string $eventName;

    private string $municipalityName;

    private string $advisoryName;

    private string $title;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        protected Message $message,
    ) {
        /** @var AdviceThread $adviceThread */
        $adviceThread = $message->thread;
        $this->senderName = $message->user->name;
        $this->eventName = $adviceThread->zaak->reference_data->naam_evenement;
        $this->municipalityName = $adviceThread->zaak->municipality->name;
        $this->advisoryName = $adviceThread->advisory->name;
        $this->title = $adviceThread->title;
    }

    public static function getLabel(): string|Htmlable|null
    {
        return __('notification/new-advice-thread-message.label');
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notification/new-advice-thread-message.mail.subject', [
                'sender' => $this->senderName,
                'event' => $this->eventName,
                'municipality' => $this->municipalityName,
            ]))
            ->markdown('mail.new-advice-thread-message', [
                'sender' => $this->senderName,
                'advisory' => $this->advisoryName,
                'event' => $this->eventName,
                'title' => $this->title,
                'viewUrl' => $this->message->getViewUrlForUser($notifiable),
            ]);
    }

    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('notification/new-advice-thread-message.database.title', [
                'sender' => $this->senderName,
                'event' => $this->eventName,
                'municipality' => $this->municipalityName,
            ]))
            ->actions([
                Action::make('view')
                    ->label(__('View'))
                    ->url($this->message->getViewUrlForUser($notifiable))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    public function logSubject(): Model
    {
        return $this->message;
    }
}
