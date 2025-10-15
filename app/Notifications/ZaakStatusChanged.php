<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Zaak;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Notifications\Messages\MailMessage;

class ZaakStatusChanged extends BaseNotification
{
    private string $eventName;

    private string $municipalityName;

    private string $newStatus;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        protected Zaak $zaak,
        protected string $oldStatus
    ) {
        $this->eventName = $zaak->reference_data->naam_evenement;
        $this->municipalityName = $zaak->municipality->name;
        $this->newStatus = $zaak->reference_data->status_name;
    }

    public static function getLabel(): string|Htmlable|null
    {
        return __('notification/zaak-status-changed.label');
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notification/zaak-status-changed.mail.subject', [
                'event' => $this->eventName,
            ]))
            ->markdown('mail.zaak-status-changed', [
                'event' => $this->eventName,
                'municipality' => $this->municipalityName,
                'oldStatus' => $this->oldStatus,
                'newStatus' => $this->newStatus,
                'viewUrl' => route('filament.organiser.resources.zaken.view', [
                    'tenant' => $this->zaak->organisation->uuid,
                    'record' => $this->zaak->id,
                ]),
            ]);
    }

    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('notification/zaak-status-changed.database.title', [
                'event' => $this->eventName,
            ]))
            ->body(__('notification/zaak-status-changed.database.body', [
                'municipality' => $this->municipalityName,
                'old_status' => $this->oldStatus,
                'new_status' => $this->newStatus,
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
