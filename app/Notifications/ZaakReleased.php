<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Zaak;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;

class ZaakReleased extends BaseNotification
{
    private string $eventName;

    private string $municipalityName;

    private string $releasedByName;

    public function __construct(
        protected Zaak $zaak,
        User $releasedBy,
    ) {
        $this->eventName = $zaak->reference_data->naam_evenement;
        $this->municipalityName = $zaak->municipality->name;
        $this->releasedByName = $releasedBy->name;
    }

    public static function getLabel(): string|Htmlable|null
    {
        return __('notification/zaak-released.label');
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notification/zaak-released.mail.subject', [
                'event' => $this->eventName,
                'municipality' => $this->municipalityName,
            ]))
            ->markdown('mail.zaak-released', [
                'event' => $this->eventName,
                'municipality' => $this->municipalityName,
                'releasedBy' => $this->releasedByName,
                'viewUrl' => route('filament.municipality.resources.zaken.view', [
                    'tenant' => $this->zaak->zaaktype->municipality_id,
                    'record' => $this->zaak->id,
                ]),
            ]);
    }

    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('notification/zaak-released.database.title', [
                'event' => $this->eventName,
                'releasedBy' => $this->releasedByName,
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
