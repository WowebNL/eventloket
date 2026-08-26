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
use Illuminate\Database\Eloquent\Model;
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
        [$attachments, $omittedAttachments] = $this->resolveAttachments();

        $mailMessage = (new MailMessage)
            ->subject($this->title)
            ->markdown('mail.result-set', [
                'content' => $this->message,
                'url' => $this->url,
                'omittedAttachments' => $omittedAttachments,
            ]);

        $mailMessage->attachMany($attachments);

        return $mailMessage;
    }

    /**
     * Build the attachments for this mail, keeping their combined size within
     * the configured budget.
     *
     * A receiving mail server rejects a message that exceeds its size limit
     * outright (SMTP 552), which loses the entire notification: message and
     * attachments alike. Documents are therefore added in the order they appear
     * on the zaak until the budget is spent, and a document that no longer fits
     * is skipped without blocking the smaller ones behind it. The skipped file
     * names are returned so the mail can name them and point the recipient at
     * the application, where every document stays available.
     *
     * The size is measured on the downloaded bytes rather than on the ZGW
     * `bestandsomvang` metadata: that field is not guaranteed to be filled by
     * every backend, and a wrong value would put the message back over the
     * server's limit. The bytes are fetched here either way, exactly as before,
     * because Attachment::fromData resolves its data as soon as the attachment
     * is added to the message.
     *
     * @return array{0: array<int, Attachment>, 1: array<int, string>}
     */
    private function resolveAttachments(): array
    {
        if (! $this->attachmentUrls) {
            return [[], []];
        }

        $remaining = (int) config('mail.attachments.max_total_bytes');
        $attachments = [];
        $omitted = [];

        foreach ($this->zaak->documenten->whereIn('url', $this->attachmentUrls) as $document) {
            $contents = (new Openzaak)->getRaw($document->inhoud);

            if (strlen($contents) > $remaining) {
                $omitted[] = $document->bestandsnaam;

                continue;
            }

            $remaining -= strlen($contents);

            $attachments[] = Attachment::fromData(fn () => $contents, $document->bestandsnaam)
                ->withMime($document->formaat);
        }

        return [$attachments, $omitted];
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

    public function logSubject(): Model
    {
        return $this->zaak;
    }
}
