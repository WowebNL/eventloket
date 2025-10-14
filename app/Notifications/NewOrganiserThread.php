<?php

namespace App\Notifications;

use App\Models\Threads\OrganiserThread;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Notifications\Messages\MailMessage;

class NewOrganiserThread extends BaseNotification
{
    private string $eventName;

    private string $municipalityName;

    private string $organisationName;

    private string $title;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        protected OrganiserThread $organiserThread,
    ) {
        $this->eventName = $organiserThread->zaak->reference_data->naam_evenement;
        $this->municipalityName = $organiserThread->zaak->municipality->name;
        $this->organisationName = $organiserThread->zaak->organisation->name;
        $this->title = $organiserThread->title;
    }

    public static function getLabel(): string|Htmlable|null
    {
        return __('notification/new-organiser-thread.label');
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notification/new-organiser-thread.mail.subject', [
                'event' => $this->eventName,
                'municipality' => $this->municipalityName,
            ]))
            ->markdown('mail.new-organiser-thread', [
                'organisation' => $this->organisationName,
                'municipality' => $this->municipalityName,
                'event' => $this->eventName,
                'title' => $this->title,
                'viewUrl' => $this->organiserThread->getViewUrlForUser($notifiable),
            ]);
    }

    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('notification/new-organiser-thread.database.title', [
                'event' => $this->eventName,
                'municipality' => $this->municipalityName,
            ]))
            ->actions([
                Action::make('view')
                    ->label(__('View'))
                    ->url($this->organiserThread->getViewUrlForUser($notifiable))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
