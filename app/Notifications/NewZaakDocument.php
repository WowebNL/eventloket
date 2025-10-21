<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Zaak;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Notifications\Messages\MailMessage;

class NewZaakDocument extends BaseNotification
{
    private string $eventName;

    private string $type;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        protected Zaak $zaak,
        protected string $documentTitle,
        protected bool $isNew = true,
    ) {
        $this->eventName = $zaak->reference_data->naam_evenement;
        $this->type = $isNew ? 'new' : 'updated';
    }

    public static function getLabel(): string|Htmlable|null
    {
        return __('notification/new-zaak-document.label');
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notification/new-zaak-document.mail.subject.'.$this->type, [
                'event' => $this->eventName,
            ]))
            ->markdown('mail.new-zaak-document', [
                'type' => $this->type,
                'event' => $this->eventName,
                'filename' => $this->documentTitle,
                'viewUrl' => route('filament.organiser.resources.zaken.view', [
                    'tenant' => $this->zaak->organisation->uuid,
                    'record' => $this->zaak->id,
                ]),
            ]);
    }

    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('notification/new-zaak-document.database.title.'.$this->type, [
                'event' => $this->eventName,
            ]))
            ->body(__('notification/new-zaak-document.database.body.'.$this->type, [
                'filename' => $this->documentTitle,
            ]))
            ->actions([
                Action::make('view')
                    ->label(__('View'))
                    ->url(route('filament.organiser.resources.zaken.view', [
                        'tenant' => $this->zaak->organisation->uuid,
                        'record' => $this->zaak->id,
                    ]))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
