<?php

namespace App\Notifications;

use App\Enums\Role;
use App\Models\User;
use App\Models\Zaak;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;

class NewZaak extends BaseNotification
{
    private string $eventName;

    private string $municipalityName;

    /**
     * Create a new notification instance.
     *
     * The event name is optional on the reference data: a zaak built straight
     * from the ZGW eigenschappen (a doorkomst deelzaak whose zaaktype does not
     * carry the eigenschap, a recovered zaak) can arrive without one. This
     * notification is raised synchronously from the zaak observer, so a missing
     * name used to abort whatever was creating the zaak rather than merely
     * spoiling one message. A placeholder is used instead, keeping the failure
     * inside the notification where it belongs.
     */
    public function __construct(
        protected Zaak $zaak,
    ) {
        $eventName = $zaak->reference_data->naam_evenement;

        $this->eventName = ($eventName === null || $eventName === '')
            ? __('notification/new-zaak.unnamed_event')
            : $eventName;

        $this->municipalityName = (string) $zaak->municipality?->name;
    }

    public static function getLabel(): string|Htmlable|null
    {
        $type = auth()->user()->role === Role::Organiser ? 'organiser' : 'reviewer';

        return __("notification/new-zaak.label.{$type}");
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(User $notifiable): MailMessage
    {
        $type = $notifiable->role === Role::Organiser ? 'organiser' : 'reviewer';

        return (new MailMessage)
            ->subject(__("notification/new-zaak.mail.subject.$type", [
                'event' => $this->eventName,
            ]))
            ->markdown('mail.new-zaak', [
                'type' => $type,
                'event' => $this->eventName,
                'municipality' => $this->municipalityName,
                'viewUrl' => $this->getViewUrl($type),
            ]);
    }

    public function toDatabase(User $notifiable): array
    {
        $type = $notifiable->role === Role::Organiser ? 'organiser' : 'reviewer';

        return FilamentNotification::make()
            ->title(__("notification/new-zaak.database.title.$type", [
                'event' => $this->eventName,
            ]))
            ->body(__("notification/new-zaak.database.body.$type", [
                'event' => $this->eventName,
                'municipality' => $this->municipalityName,
            ]))
            ->actions([
                Action::make('view')
                    ->label(__('View'))
                    ->url($this->getViewUrl($type))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    public function logSubject(): Model
    {
        return $this->zaak;
    }

    private function getViewUrl(string $type)
    {
        if ($type === 'organiser') {
            return route('filament.organiser.resources.zaken.view', [
                'tenant' => $this->zaak->organisation->uuid,
                'record' => $this->zaak->id,
            ]);
        }

        return route('filament.municipality.resources.zaken.view', [
            'tenant' => $this->zaak->zaaktype->municipality_id,
            'record' => $this->zaak->id,
        ]);
    }
}
