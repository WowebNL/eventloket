<?php

namespace App\Notifications;

use App\Models\Threads\AdviceThread;
use App\Models\User;
use App\Models\Users\AdvisorUser;
use App\Models\Users\OrganiserUser;
use App\Models\Zaak;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
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
    public function toMail(User $notifiable): MailMessage
    {
        $tenant = $this->getTenantIdentificationForUser($notifiable);
        $type = $this->getType($notifiable);

        return (new MailMessage)
            ->subject(__('notification/new-zaak-document.mail.subject.'.$this->type, [
                'event' => $this->eventName,
            ]))
            ->markdown('mail.new-zaak-document', [
                'type' => $this->type,
                'event' => $this->eventName,
                'filename' => $this->documentTitle,
                'viewUrl' => $this->getViewUrl($type, $tenant),
            ]);
    }

    public function toDatabase(User $notifiable): array
    {
        $tenant = $this->getTenantIdentificationForUser($notifiable);
        $type = $this->getType($notifiable);

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
                    ->url($this->getViewUrl($type, $tenant))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    private function getTenantIdentificationForUser(User $notifiable): string|int
    {
        return match (get_class($notifiable)) {
            OrganiserUser::class => $this->zaak->organisation->uuid,
            AdvisorUser::class => $this->zaak->adviceThreads->map(function (Model $thread) use ($notifiable) {
                /** @var AdviceThread $thread */
                return in_array($thread->advisory_id, $notifiable->advisories->pluck('id')->toArray());
            }
            )->first(),
            default => $this->zaak->municipality->id
        };
    }

    private function getType(User $notifiable): string
    {
        return match (get_class($notifiable)) {
            OrganiserUser::class => 'organiser',
            AdvisorUser::class => 'advisor',
            default => 'municipality'
        };
    }

    public function logSubject(): Model
    {
        return $this->zaak;
    }

    private function getViewUrl(string $type, string|int $tenant)
    {
        return route('filament.'.$type.'.resources.zaken.view', [
            'tenant' => $tenant,
            'record' => $this->zaak->id,
        ]);
    }
}
