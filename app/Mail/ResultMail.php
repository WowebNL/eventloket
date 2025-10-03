<?php

namespace App\Mail;

use App\Models\Organisation;
use App\Models\Zaak;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Woweb\Openzaak\Openzaak;

class ResultMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        protected Zaak $zaak,
        protected Organisation $tenant,
        protected string $title,
        protected string $message,
        protected ?array $attachmentUrls = null,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->title,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.result-set',
            with: [
                'content' => $this->message,
                'url' => route('filament.organiser.resources.zaken.view', ['record' => $this->zaak, 'tenant' => $this->tenant]),
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        if (! $this->attachmentUrls) {
            return [];
        }

        return $this->zaak->documenten->whereIn('url', $this->attachmentUrls)->map(fn ($document) => Attachment::fromData(
            fn () => (new Openzaak)->getRaw($document->inhoud), $document->bestandsnaam
        )->withMime($document->formaat))->toArray();
    }
}
