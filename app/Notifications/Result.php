<?php

namespace App\Notifications;

use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Users\MunicipalityUser;
use App\Models\Zaak;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Notifications\Messages\MailMessage;
use Woweb\Openzaak\Openzaak;

/**
 * note: municipality users are only informed if organisation withdraws a pending request
 */
class Result extends BaseNotification
{
    private string $url;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        protected Zaak $zaak,
        protected Organisation|Municipality $tenant,
        protected string $title,
        protected string $message,
        protected ?array $attachmentUrls = null,
    ) {
        $this->url = $this->tenant instanceof Organisation
            ? route('filament.organiser.resources.zaken.view', [
                'record' => $this->zaak,
                'tenant' => $this->tenant,
            ])
            : route('filament.municipality.resources.zaken.view', [
                'record' => $this->zaak,
                'tenant' => $this->tenant,
            ]);
    }

    public static function getLabel(): string|Htmlable|null
    {
        return auth()->user() instanceof MunicipalityUser ? __('notification/result.ingetrokken.label') : __('notification/result.label');
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(User $notifiable): MailMessage
    {
        $mailMessage = (new MailMessage)
            ->subject($this->title)
            ->markdown('mail.result-set', [
                'content' => $this->message,
                'url' => $this->url,
            ]);

        // Add attachments if they exist
        if ($this->attachmentUrls) {
            $attachments = $this->zaak->documenten->whereIn('url', $this->attachmentUrls)->map(fn ($document) => Attachment::fromData(
                fn () => (new Openzaak)->getRaw($document->inhoud), $document->bestandsnaam)->withMime($document->formaat)
            )->toArray();

            $mailMessage->attachMany($attachments);
        }

        return $mailMessage;
    }

    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->title)
            ->body($this->message)
            ->actions([
                Action::make('view')
                    ->label(__('View'))
                    ->url($this->url)
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
