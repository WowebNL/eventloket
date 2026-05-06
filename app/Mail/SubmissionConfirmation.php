<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Zaak;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Bevestigingsmail naar de organisator na een geslaagde submit.
 * Vervangt OF's `send_confirmation_email`-task: zelfde intent, eigen
 * layout. Stuurt — indien beschikbaar — het PDF-inzendingsbewijs mee
 * dat door `GenerateSubmissionPdf` is geschreven.
 */
class SubmissionConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Zaak $zaak) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                'Bevestiging aanvraag %s (%s)',
                $this->zaak->reference_data->naam_evenement ?? 'evenement',
                $this->zaak->public_id ?? '',
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.submission-confirmation',
            with: [
                'zaak' => $this->zaak,
                'reference' => $this->zaak->reference_data,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $path = sprintf('zaken/%s/aanvraagformulier.pdf', $this->zaak->id);
        if (! Storage::disk('local')->exists($path)) {
            return [];
        }

        return [
            Attachment::fromStorageDisk('local', $path)
                ->as(sprintf('aanvraagformulier-%s.pdf', $this->zaak->public_id))
                ->withMime('application/pdf'),
        ];
    }
}
