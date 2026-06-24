<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Zaak;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;

class AssignedToZaak extends BaseNotification
{
    private string $eventName;

    private string $municipalityName;

    public function __construct(
        protected Zaak $zaak,
    ) {
        $this->eventName = $zaak->reference_data->naam_evenement;
        $this->municipalityName = $zaak->municipality->name;
    }

    public static function getLabel(): string|Htmlable|null
    {
        return __('notification/assigned-to-zaak.label');
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notification/assigned-to-zaak.mail.subject', [
                'event' => $this->eventName,
                'municipality' => $this->municipalityName,
            ]))
            ->markdown('mail.assigned-to-zaak', [
                'event' => $this->eventName,
                'municipality' => $this->municipalityName,
                'viewUrl' => route('filament.municipality.resources.zaken.view', [
                    'tenant' => $this->zaak->zaaktype->municipality_id,
                    'record' => $this->zaak->id,
                ]),
            ]);
    }

    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('notification/assigned-to-zaak.database.title', [
                'event' => $this->eventName,
                'municipality' => $this->municipalityName,
            ]))
            ->actions([
                Action::make('view')
                    ->label(__('View'))
                    ->url(route('filament.municipality.resources.zaken.view', [
                        'tenant' => $this->zaak->zaaktype->municipality_id,
                        'record' => $this->zaak->id,
                    ]))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    public function logSubject(): Model
    {
        return $this->zaak;
    }
}
