<?php

namespace App\Notifications;

use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Notifications\Messages\MailMessage;
use Woweb\Openzaak\Openzaak;

class Result extends BaseNotification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(
        protected Zaak $zaak,
        protected Organisation $tenant,
        protected string $title,
        protected string $message,
        protected ?array $attachmentUrls = null,
    ) {}

    public static function getLabel(): string|Htmlable|null
    {
        return __('notification/result.label');
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
                'url' => route('filament.organiser.resources.zaken.view', [
                    'record' => $this->zaak,
                    'tenant' => $this->tenant,
                ]),
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
                    ->url(route('filament.organiser.resources.zaken.view', [
                        'record' => $this->zaak,
                        'tenant' => $this->tenant,
                    ]))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
